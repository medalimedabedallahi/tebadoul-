<?php

namespace Tests\Feature\Actions\Matching;

use App\Actions\Auth\SuspendUser;
use App\Actions\Matching\AcceptMatch;
use App\Actions\Matching\ComputeDirectMatches;
use App\Actions\Matching\InviteToMatch;
use App\Actions\Matching\ShareContact;
use App\Actions\Matching\WithdrawFromMatch;
use App\Enums\MatchStatus;
use App\Enums\MobilityRequestStatus;
use App\Models\MobilityMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMatchedPair;
use Tests\TestCase;

/**
 * How agreements and the matching engine interact: one agreement at a time per request, an
 * agreement survives recomputations, and an agreement that stops holding releases everything.
 */
class AgreementLifecycleTest extends TestCase
{
    use CreatesMatchedPair;
    use RefreshDatabase;

    private User $rosso;

    private User $hodh;

    private MobilityMatch $agreement;

    private MobilityMatch $competing;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->rosso, $this->hodh, $this->agreement] = $this->createMatchedPair();

        // A third teacher in 0101 who also wants Rosso: a second match for the Rosso request.
        $third = $this->publishedTeacher(['wilaya' => '01', 'moughataa' => '0101', 'establishment' => null], [['wilaya' => '06']]);
        $this->competing = MobilityMatch::query()->involving($third)->sole();
    }

    public function test_accepting_withdraws_the_other_matches_of_both_requests(): void
    {
        $this->agree();

        $this->assertSame(MatchStatus::Mutual, $this->agreement->refresh()->status);
        $this->assertSame(MobilityRequestStatus::Matched, $this->rosso->mobilityRequests()->sole()->status);
        $this->assertSame(MatchStatus::Invalidated, $this->competing->refresh()->status);
        $this->assertSame('request_unavailable', $this->competing->invalidation_reason);
    }

    public function test_an_agreement_survives_recomputations_and_the_expiry_date(): void
    {
        $this->agree();

        $this->artisan('matching:recompute')->assertSuccessful();
        $this->assertSame(MatchStatus::Mutual, $this->agreement->refresh()->status);

        $this->travelTo(today()->addYear());
        $this->artisan('requests:expire')->assertSuccessful();
        app(ComputeDirectMatches::class)->handle($this->rosso->mobilityRequests()->sole());

        $this->assertSame(MatchStatus::Mutual, $this->agreement->refresh()->status);
        $this->assertSame(MobilityRequestStatus::Matched, $this->hodh->mobilityRequests()->sole()->status);
    }

    public function test_an_agreement_that_stops_holding_releases_the_requests_and_revokes_consents(): void
    {
        $this->agree();
        app(ShareContact::class)->grant($this->rosso, $this->agreement->public_id);
        app(ShareContact::class)->grant($this->hodh, $this->agreement->public_id);

        app(SuspendUser::class)->handle(User::factory()->administrator()->create(), $this->hodh->public_id, 'Abuse.');

        $this->assertSame(MatchStatus::Invalidated, $this->agreement->refresh()->status);
        $this->assertSame('request_unavailable', $this->agreement->invalidation_reason);
        $this->assertSame(MobilityRequestStatus::Published, $this->rosso->mobilityRequests()->sole()->status);
        $this->assertSame(0, $this->agreement->participants()->whereNotNull('contact_consented_at')->count());
        $this->assertDatabaseCount('contact_events', 4);
        $this->assertDatabaseHas('contact_events', ['match_id' => $this->agreement->id, 'user_id' => $this->rosso->id, 'action' => 'revoked']);
        $this->assertSame(MatchStatus::Suggested, $this->competing->refresh()->status, 'The released request is matched again.');
    }

    public function test_a_withdrawn_agreement_brings_the_other_matches_back(): void
    {
        $this->agree();

        app(WithdrawFromMatch::class)->handle($this->hodh, $this->agreement->public_id);

        $this->assertSame(MatchStatus::Withdrawn, $this->agreement->refresh()->status);
        $this->assertSame(MobilityRequestStatus::Published, $this->rosso->mobilityRequests()->sole()->status);
        $this->assertSame(MobilityRequestStatus::Published, $this->hodh->mobilityRequests()->sole()->status);
        $this->assertSame(MatchStatus::Suggested, $this->competing->refresh()->status);
    }

    private function agree(): void
    {
        app(InviteToMatch::class)->handle($this->rosso, $this->agreement->public_id);
        app(AcceptMatch::class)->handle($this->hodh, $this->agreement->public_id);
    }
}
