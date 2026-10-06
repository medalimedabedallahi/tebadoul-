<?php

namespace Tests\Feature\Actions\Matching;

use App\Actions\Matching\AcceptMatch;
use App\Actions\Matching\DeclineMatch;
use App\Actions\Matching\InviteToMatch;
use App\Actions\Matching\ShareContact;
use App\Actions\Matching\UpdateMatchProgress;
use App\Actions\Matching\WithdrawFromMatch;
use App\Enums\MatchDeclineReason;
use App\Enums\MatchStatus;
use App\Enums\MobilityRequestStatus;
use App\Exceptions\Matching\ContactNotAuthorizedException;
use App\Exceptions\Matching\InvalidMatchTransitionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMatchedPair;
use Tests\TestCase;

class MatchParticipationActionsTest extends TestCase
{
    use CreatesMatchedPair;
    use RefreshDatabase;

    public function test_invitation_acceptance_and_double_consent_are_explicit_and_audited(): void
    {
        [$rosso, $hodh, $match] = $this->createMatchedPair();

        app(InviteToMatch::class)->handle($rosso, $match->public_id);
        $this->assertSame(MatchStatus::Invited, $match->refresh()->status);
        $this->assertNotNull($match->participantOf($rosso)?->decided_at);
        $this->assertNull($match->participantOf($hodh)?->decision);

        app(AcceptMatch::class)->handle($hodh, $match->public_id);
        $this->assertSame(MatchStatus::Mutual, $match->refresh()->status);
        $this->assertSame(MobilityRequestStatus::Matched, $rosso->mobilityRequests()->sole()->status);
        $this->assertSame(MobilityRequestStatus::Matched, $hodh->mobilityRequests()->sole()->status);

        $sharing = app(ShareContact::class);
        $this->expectException(ContactNotAuthorizedException::class);
        $sharing->reveal($rosso, $match->public_id);
    }

    public function test_contact_access_needs_both_consents_and_revocation_blocks_future_access(): void
    {
        [$rosso, $hodh, $match] = $this->createMatchedPair();
        app(InviteToMatch::class)->handle($rosso, $match->public_id);
        app(AcceptMatch::class)->handle($hodh, $match->public_id);
        $sharing = app(ShareContact::class);

        $sharing->grant($rosso, $match->public_id, '192.0.2.10');
        $sharing->grant($hodh, $match->public_id, '192.0.2.11');
        $this->assertSame(['email' => $hodh->email, 'phone' => null], $sharing->reveal($rosso, $match->public_id, '192.0.2.10'));
        $this->assertDatabaseCount('contact_events', 3);

        $sharing->revoke($hodh, $match->public_id, '192.0.2.11');
        $this->assertDatabaseHas('contact_events', ['user_id' => $hodh->id, 'action' => 'revoked']);

        $this->expectException(ContactNotAuthorizedException::class);
        $sharing->reveal($rosso, $match->public_id);
    }

    public function test_declining_and_withdrawing_produce_final_states(): void
    {
        [$rosso, , $match] = $this->createMatchedPair();

        app(DeclineMatch::class)->handle($rosso, $match->public_id, MatchDeclineReason::SituationChanged);

        $this->assertSame(MatchStatus::Declined, $match->refresh()->status);
        $this->assertSame('situation_changed', $match->outcome_reason);
    }

    public function test_withdrawing_an_agreement_releases_requests_and_revokes_consents(): void
    {
        [$rosso, $hodh, $match] = $this->createMatchedPair();
        app(InviteToMatch::class)->handle($rosso, $match->public_id);
        app(AcceptMatch::class)->handle($hodh, $match->public_id);
        app(ShareContact::class)->grant($rosso, $match->public_id);

        app(WithdrawFromMatch::class)->handle($hodh, $match->public_id);

        $this->assertSame(MatchStatus::Withdrawn, $match->refresh()->status);
        $this->assertSame(MobilityRequestStatus::Published, $rosso->mobilityRequests()->sole()->status);
        $this->assertSame(MobilityRequestStatus::Published, $hodh->mobilityRequests()->sole()->status);
        $this->assertNull($match->participantOf($rosso)?->contact_consented_at);
        $this->assertDatabaseHas('contact_events', ['user_id' => $rosso->id, 'action' => 'revoked']);
    }

    public function test_progress_only_moves_forward_and_approval_closes_the_requests(): void
    {
        [$rosso, $hodh, $match] = $this->createMatchedPair();
        app(InviteToMatch::class)->handle($rosso, $match->public_id);
        app(AcceptMatch::class)->handle($hodh, $match->public_id);

        app(UpdateMatchProgress::class)->handle($rosso, $match->public_id, MatchStatus::Submitted);
        app(UpdateMatchProgress::class)->handle($hodh, $match->public_id, MatchStatus::Approved);

        $this->assertSame(MatchStatus::Approved, $match->refresh()->status);
        $this->assertSame(MobilityRequestStatus::Closed, $rosso->mobilityRequests()->sole()->status);
        $this->assertSame(MobilityRequestStatus::Closed, $hodh->mobilityRequests()->sole()->status);
    }

    public function test_unanswered_invitations_expire_hourly(): void
    {
        [$rosso, , $match] = $this->createMatchedPair();
        $this->travelTo('2026-10-01 10:00:00');
        config(['matching.invitation_ttl_days' => 2]);
        app(InviteToMatch::class)->handle($rosso, $match->public_id);

        $this->travelTo('2026-10-03 10:00:01');
        $this->artisan('matches:expire-invitations')->expectsOutput('1 invitation(s) expired.')->assertSuccessful();

        $this->assertSame(MatchStatus::Expired, $match->refresh()->status);
    }

    public function test_an_overdue_invitation_cannot_be_accepted_before_the_scheduler_runs(): void
    {
        $this->freezeSecond();
        [$rosso, $hodh, $match] = $this->createMatchedPair();
        app(InviteToMatch::class)->handle($rosso, $match->public_id);
        $this->travelTo($match->refresh()->expires_at->copy()->addSecond());
        $notificationCount = $rosso->notifications()->count();

        try {
            app(AcceptMatch::class)->handle($hodh, $match->public_id);
            $this->fail('An overdue invitation must not be accepted.');
        } catch (InvalidMatchTransitionException) {
            $this->assertSame(MatchStatus::Invited, $match->refresh()->status);
        }

        $this->assertNull($match->participantOf($hodh)?->decision);
        $this->assertSame(MobilityRequestStatus::Published, $rosso->mobilityRequests()->sole()->status);
        $this->assertSame(MobilityRequestStatus::Published, $hodh->mobilityRequests()->sole()->status);
        $this->assertSame($notificationCount, $rosso->notifications()->count());
    }

    public function test_an_invitation_can_still_be_accepted_at_its_exact_deadline(): void
    {
        $this->freezeSecond();
        [$rosso, $hodh, $match] = $this->createMatchedPair();
        app(InviteToMatch::class)->handle($rosso, $match->public_id);
        $this->travelTo($match->refresh()->expires_at->copy());

        app(AcceptMatch::class)->handle($hodh, $match->public_id);

        $this->assertSame(MatchStatus::Mutual, $match->refresh()->status);
        $this->assertSame(MobilityRequestStatus::Matched, $rosso->mobilityRequests()->sole()->status);
        $this->assertSame(MobilityRequestStatus::Matched, $hodh->mobilityRequests()->sole()->status);
    }
}
