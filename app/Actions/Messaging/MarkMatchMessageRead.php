<?php

namespace App\Actions\Messaging;

use App\Actions\Matching\Concerns\ManagesParticipation;
use App\Models\MatchMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class MarkMatchMessageRead
{
    use ManagesParticipation;

    public function handle(User $user, string $matchPublicId, string $messagePublicId): MatchMessage
    {
        return DB::transaction(function () use ($user, $matchPublicId, $messagePublicId): MatchMessage {
            [$match] = $this->lockParticipation($user, $matchPublicId);
            $message = MatchMessage::query()
                ->where('match_id', $match->getKey())
                ->where('sender_id', '!=', $user->getKey())
                ->where('public_id', Str::lower($messagePublicId))
                ->lockForUpdate()
                ->firstOrFail();

            if ($message->read_at === null) {
                $message->forceFill(['read_at' => now()])->save();
            }

            return $message;
        });
    }
}
