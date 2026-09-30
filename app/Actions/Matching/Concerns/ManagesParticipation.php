<?php

namespace App\Actions\Matching\Concerns;

use App\Models\MatchParticipant;
use App\Models\MobilityMatch;
use App\Models\User;
use App\Support\Matching\AgreementLifecycle;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Shared by the actions of the participants on a match. Call inside a transaction.
 */
trait ManagesParticipation
{
    /**
     * The actor's match, locked for update, with the actor's participant and the other one. A
     * match the actor does not take part in is reported exactly like an unknown one (404).
     *
     * @return array{MobilityMatch, MatchParticipant, MatchParticipant}
     *
     * @throws ModelNotFoundException
     */
    protected function lockParticipation(User $user, string $publicId): array
    {
        $match = MobilityMatch::query()
            ->involving($user)
            ->where('public_id', Str::lower($publicId))
            ->lockForUpdate()
            ->firstOrFail();

        $match->load('participants');
        Gate::forUser($user)->authorize('view', $match);

        $mine = $match->participantOf($user);
        $other = $match->counterpartOf($user);

        if ($mine === null || $other === null) {
            throw (new ModelNotFoundException)->setModel(MobilityMatch::class, [$publicId]);
        }

        return [$match, $mine, $other];
    }

    protected function agreements(): AgreementLifecycle
    {
        return app(AgreementLifecycle::class);
    }
}
