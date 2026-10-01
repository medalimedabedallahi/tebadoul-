<?php

namespace Tests\Feature\Jobs;

use App\Enums\NotificationType;
use App\Jobs\SendNotificationEmail;
use App\Models\User;
use App\Notifications\MatchActivityNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class SendNotificationEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_replayed_job_sends_the_email_once_in_the_recipient_language(): void
    {
        $user = User::factory()->create(['locale' => 'ar']);
        $notification = $this->notificationFor($user);
        Notification::fake();

        (new SendNotificationEmail($notification->id))->handle();
        (new SendNotificationEmail($notification->id))->handle();

        Notification::assertSentToTimes($user, MatchActivityNotification::class, 1);
        Notification::assertSentTo($user, MatchActivityNotification::class, fn (MatchActivityNotification $mail): bool => $mail->locale === 'ar');
        $this->assertNotNull($notification->fresh()->mailed_at);
    }

    public function test_nothing_is_sent_once_read_or_after_the_preference_is_turned_off(): void
    {
        $user = User::factory()->create();
        $read = $this->notificationFor($user);
        $read->markAsRead();
        $unread = $this->notificationFor($user);
        $user->forceFill(['email_notifications' => false])->save();
        Notification::fake();

        (new SendNotificationEmail($read->id))->handle();
        (new SendNotificationEmail($unread->id))->handle();

        Notification::assertNothingSent();
    }

    public function test_the_email_states_the_event_and_links_to_the_match_without_naming_anyone(): void
    {
        $user = User::factory()->create(['name' => 'Aminetou Sy']);

        $mail = (new MatchActivityNotification(NotificationType::InvitationReceived, '01jzmatch0000000000000000'))->toMail($user);

        $this->assertSame('Tebadoul : Vous avez reçu une invitation sur une correspondance.', $mail->subject);
        $this->assertSame(route('matches.show', '01jzmatch0000000000000000'), $mail->actionUrl);
        $this->assertStringNotContainsString('Aminetou', (string) $mail->render());
    }

    private function notificationFor(User $user): DatabaseNotification
    {
        /** @var DatabaseNotification */
        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => NotificationType::InvitationReceived->value,
            'data' => ['match_public_id' => '01jzmatch0000000000000000'],
        ]);
    }
}
