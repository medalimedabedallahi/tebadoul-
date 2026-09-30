<?php

namespace App\Actions\Messaging;

use App\Actions\Matching\Concerns\ManagesParticipation;
use App\Enums\NotificationType;
use App\Exceptions\Matching\InvalidMatchTransitionException;
use App\Models\MatchMessage;
use App\Models\User;
use App\Support\Notifications\UserNotifier;
use App\Support\Trust\BlockGuard;
use Illuminate\Support\Facades\DB;

final class SendMatchMessage
{
    use ManagesParticipation;

    public function __construct(
        private readonly BlockGuard $blockGuard,
        private readonly UserNotifier $notifier,
    ) {}

    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return ['body' => ['required', 'string', 'max:2000']];
    }

    public function handle(User $user, string $matchPublicId, string $body): MatchMessage
    {
        return DB::transaction(function () use ($user, $matchPublicId, $body): MatchMessage {
            [$match, , $other] = $this->lockParticipation($user, $matchPublicId);
            $this->blockGuard->ensureInteractionAllowed($user, $other->user);

            if (! $match->status->isAccord()) {
                throw new InvalidMatchTransitionException($match->status, 'send_message');
            }

            $message = new MatchMessage;
            $message->forceFill([
                'match_id' => $match->getKey(),
                'sender_id' => $user->getKey(),
                'body' => trim($body),
            ])->save();

            $this->notifier->notify($other->user, NotificationType::MessageReceived, $match);

            return $message;
        });
    }
}
