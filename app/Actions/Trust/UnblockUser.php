<?php

namespace App\Actions\Trust;

use App\Actions\Matching\Concerns\ManagesParticipation;
use App\Jobs\RecomputeMatches;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Support\Facades\DB;

final class UnblockUser
{
    use ManagesParticipation;

    public function handle(User $user, string $matchPublicId): void
    {
        DB::transaction(function () use ($user, $matchPublicId): void {
            [, , $other] = $this->lockParticipation($user, $matchPublicId);

            UserBlock::query()
                ->where('blocker_id', $user->getKey())
                ->where('blocked_id', $other->user_id)
                ->delete();

            RecomputeMatches::forUser($user);
            RecomputeMatches::forUser($other->user);
        });
    }
}
