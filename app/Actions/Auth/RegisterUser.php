<?php

namespace App\Actions\Auth;

use App\Enums\ContactPurpose;
use App\Enums\ContactType;
use App\Enums\UserStatus;
use App\Models\User;
use App\Rules\AsciiEmail;
use App\Rules\PhoneNumber;
use App\Support\ContactNormalizer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;

/**
 * Creates an account identified by an email address, a phone number, or both, and requests the
 * verification code of each contact given.
 *
 * The account starts as {@see UserStatus::PendingVerification}; {@see VerifyContact} activates it.
 * A pending account cannot sign in (see {@see AuthenticateCredentials}) and is deleted after
 * `auth.pending_accounts.ttl_days` without verification ({@see PruneExpiredPendingAccounts}).
 *
 * Contact ownership: only a VERIFIED contact is reserved. When a requested contact is held,
 * unverified, by another account, it is detached from that account (its unused codes are
 * invalidated), and a pending account left without any contact is deleted: an abandoned or
 * squatted registration never blocks the real owner. A contact VERIFIED by another account is
 * never touched: the whole registration is then a silent no-op. The check and the change happen in
 * one transaction, with the holder rows locked; the unique indexes on `email` and `phone` stay the
 * final arbiter (a concurrent registration that wins makes this one a silent no-op too).
 *
 * Anti-enumeration: the action returns nothing and the caller answers with the same generic
 * response in every case. The password is hashed first, on every path, so that response times
 * are similar; the residual difference (a few inserts when an account is created) is covered by
 * the per-contact throttle. Uniqueness is therefore NOT part of {@see self::rules()}: a `unique`
 * rule would reveal which contacts are taken.
 *
 * Callers are expected to throttle (`throttle:register`).
 */
final class RegisterUser
{
    /**
     * Validation rules for the input, shared by the API and Livewire. The password follows
     * `Password::defaults()` (12 characters minimum, breach check in production). Emails must be
     * ASCII (see {@see ContactNormalizer::email()}). The terms of use and the privacy policy must be
     * accepted: {@see self::handle()} records that acceptance, so it is only called once they are.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'required_without:phone', 'string', 'email:rfc', new AsciiEmail, 'max:255'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:32', new PhoneNumber],
            'password' => ['required', 'string', Password::defaults(), 'max:255'],
            'locale' => ['nullable', 'string', Rule::in((array) config('app.supported_locales'))],
            'accept_terms' => ['accepted'],
        ];
    }

    public function __construct(private readonly SendContactVerification $sendVerification) {}

    /**
     * @param  string|null  $email  Email address in any spelling; null when registering with a phone only.
     * @param  string|null  $phone  Phone number, national or international; null when registering with an email only.
     * @param  string|null  $locale  `fr` or `ar`; the current application locale when null or unsupported.
     *
     * @throws InvalidArgumentException When neither an email nor a phone number is given (validate with {@see self::rules()} first).
     */
    public function handle(string $name, ?string $email, ?string $phone, string $password, ?string $locale = null): void
    {
        $email = ContactNormalizer::email($email);
        $phone = ContactNormalizer::phone($phone, (string) config('app.default_phone_country_code', '222'));

        if ($email === null && $phone === null) {
            throw new InvalidArgumentException('An email address or a phone number is required.');
        }

        $hashedPassword = Hash::make($password);

        try {
            DB::transaction(function () use ($name, $email, $phone, $hashedPassword, $locale): void {
                $holders = $this->lockHolders($email, $phone);

                if (! $this->canReleaseContacts($holders, $email, $phone)) {
                    return;
                }

                foreach ($holders as $holder) {
                    $this->releaseContacts($holder, $email, $phone);
                }

                $user = new User([
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'password' => $hashedPassword,
                    'locale' => $this->supportedLocale($locale),
                ]);
                $user->forceFill([
                    'status' => UserStatus::PendingVerification,
                    'terms_accepted_at' => now(),
                    'terms_version' => (string) config('legal.version'),
                ])->save();

                foreach (array_filter([$email, $phone]) as $contact) {
                    $this->sendVerification->handle($contact, ContactPurpose::ContactVerification);
                }
            });
        } catch (UniqueConstraintViolationException) {
            // A concurrent registration took the contact first: same silent outcome as above.
        }
    }

    /**
     * Accounts that currently hold the requested email or phone, locked until the transaction ends.
     *
     * @return Collection<int, User>
     */
    private function lockHolders(?string $email, ?string $phone): Collection
    {
        return User::query()
            ->where(function ($query) use ($email, $phone): void {
                if ($email !== null) {
                    $query->orWhere('email', $email);
                }

                if ($phone !== null) {
                    $query->orWhere('phone', $phone);
                }
            })
            ->lockForUpdate()
            ->get();
    }

    /**
     * Whether every requested contact can be taken from its current holder: it is not verified,
     * and a holder that would be left without any contact is an abandoned registration (pending
     * verification) that can be deleted.
     *
     * @param  Collection<int, User>  $holders
     */
    private function canReleaseContacts(Collection $holders, ?string $email, ?string $phone): bool
    {
        foreach ($holders as $holder) {
            $holdsEmail = $email !== null && $holder->email === $email;
            $holdsPhone = $phone !== null && $holder->phone === $phone;

            if (($holdsEmail && $holder->hasVerified(ContactType::Email))
                || ($holdsPhone && $holder->hasVerified(ContactType::Phone))) {
                return false;
            }

            $keepsAContact = ($holder->email !== null && ! $holdsEmail) || ($holder->phone !== null && ! $holdsPhone);

            if (! $keepsAContact && $holder->status !== UserStatus::PendingVerification) {
                return false;
            }
        }

        return true;
    }

    private function releaseContacts(User $holder, ?string $email, ?string $phone): void
    {
        if ($email !== null && $holder->email === $email) {
            $holder->detachContact(ContactType::Email);
        }

        if ($phone !== null && $holder->phone === $phone) {
            $holder->detachContact(ContactType::Phone);
        }

        if ($holder->email === null && $holder->phone === null) {
            $holder->tokens()->delete();
            $holder->contactVerifications()->delete();
            $holder->delete();

            return;
        }

        $holder->save();
    }

    private function supportedLocale(?string $locale): string
    {
        $supported = (array) config('app.supported_locales');

        return $locale !== null && in_array($locale, $supported, true) ? $locale : app()->getLocale();
    }
}
