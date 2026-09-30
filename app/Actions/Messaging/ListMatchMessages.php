<?php

namespace App\Actions\Messaging;

use App\Actions\Matching\ListMatches;
use App\Models\MatchMessage;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListMatchMessages
{
    public function __construct(private readonly ListMatches $matches) {}

    /** @return LengthAwarePaginator<int, MatchMessage> */
    public function handle(User $user, string $matchPublicId, int $page = 1, int $perPage = 50): LengthAwarePaginator
    {
        $match = $this->matches->find($user, $matchPublicId);

        return MatchMessage::query()
            ->where('match_id', $match->getKey())
            ->orderByDesc('id')
            ->paginate(min(max($perPage, 1), 100), ['*'], 'page', max($page, 1));
    }
}
