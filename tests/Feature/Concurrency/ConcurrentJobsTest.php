<?php

namespace Tests\Feature\Concurrency;

use App\Actions\Matching\ComputeDirectMatches;
use App\Contracts\ContactCodeSender;
use App\Enums\NotificationType;
use App\Jobs\SendContactCode;
use App\Jobs\SendNotificationEmail;
use App\Models\ContactVerification;
use App\Models\MatchParticipant;
use App\Models\MobilityMatch;
use App\Models\MobilityRequest;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Support\CreatesMatchedPair;
use Tests\Support\RunsConcurrently;
use Tests\TestCase;

/**
 * Pilot gate "Les jobs sont idempotents et testes sous concurrence": the same job run by several
 * workers at the same instant, as a redelivered or double-dispatched job would be, has its effect
 * exactly once. Each worker fakes its own outgoing messages and reports how many it sent.
 */
class ConcurrentJobsTest extends TestCase
{
    use CreatesMatchedPair;
    use RunsConcurrently;

    public function test_racing_workers_send_a_contact_code_once(): void
    {
        $this->requireSharedDatabase();
        $verificationId = ContactVerification::factory()->create(['contact' => 'race@example.org'])->getKey();

        $sent = $this->runConcurrently(static function () use ($verificationId): int {
            Notification::fake();
            (new SendContactCode($verificationId, '123456'))->handle(app(ContactCodeSender::class));

            return self::sentCount();
        }, processes: 6);

        $this->assertSame(1, array_sum($sent));
        $this->assertNotNull(ContactVerification::query()->findOrFail($verificationId)->last_sent_at);
    }

    public function test_racing_workers_send_a_notification_email_once(): void
    {
        $this->requireSharedDatabase();
        $user = User::factory()->create();
        $notificationId = (string) $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => NotificationType::InvitationReceived->value,
            'data' => ['match_public_id' => '01jzmatch0000000000000000'],
        ])->getKey();

        $sent = $this->runConcurrently(static function () use ($notificationId): int {
            Notification::fake();
            (new SendNotificationEmail($notificationId))->handle();

            return self::sentCount();
        }, processes: 6);

        $this->assertSame(1, array_sum($sent));
    }

    public function test_racing_recomputations_create_one_match_and_notify_each_participant_once(): void
    {
        $this->requireSharedDatabase();
        // Published while matching is off: no match exists yet, the workers all race to create it.
        $this->createReferenceData();
        $rosso = $this->publishedTeacher([], [['wilaya' => '01']]);
        $hodh = $this->publishedTeacher(['wilaya' => '01', 'moughataa' => '0101', 'establishment' => null], [['wilaya' => '06', 'moughataa' => '0606']]);
        $requestIds = [
            MobilityRequest::query()->where('user_id', $rosso->id)->sole()->id,
            MobilityRequest::query()->where('user_id', $hodh->id)->sole()->id,
        ];

        $this->assertSame(0, MobilityMatch::query()->count());

        $results = $this->runConcurrently(static function (int $worker) use ($requestIds): array {
            $request = MobilityRequest::query()->findOrFail($requestIds[$worker % 2]);

            return app(ComputeDirectMatches::class)->handle($request);
        }, processes: 6, environment: ['MATCHING_ENABLED' => 'true']);

        $this->assertSame(1, MobilityMatch::query()->count());
        $this->assertSame(2, MatchParticipant::query()->count());
        $this->assertSame(1, array_sum(array_column($results, 'suggested')));

        foreach ([$rosso, $hodh] as $participant) {
            $this->assertSame(1, $participant->notifications()->where('type', NotificationType::MatchSuggested->value)->count());
        }
    }

    /**
     * Notifications the worker's fake recorded, whatever their recipient or channel.
     */
    private static function sentCount(): int
    {
        $count = 0;

        foreach (Notification::sentNotifications() as $byNotifiable) {
            foreach ($byNotifiable as $byNotification) {
                foreach ($byNotification as $sends) {
                    $count += count($sends);
                }
            }
        }

        return $count;
    }
}
