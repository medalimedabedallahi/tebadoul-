<?php

namespace App\Support\Matching;

use App\Enums\MobilityRequestStatus;
use App\Enums\UserStatus;
use App\Models\MobilityRequest;
use App\Models\ProfessionalProfile;
use App\Models\RequestDestination;

/**
 * A mobility request as the matching rules see it: the request, its destinations and the
 * owner's professional profile (origin and professional context).
 */
final readonly class Candidate
{
    /**
     * @param  bool  $agreement  True when evaluated for the agreement in progress it takes part in:
     *                           a `matched` request then counts as available.
     */
    public function __construct(
        public MobilityRequest $request,
        public ?ProfessionalProfile $profile,
        public bool $agreement = false,
    ) {}

    /**
     * The same request, evaluated for its own agreement in progress.
     */
    public function inAccord(): self
    {
        return new self($this->request, $this->profile, true);
    }

    /**
     * Expects `destinations` and `user.professionalProfile` to be loadable (they are loaded when
     * missing).
     */
    public static function fromRequest(MobilityRequest $request): self
    {
        $request->loadMissing(['destinations', 'user.professionalProfile']);

        return new self($request, $request->user->professionalProfile);
    }

    /**
     * Published and not expired, or `matched` for its own agreement; not deleted; owned by an
     * active account with a profile.
     */
    public function isAvailable(): bool
    {
        return $this->profile !== null
            && ! $this->request->trashed()
            && ($this->request->status === MobilityRequestStatus::Published
                || ($this->agreement && $this->request->status === MobilityRequestStatus::Matched))
            // An agreement in progress outlives the expiry date set when the request was published.
            && ($this->agreement || ! $this->request->expires_at->isBefore(today()))
            && $this->request->user->status === UserStatus::Active;
    }

    /**
     * The best (lowest priority) destination of this request covering `$other`'s current
     * assignment, or null. A destination covers an assignment when the wilaya is the same and
     * the moughataa and establishment, when given, are the same too.
     */
    public function destinationCovering(self $other): ?RequestDestination
    {
        $origin = $other->profile;

        if ($origin === null) {
            return null;
        }

        $origin->loadMissing('moughataa');

        return $this->request->destinations->first(
            fn (RequestDestination $destination): bool => $destination->wilaya_id === $origin->moughataa->wilaya_id
                && ($destination->moughataa_id === null || $destination->moughataa_id === $origin->moughataa_id)
                && ($destination->establishment_id === null || $destination->establishment_id === $origin->establishment_id),
        );
    }
}
