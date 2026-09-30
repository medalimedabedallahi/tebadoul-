<?php

namespace App\Actions\Auth;

use App\Enums\TokenAbility;
use App\Exceptions\Auth\AccountSuspendedException;
use App\Exceptions\Auth\InvalidCredentialsException;
use App\Models\User;

/**
 * Signs an account in with a VERIFIED email address or phone number and a password, and issues a
 * Sanctum personal access token.
 *
 * Credentials are checked by {@see AuthenticateCredentials}, shared with the web sign-in
 * component: see it for the anti-enumeration guarantees (identical failure for an unknown or
 * unverified identifier, an account pending verification and a wrong password; constant-time dummy
 * check; suspension reported only after the password is verified).
 *
 * Only an active account ever receives a token, with the `access-api` ability. An account pending
 * verification gets the same 401 as invalid credentials: it verifies its contact through the
 * public verification endpoints, then signs in. The token expires after `sanctum.expiration`
 * minutes.
 *
 * Callers are expected to throttle (`throttle:login`).
 */
final class LoginUser
{
    /**
     * Validation rules for the input, shared by the API and Livewire.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return AuthenticateCredentials::rules() + [
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function __construct(private readonly AuthenticateCredentials $authenticate) {}

    /**
     * @param  string  $identifier  Email address or phone number, in any of the accepted spellings.
     * @param  string  $deviceName  Label of the token, to recognise it later (for example "mobile").
     *
     * @throws InvalidCredentialsException Unknown or unverified identifier, pending account or wrong password (indistinguishable).
     * @throws AccountSuspendedException The credentials are right but the account is suspended.
     */
    public function handle(string $identifier, string $password, string $deviceName = 'api'): AuthToken
    {
        $user = $this->authenticate->handle($identifier, $password);

        return $this->issueToken($user, $deviceName);
    }

    private function issueToken(User $user, string $deviceName): AuthToken
    {
        $abilities = [TokenAbility::AccessApi->value];

        $expiresAt = now()->addMinutes((int) config('sanctum.expiration', 60 * 24 * 7));

        $token = $user->createToken($deviceName, $abilities, $expiresAt);

        return new AuthToken($user, $token->plainTextToken, $abilities, $expiresAt);
    }
}
