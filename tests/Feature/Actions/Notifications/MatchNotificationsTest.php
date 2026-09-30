<?php

namespace Tests\Feature\Actions\Notifications;

use App\Actions\Auth\SuspendUser;
use App\Actions\Matching\AcceptMatch;
use App\Actions\Matching\ComputeDirectMatches;
use App\Actions\Matching\DeclineMatch;
use App\Actions\Matching\InviteToMatch;
use App\Actions\Matching\ShareContact;
use App\Actions\Matching\WithdrawFromMatch;
use App\Actions\Messaging\SendMatchMessage;
use App\Enums\NotificationType;
use App\Models\MobilityMatch;
use App\Models\MobilityRequest;
use App\Models\User;
use App\Notifications\MatchActivityNotification;
use App\Support\Notifications\UserNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Support\CreatesMatchedPair;
use Tests\TestCase;

class MatchNotificationsTest extends TestCase
{
    use CreatesMatchedPair;
    use RefreshDatabase;

    public function test_a_new_suggestion_notifies_both_participants_once_with_the_match_id_only(): void
    {
        Notification::fake();

        [$rosso, $hodh, $match] = $this->createMatchedPair();
        app(ComputeDirectMatches::class)->handle(MobilityRequest::query()->where('user_id', $rosso->id)->sole());

        foreach ([$rosso, $hodh] as $participant) {
            $notification = $participant->notifications()->sole();
            $this->assertSame(NotificationType::MatchSuggested->value, $notification->type);
            $this->assertSame(['match_public_id' => $match->public_id], $notification->data);
            $this->assertNull($notification->read_at);
        }

        Notification::assertSentToTimes($rosso, MatchActivityNotification::class, 1);
    }

    public function test_invitation_and_acceptance_notify_the_other_participant_by_email_too(): void
    {
        [$rosso, $hodh, $match] = $this->createMatchedPair();
        Notification::fake();

        app(InviteToMatch::class)->handle($rosso, $match->public_id);
        app(AcceptMatch::class)->handle($hodh, $match->public_id);

        $this->assertSame(1, $this->notificationsOf($hodh, NotificationType::InvitationReceived));
        $this->assertSame(1, $this->notificationsOf($rosso, NotificationType::MatchAccepted));
        $this->assertSame(0, $this->notificationsOf($rosso, NotificationType::InvitationReceived));
        Notification::assertSentTo(
            $hodh,
            MatchActivityNotification::class,
            fn (MatchActivityNotification $mail): bool => $mail->type === NotificationType::InvitationReceived && $mail->matchPublicId === $match->public_id,
        );
    }

    public function test_messages_collapse_into_one_unread_notification_and_are_never_emailed(): void
    {
        [$rosso, $hodh, $match] = $this->agreement();
        Notification::fake();

        app(SendMatchMessage::class)->handle($rosso, $match->public_id, 'Bonjour');
        app(SendMatchMessage::class)->handle($rosso, $match->public_id, 'Êtes-vous disponible ?');

        $this->assertSame(1, $this->notificationsOf($hodh, NotificationType::MessageReceived));
        Notification::assertNothingSent();

        $hodh->unreadNotifications()->update(['read_at' => now()]);
        app(SendMatchMessage::class)->handle($rosso, $match->public_id, 'Nouvelle question');

        $this->assertSame(2, $this->notificationsOf($hodh, NotificationType::MessageReceived));
    }

    public function test_dismissing_a_suggestion_notifies_nobody_but_refusing_an_invitation_notifies_the_inviter(): void
    {
        [$rosso, $hodh, $match] = $this->createMatchedPair();
        app(DeclineMatch::class)->handle($hodh, $match->public_id);

        $this->assertSame(0, $this->notificationsOf($rosso, NotificationType::InvitationDeclined));

        [$first, $second, $invited] = $this->secondPair();
        app(InviteToMatch::class)->handle($first, $invited->public_id);
        app(DeclineMatch::class)->handle($second, $invited->public_id);

        $this->assertSame(1, $this->notificationsOf($first, NotificationType::InvitationDeclined));
    }

    public function test_withdrawal_and_contact_consent_notify_the_other_participant(): void
    {
        [$rosso, $hodh, $match] = $this->agreement();

        app(ShareContact::class)->grant($rosso, $match->public_id);
        app(ShareContact::class)->grant($rosso, $match->public_id);
        app(WithdrawFromMatch::class)->handle($hodh, $match->public_id);

        $this->assertSame(1, $this->notificationsOf($hodh, NotificationType::ContactConsentGranted));
        $this->assertSame(1, $this->notificationsOf($rosso, NotificationType::MatchWithdrawn));
    }

    public function test_no_email_without_the_preference_or_a_verified_address(): void
    {
        [$rosso, $hodh, $match] = $this->createMatchedPair();
        $hodh->forceFill(['email_notifications' => false])->save();
        Notification::fake();

        app(InviteToMatch::class)->handle($rosso, $match->public_id);

        $this->assertSame(1, $this->notificationsOf($hodh, NotificationType::InvitationReceived));
        Notification::assertNothingSentTo($hodh);

        $rosso->forceFill(['email_verified_at' => null, 'phone' => '+22241234567', 'phone_verified_at' => now()])->save();
        app(AcceptMatch::class)->handle($hodh, $match->public_id);

        $this->assertSame(1, $this->notificationsOf($rosso, NotificationType::MatchAccepted));
        Notification::assertNothingSentTo($rosso);
    }

    public function test_a_suspended_account_is_not_notified(): void
    {
        [$rosso, $hodh, $match] = $this->createMatchedPair();
        app(SuspendUser::class)->handle(User::factory()->administrator()->create(), $hodh->public_id, 'Fraude avérée.');
        Notification::fake();

        app(UserNotifier::class)->notify($hodh->fresh(), NotificationType::InvitationReceived, $match);

        $this->assertSame(0, $this->notificationsOf($hodh, NotificationType::InvitationReceived));
        Notification::assertNothingSent();
    }

    /**
     * @return array{User, User, MobilityMatch}
     */
    private function agreement(): array
    {
        [$rosso, $hodh, $match] = $this->createMatchedPair();
        app(InviteToMatch::class)->handle($rosso, $match->public_id);
        app(AcceptMatch::class)->handle($hodh, $match->public_id);

        return [$rosso, $hodh, $match];
    }

    /**
     * A second, independent pair of teachers (wilaya 01 and 0606 again, with other accounts).
     *
     * @return array{User, User, MobilityMatch}
     */
    private function secondPair(): array
    {
        $first = $this->publishedTeacher([], [['wilaya' => '01']]);
        $second = $this->publishedTeacher(['wilaya' => '01', 'moughataa' => '0101', 'establishment' => null], [['wilaya' => '06', 'moughataa' => '0606']]);

        $match = MobilityMatch::query()->involving($first)->involving($second)->sole();

        return [$first, $second, $match];
    }

    private function notificationsOf(User $user, NotificationType $type): int
    {
        return $user->notifications()->where('type', $type->value)->count();
    }
}
