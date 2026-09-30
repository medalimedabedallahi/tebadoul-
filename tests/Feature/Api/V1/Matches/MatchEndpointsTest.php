<?php

namespace Tests\Feature\Api\V1\Matches;

use App\Enums\MatchStatus;
use App\Enums\MobilityRequestStatus;
use App\Enums\TokenAbility;
use App\Models\MobilityMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMatchedPair;
use Tests\TestCase;

class MatchEndpointsTest extends TestCase
{
    use CreatesMatchedPair;
    use RefreshDatabase;

    public function test_each_participant_sees_the_other_request_without_private_data(): void
    {
        [$rosso, $hodh, $match] = $this->createMatchedPair();
        $rosso->forceFill(['name' => 'Aminetou Sy', 'email' => 'aminetou@example.com'])->save();

        $response = $this->withToken($this->tokenFor($hodh))->getJson("/api/v1/matches/{$match->public_id}");

        $response->assertOk()
            ->assertJsonPath('data.status', 'suggested')
            ->assertJsonPath('data.score', $match->score)
            ->assertJsonPath('data.rules_version', 'v1-strict')
            ->assertJsonPath('data.my_request', $hodh->mobilityRequests()->sole()->public_id)
            ->assertJsonPath('data.counterpart.profession.code', 'teacher')
            ->assertJsonPath('data.counterpart.moughataa.code', '0606')
            ->assertJsonPath('data.counterpart.wilaya.code', '06')
            ->assertJsonCount(7, 'data.reasons')
            ->assertJsonPath('data.allowed_actions', ['invite', 'decline', 'block', 'report'])
            ->assertJsonPath('data.contact_sharing.available', false);

        $body = (string) $response->getContent();
        foreach (['Aminetou', 'aminetou@example.com', 'lycee-rosso', 'MAT-12345', $rosso->public_id, $rosso->mobilityRequests()->sole()->public_id] as $private) {
            $this->assertStringNotContainsString($private, $body);
        }

        $this->app['auth']->forgetGuards();
        $this->withToken($this->tokenFor($rosso))->getJson("/api/v1/matches/{$match->public_id}")
            ->assertOk()
            ->assertJsonPath('data.counterpart.moughataa.code', '0101');
    }

    public function test_lists_the_matches_of_the_account_and_hides_invalidated_ones_by_default(): void
    {
        [$rosso, $hodh, $match] = $this->createMatchedPair();
        $token = $this->tokenFor($hodh);

        $this->withToken($token)->getJson('/api/v1/matches')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.public_id', $match->public_id);

        $this->withToken($token)->getJson('/api/v1/matches?mobility_request='.$hodh->mobilityRequests()->sole()->public_id)
            ->assertJsonCount(1, 'data');
        $this->withToken($token)->getJson('/api/v1/matches?mobility_request='.$rosso->mobilityRequests()->sole()->public_id)
            ->assertJsonCount(0, 'data');

        $match->forceFill(['status' => MatchStatus::Invalidated, 'invalidation_reason' => 'geography'])->save();
        $this->withToken($token)->getJson('/api/v1/matches')->assertJsonCount(0, 'data');
        $this->withToken($token)->getJson('/api/v1/matches?status=invalidated')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.invalidation_reason', 'geography');
        $this->withToken($token)->getJson('/api/v1/matches?status=bogus')->assertUnprocessable();
    }

    public function test_a_match_is_invisible_to_other_accounts(): void
    {
        [, , $match] = $this->createMatchedPair();
        $outsider = User::factory()->create();

        $this->withToken($this->tokenFor($outsider))->getJson('/api/v1/matches')->assertJsonCount(0, 'data');
        $this->withToken($this->tokenFor($outsider))->getJson("/api/v1/matches/{$match->public_id}")
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found');
        $this->app['auth']->forgetGuards();
        $this->withoutToken()->getJson("/api/v1/matches/{$match->public_id}")->assertUnauthorized();
        $this->assertInstanceOf(MobilityMatch::class, $match->fresh());
    }

    public function test_participants_complete_the_invitation_consent_and_progress_flow(): void
    {
        [$rosso, $hodh, $match] = $this->createMatchedPair();
        $id = $match->public_id;

        $this->withToken($this->tokenFor($rosso))
            ->postJson("/api/v1/matches/$id/invitation", [], ['Idempotency-Key' => 'match-invite-0001'])
            ->assertOk()
            ->assertJsonPath('data.status', 'invited')
            ->assertJsonPath('data.invited_by_me', true)
            ->assertJsonPath('data.allowed_actions', ['withdraw', 'block', 'report']);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->tokenFor($hodh))
            ->postJson("/api/v1/matches/$id/acceptance", [], ['Idempotency-Key' => 'match-accept-0001'])
            ->assertOk()
            ->assertJsonPath('data.status', 'mutual')
            ->assertJsonPath('data.contact_sharing.available', false)
            ->assertJsonPath('data.allowed_actions.0', 'withdraw');

        $this->assertSame(MobilityRequestStatus::Matched, $rosso->mobilityRequests()->sole()->status);
        $this->assertSame(MobilityRequestStatus::Matched, $hodh->mobilityRequests()->sole()->status);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->tokenFor($rosso))->getJson("/api/v1/matches/$id/contact")
            ->assertForbidden()
            ->assertJsonPath('code', 'contact_not_authorized');

        $this->withToken($this->tokenFor($rosso))
            ->postJson("/api/v1/matches/$id/contact-consent", [], ['Idempotency-Key' => 'match-consent-0001'])
            ->assertOk()
            ->assertJsonPath('data.contact_sharing.my_consent', true)
            ->assertJsonPath('data.contact_sharing.available', false);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->tokenFor($hodh))
            ->postJson("/api/v1/matches/$id/contact-consent", [], ['Idempotency-Key' => 'match-consent-0002'])
            ->assertOk()
            ->assertJsonPath('data.contact_sharing.available', true)
            ->assertJsonPath('data.allowed_actions.2', 'view_contact');

        $this->app['auth']->forgetGuards();
        $this->withToken($this->tokenFor($rosso))->getJson("/api/v1/matches/$id/contact")
            ->assertOk()
            ->assertJsonPath('data.email', $hodh->email)
            ->assertJsonPath('data.phone', null)
            ->assertJsonMissingPath('data.name')
            ->assertJsonMissingPath('data.public_id');

        $this->app['auth']->forgetGuards();
        $this->withToken($this->tokenFor($hodh))->deleteJson("/api/v1/matches/$id/contact-consent")
            ->assertOk()
            ->assertJsonPath('data.contact_sharing.my_consent', false);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->tokenFor($rosso))->getJson("/api/v1/matches/$id/contact")
            ->assertForbidden();

        $this->withToken($this->tokenFor($rosso))
            ->postJson("/api/v1/matches/$id/progress", ['status' => 'submitted'], ['Idempotency-Key' => 'match-progress-0001'])
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted');
        $this->withToken($this->tokenFor($rosso))
            ->postJson("/api/v1/matches/$id/progress", ['status' => 'approved'], ['Idempotency-Key' => 'match-progress-0002'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.allowed_actions', ['block', 'report']);

        $this->assertSame(MobilityRequestStatus::Closed, $rosso->mobilityRequests()->sole()->status);
        $this->assertDatabaseHas('contact_events', ['match_id' => $match->id, 'action' => 'viewed']);
    }

    public function test_match_mutations_validate_payloads_and_hide_foreign_matches(): void
    {
        [$rosso, , $match] = $this->createMatchedPair();
        $token = $this->tokenFor($rosso);

        $this->withToken($token)->postJson("/api/v1/matches/{$match->public_id}/decline", ['reason' => 'personal-details'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
        $this->withToken($token)->postJson("/api/v1/matches/{$match->public_id}/progress", ['status' => 'approved'])
            ->assertConflict()
            ->assertJsonPath('code', 'invalid_match_transition');

        $outsider = User::factory()->create();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->tokenFor($outsider))
            ->postJson("/api/v1/matches/{$match->public_id}/invitation")
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found');
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('test', [TokenAbility::AccessApi->value])->plainTextToken;
    }
}
