<?php

namespace Tests\Feature\Actions\Notifications;

use App\Actions\Notifications\ListNotifications;
use App\Actions\Notifications\MarkAllNotificationsRead;
use App\Actions\Notifications\MarkNotificationRead;
use App\Actions\Notifications\UpdateNotificationPreferences;
use App\Enums\NotificationType;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_list_holds_only_the_account_notifications_newest_first_and_can_filter_unread(): void
    {
        $user = User::factory()->create();
        $this->travelTo(now()->subHour());
        $older = $this->notificationFor($user);
        $older->markAsRead();
        $this->travelBack();
        $newer = $this->notificationFor($user);
        $this->notificationFor(User::factory()->create());

        $all = app(ListNotifications::class)->handle($user);
        $unread = app(ListNotifications::class)->handle($user, unreadOnly: true);

        $this->assertSame([$newer->id, $older->id], $all->pluck('id')->all());
        $this->assertSame([$newer->id], $unread->pluck('id')->all());
        $this->assertSame(1, app(ListNotifications::class)->unreadCount($user));
    }

    public function test_marking_read_keeps_the_first_read_date(): void
    {
        $user = User::factory()->create();
        $notification = $this->notificationFor($user);
        $this->freezeTime();

        app(MarkNotificationRead::class)->handle($user, $notification->id);
        $firstRead = $notification->fresh()->read_at;
        $this->travel(5)->minutes();
        app(MarkNotificationRead::class)->handle($user, strtoupper($notification->id));

        $this->assertNotNull($firstRead);
        $this->assertTrue($firstRead->equalTo($notification->fresh()->read_at));
    }

    public function test_another_account_notification_is_reported_as_not_found(): void
    {
        $notification = $this->notificationFor(User::factory()->create());

        $this->expectException(ModelNotFoundException::class);
        app(MarkNotificationRead::class)->handle(User::factory()->create(), $notification->id);
    }

    public function test_mark_all_read_touches_only_the_account_unread_notifications(): void
    {
        $user = User::factory()->create();
        $this->notificationFor($user);
        $this->notificationFor($user);
        $other = $this->notificationFor(User::factory()->create());

        $this->assertSame(2, app(MarkAllNotificationsRead::class)->handle($user));
        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertNull($other->fresh()->read_at);
    }

    public function test_preferences_change_the_email_opt_in_and_language(): void
    {
        $user = User::factory()->create(['locale' => 'fr']);

        app(UpdateNotificationPreferences::class)->handle($user, ['email_notifications' => false, 'locale' => 'ar']);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email_notifications' => false, 'locale' => 'ar']);
    }

    private function notificationFor(User $user): DatabaseNotification
    {
        /** @var DatabaseNotification */
        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => NotificationType::MatchSuggested->value,
            'data' => ['match_public_id' => '01jzmatch0000000000000000'],
        ]);
    }
}
