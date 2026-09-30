<?php

namespace App\Actions\Notifications;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;

/**
 * The account's own in-app notifications, newest first. Only the owner's notifications are ever
 * queried: there is nothing to authorize beyond the signed-in account.
 */
final class ListNotifications
{
    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return [
            'unread' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }

    /** @return LengthAwarePaginator<int, DatabaseNotification> */
    public function handle(User $user, bool $unreadOnly = false, int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        return $user->notifications()
            ->when($unreadOnly, fn ($query) => $query->whereNull('read_at'))
            ->reorder()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(min(max($perPage, 1), 100), ['*'], 'page', max($page, 1));
    }

    public function unreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }
}
