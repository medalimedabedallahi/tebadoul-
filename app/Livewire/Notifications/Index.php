<?php

namespace App\Livewire\Notifications;

use App\Actions\Notifications\ListNotifications;
use App\Actions\Notifications\MarkAllNotificationsRead;
use App\Actions\Notifications\MarkNotificationRead;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The account's in-app notifications, calling the same actions as `/api/v1/notifications`.
 */
class Index extends Component
{
    use WithPagination;

    #[Url(except: 'all')]
    public string $filter = 'all';

    public ?string $allMarked = null;

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function markRead(string $notification, MarkNotificationRead $markRead): void
    {
        try {
            $markRead->handle($this->currentUser(), $notification);
        } catch (ModelNotFoundException) {
            // Already gone or not the account's own: nothing to mark.
        }
    }

    /**
     * Marks the notification as read, then opens the match it is about.
     */
    public function open(string $notification, MarkNotificationRead $markRead): void
    {
        try {
            $read = $markRead->handle($this->currentUser(), $notification);
        } catch (ModelNotFoundException) {
            return;
        }

        $matchPublicId = $read->data['match_public_id'] ?? null;

        if (is_string($matchPublicId)) {
            $this->redirect(route('matches.show', $matchPublicId));
        }
    }

    public function markAllRead(MarkAllNotificationsRead $markAllRead): void
    {
        $markAllRead->handle($this->currentUser());
        $this->allMarked = __('notifications.all_marked');
    }

    public function render(ListNotifications $notifications): View
    {
        $user = $this->currentUser();

        return view('livewire.notifications.index', [
            'notifications' => $notifications->handle($user, $this->filter === 'unread', $this->getPage(), 20),
            'unreadCount' => $notifications->unreadCount($user),
            'filters' => [
                'all' => __('notifications.filters.all'),
                'unread' => __('notifications.filters.unread'),
            ],
        ])->layout('components.layouts.app', [
            'title' => __('notifications.title'),
            'description' => __('notifications.description'),
        ]);
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
