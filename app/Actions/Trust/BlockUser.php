<?php

namespace App\Actions\Trust;

use App\Actions\Matching\Concerns\ManagesParticipation;
use App\Enums\MatchStatus;
use App\Models\MobilityMatch;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Support\Facades\DB;

final class BlockUser
{
    use ManagesParticipation;

    public function handle(User $user, string $matchPublicId): UserBlock
    {
        return DB::transaction(function () use ($user, $matchPublicId): UserBlock {
            [, , $other] = $this->lockParticipation($user, $matchPublicId);

            $block = UserBlock::query()->firstOrCreate([
                'blocker_id' => $user->getKey(),
                'blocked_id' => $other->user_id,
            ]);

            $matches = MobilityMatch::query()
                ->whereIn('status', MatchStatus::reevaluable())
                ->whereHas('participants', fn ($query) => $query->where('user_id', $user->getKey()))
                ->whereHas('participants', fn ($query) => $query->where('user_id', $other->user_id))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($matches as $match) {
                $wasAccord = $match->status->isAccord();
                $match->forceFill([
                    'status' => MatchStatus::Invalidated,
                    'invalidated_at' => now(),
                    'invalidation_reason' => 'blocked',
                    'expires_at' => null,
                ])->save();

                if ($wasAccord) {
                    $this->agreements()->end($match);
                }
            }

            return $block;
        });
    }
}
