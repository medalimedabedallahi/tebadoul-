<?php

namespace Tests\Feature\Actions\MobilityRequests;

use App\Actions\MobilityRequests\CloseMobilityRequest;
use App\Actions\MobilityRequests\DeleteMobilityRequest;
use App\Actions\MobilityRequests\ExpireMobilityRequests;
use App\Actions\MobilityRequests\PauseMobilityRequest;
use App\Actions\MobilityRequests\PublishMobilityRequest;
use App\Actions\MobilityRequests\RenewMobilityRequest;
use App\Actions\MobilityRequests\SaveMobilityRequest;
use App\Actions\Profiles\SaveProfessionalProfile;
use App\Enums\MobilityRequestStatus;
use App\Enums\RequestCloseReason;
use App\Exceptions\MobilityRequests\InvalidRequestTransitionException;
use App\Exceptions\MobilityRequests\RequestNotPublishableException;
use App\Models\MobilityRequest;
use App\Models\User;
use App\Models\Wilaya;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesReferenceData;
use Tests\TestCase;

class MobilityRequestLifecycleTest extends TestCase
{
    use CreatesReferenceData;
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-01 10:00:00');
        $this->createReferenceData();
        $this->user = User::factory()->create();
        app(SaveProfessionalProfile::class)->handle($this->user, $this->teacherProfileInput());
    }

    public function test_publishes_pauses_resumes_then_closes_a_request(): void
    {
        $request = $this->draft();

        $published = app(PublishMobilityRequest::class)->handle($this->user, $request->public_id);
        $this->assertSame(MobilityRequestStatus::Published, $published->status);
        $this->assertSame('2026-10-01 10:00:00', $published->published_at?->toDateTimeString());
        $this->assertSame(['update', 'pause', 'close'], $published->allowedActions());

        $this->travel(1)->days();
        $paused = app(PauseMobilityRequest::class)->handle($this->user, $request->public_id);
        $this->assertSame(MobilityRequestStatus::Paused, $paused->status);

        $resumed = app(PublishMobilityRequest::class)->handle($this->user, $request->public_id);
        $this->assertSame(MobilityRequestStatus::Published, $resumed->status);
        $this->assertSame('2026-10-01 10:00:00', $resumed->published_at?->toDateTimeString(), 'The first publication date is kept.');

        $closed = app(CloseMobilityRequest::class)->handle($this->user, $request->public_id, RequestCloseReason::PermutationCompleted);
        $this->assertSame(MobilityRequestStatus::Closed, $closed->status);
        $this->assertSame(RequestCloseReason::PermutationCompleted, $closed->close_reason);
        $this->assertNotNull($closed->closed_at);
        $this->assertSame(['delete'], $closed->allowedActions());
    }

    public function test_publication_reports_every_missing_requirement(): void
    {
        $withoutProfile = User::factory()->create();
        $request = MobilityRequest::factory()->for($withoutProfile)->create(['expires_at' => '2026-09-30']);

        try {
            app(PublishMobilityRequest::class)->handle($withoutProfile, $request->public_id);
            $this->fail('The request must not be publishable.');
        } catch (RequestNotPublishableException $exception) {
            $this->assertEqualsCanonicalizing(['profile', 'destinations', 'expires_at'], array_keys($exception->errors));
        }

        $this->assertSame(MobilityRequestStatus::Draft, $request->fresh()->status);
    }

    public function test_only_one_request_of_an_account_can_be_active(): void
    {
        $first = $this->draft();
        $second = $this->draft();
        app(PublishMobilityRequest::class)->handle($this->user, $first->public_id);
        app(PauseMobilityRequest::class)->handle($this->user, $first->public_id);

        try {
            app(PublishMobilityRequest::class)->handle($this->user, $second->public_id);
            $this->fail('A paused request still counts as the active one.');
        } catch (RequestNotPublishableException $exception) {
            $this->assertSame(['status'], array_keys($exception->errors));
        }

        app(CloseMobilityRequest::class)->handle($this->user, $first->public_id, RequestCloseReason::Other);
        $this->assertSame(MobilityRequestStatus::Published, app(PublishMobilityRequest::class)->handle($this->user, $second->public_id)->status);
    }

    public function test_publication_checks_the_destinations_again(): void
    {
        $request = $this->draft();
        Wilaya::query()->where('code', '01')->update(['active' => false]);

        try {
            app(PublishMobilityRequest::class)->handle($this->user, $request->public_id);
            $this->fail('A deactivated destination must block the publication.');
        } catch (RequestNotPublishableException $exception) {
            $this->assertSame(['destinations'], array_keys($exception->errors));
        }
    }

    public function test_expires_overdue_requests_every_hour_and_renews_them(): void
    {
        $request = $this->draft();
        app(PublishMobilityRequest::class)->handle($this->user, $request->public_id);
        $pausedElsewhere = MobilityRequest::factory()->status(MobilityRequestStatus::Paused)->create(['expires_at' => '2027-04-15']);
        $draft = MobilityRequest::factory()->create(['expires_at' => '2027-04-15']);

        $this->travelTo('2027-04-15 23:00:00');
        $this->assertSame(0, app(ExpireMobilityRequests::class)->handle(), 'Valid through its expiry date.');

        $this->travelTo('2027-04-16 00:30:00');
        $this->artisan('requests:expire')->expectsOutput('2 mobility request(s) expired.')->assertSuccessful();
        $this->assertSame(MobilityRequestStatus::Expired, $request->fresh()->status);
        $this->assertSame(MobilityRequestStatus::Expired, $pausedElsewhere->fresh()->status);
        $this->assertSame(MobilityRequestStatus::Draft, $draft->fresh()->status);
        $this->assertSame(['update', 'renew', 'close', 'delete'], $request->fresh()->allowedActions());

        $renewed = app(RenewMobilityRequest::class)->handle($this->user, $request->public_id, '2027-10-01');
        $this->assertSame(MobilityRequestStatus::Published, $renewed->status);
        $this->assertSame('2027-10-01', $renewed->expires_at->toDateString());
    }

    /**
     * @param  callable(User, string): mixed  $operation
     */
    #[DataProvider('forbiddenTransitions')]
    public function test_refuses_operations_the_status_does_not_allow(MobilityRequestStatus $status, callable $operation): void
    {
        $request = MobilityRequest::factory()->for($this->user)->status($status)->create();

        try {
            $operation($this->user, $request->public_id);
            $this->fail('The operation must be refused.');
        } catch (InvalidRequestTransitionException) {
            // Expected.
        }

        $this->assertSame($status, $request->fresh()->status);
        $this->assertNotSoftDeleted($request);
    }

    /**
     * @return array<string, array{MobilityRequestStatus, callable(User, string): mixed}>
     */
    public static function forbiddenTransitions(): array
    {
        return [
            'pause a draft' => [MobilityRequestStatus::Draft, fn (User $user, string $id) => app(PauseMobilityRequest::class)->handle($user, $id)],
            'close a draft' => [MobilityRequestStatus::Draft, fn (User $user, string $id) => app(CloseMobilityRequest::class)->handle($user, $id, RequestCloseReason::Other)],
            'renew a draft' => [MobilityRequestStatus::Draft, fn (User $user, string $id) => app(RenewMobilityRequest::class)->handle($user, $id, '2027-01-01')],
            'publish an expired request' => [MobilityRequestStatus::Expired, fn (User $user, string $id) => app(PublishMobilityRequest::class)->handle($user, $id)],
            'publish a matched request' => [MobilityRequestStatus::Matched, fn (User $user, string $id) => app(PublishMobilityRequest::class)->handle($user, $id)],
            'close a closed request' => [MobilityRequestStatus::Closed, fn (User $user, string $id) => app(CloseMobilityRequest::class)->handle($user, $id, RequestCloseReason::Other)],
            'delete a published request' => [MobilityRequestStatus::Published, fn (User $user, string $id) => app(DeleteMobilityRequest::class)->handle($user, $id)],
            'delete a matched request' => [MobilityRequestStatus::Matched, fn (User $user, string $id) => app(DeleteMobilityRequest::class)->handle($user, $id)],
        ];
    }

    public function test_deletes_a_draft_softly_and_hides_it_from_its_owner(): void
    {
        $request = $this->draft();

        app(DeleteMobilityRequest::class)->handle($this->user, $request->public_id);

        $this->assertSoftDeleted($request);
        $this->assertSame(0, $this->user->mobilityRequests()->count());
    }

    private function draft(): MobilityRequest
    {
        return app(SaveMobilityRequest::class)->handle($this->user, [
            'available_from' => '2026-10-15',
            'expires_at' => '2027-04-15',
            'destinations' => [['wilaya' => '01']],
        ]);
    }
}
