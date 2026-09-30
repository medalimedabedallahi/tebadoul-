<?php

namespace App\Actions\Matching;

use App\Actions\Matching\Concerns\ManagesParticipation;
use App\Enums\ContactEventAction;
use App\Enums\NotificationType;
use App\Exceptions\Matching\ContactNotAuthorizedException;
use App\Exceptions\Matching\InvalidMatchTransitionException;
use App\Models\ContactEvent;
use App\Models\MobilityMatch;
use App\Models\User;
use App\Support\Notifications\UserNotifier;
use App\Support\Trust\BlockGuard;
use Illuminate\Support\Facades\DB;

/**
 * Contact sharing on an agreement in progress (ADR 0001, decisions 5 and 6).
 *
 * Each participant grants or revokes their own consent explicitly; every grant, revocation and
 * reveal is time-stamped in `contact_events`. The other participant's verified contact details
 * are revealed only while both consent. Revoking ends future access (what was already seen
 * cannot be taken back). Ending the agreement revokes every consent.
 */
final class ShareContact
{
    use ManagesParticipation;

    public function __construct(
        private readonly BlockGuard $blockGuard,
        private readonly UserNotifier $notifier,
    ) {}

    /**
     * Idempotent: granting an existing consent changes nothing and records nothing.
     *
     * @throws InvalidMatchTransitionException
     */
    public function grant(User $user, string $publicId, ?string $ipAddress = null): MobilityMatch
    {
        return DB::transaction(function () use ($user, $publicId, $ipAddress): MobilityMatch {
            [$match, $mine, $other] = $this->lockParticipation($user, $publicId);
            $this->blockGuard->ensureInteractionAllowed($user, $other->user);

            if (! $match->status->isAccord()) {
                throw new InvalidMatchTransitionException($match->status, 'share_contact');
            }

            if ($mine->contact_consented_at === null) {
                $mine->forceFill(['contact_consented_at' => now()])->save();
                ContactEvent::record($match, $user, ContactEventAction::Granted, $ipAddress);
                $this->notifier->notify($other->user, NotificationType::ContactConsentGranted, $match);
            }

            return $match;
        });
    }

    /**
     * Allowed in any status; revoking an absent consent changes nothing and records nothing.
     */
    public function revoke(User $user, string $publicId, ?string $ipAddress = null): MobilityMatch
    {
        return DB::transaction(function () use ($user, $publicId, $ipAddress): MobilityMatch {
            [$match, $mine] = $this->lockParticipation($user, $publicId);

            if ($mine->contact_consented_at !== null) {
                $mine->forceFill(['contact_consented_at' => null])->save();
                ContactEvent::record($match, $user, ContactEventAction::Revoked, $ipAddress);
            }

            return $match;
        });
    }

    /**
     * The other participant's verified contact details, recorded as viewed.
     *
     * @return array{email: string|null, phone: string|null}
     *
     * @throws ContactNotAuthorizedException
     */
    public function reveal(User $user, string $publicId, ?string $ipAddress = null): array
    {
        return DB::transaction(function () use ($user, $publicId, $ipAddress): array {
            [$match, $mine, $other] = $this->lockParticipation($user, $publicId);
            $this->blockGuard->ensureInteractionAllowed($user, $other->user);

            if (! $match->status->isAccord() || $mine->contact_consented_at === null || $other->contact_consented_at === null) {
                throw new ContactNotAuthorizedException;
            }

            ContactEvent::record($match, $user, ContactEventAction::Viewed, $ipAddress);
            $counterpart = $other->user;

            return [
                'email' => $counterpart->email_verified_at !== null ? $counterpart->email : null,
                'phone' => $counterpart->phone_verified_at !== null ? $counterpart->phone : null,
            ];
        });
    }
}
