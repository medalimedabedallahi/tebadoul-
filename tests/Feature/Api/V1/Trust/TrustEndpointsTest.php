<?php

namespace Tests\Feature\Api\V1\Trust;

use App\Actions\Matching\AcceptMatch;
use App\Actions\Matching\InviteToMatch;
use App\Enums\TokenAbility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMatchedPair;
use Tests\TestCase;

class TrustEndpointsTest extends TestCase
{
    use CreatesMatchedPair;
    use RefreshDatabase;

    public function test_participants_exchange_messages_and_the_recipient_marks_one_read(): void
    {
        [$rosso, $hodh, $match] = $this->createMatchedPair();
        app(InviteToMatch::class)->handle($rosso, $match->public_id);
        app(AcceptMatch::class)->handle($hodh, $match->public_id);

        $created = $this->withToken($this->tokenFor($rosso))
            ->postJson("/api/v1/matches/{$match->public_id}/messages", ['body' => 'Bonjour'], ['Idempotency-Key' => 'message-0001'])
            ->assertCreated()
            ->assertJsonPath('data.from_me', true)
            ->assertJsonPath('data.body', 'Bonjour')
            ->json('data.public_id');

        $this->app['auth']->forgetGuards();
        $this->withToken($this->tokenFor($hodh))
            ->getJson("/api/v1/matches/{$match->public_id}/messages")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.from_me', false)
            ->assertJsonMissingPath('data.0.sender_id');

        $this->withToken($this->tokenFor($hodh))
            ->postJson("/api/v1/matches/{$match->public_id}/messages/$created/read-receipt", [], ['Idempotency-Key' => 'read-0001'])
            ->assertOk()
            ->assertJsonPath('data.public_id', $created)
            ->assertJsonPath('data.from_me', false);
    }

    public function test_a_block_invalidates_the_match_and_prevents_further_interactions(): void
    {
        [$rosso, $hodh, $match] = $this->createMatchedPair();
        app(InviteToMatch::class)->handle($rosso, $match->public_id);
        app(AcceptMatch::class)->handle($hodh, $match->public_id);

        $this->withToken($this->tokenFor($rosso))
            ->postJson("/api/v1/matches/{$match->public_id}/block", [], ['Idempotency-Key' => 'block-0001'])
            ->assertOk()
            ->assertJsonPath('data.status', 'invalidated')
            ->assertJsonPath('data.invalidation_reason', 'blocked')
            ->assertJsonPath('data.blocked_by_me', true)
            ->assertJsonPath('data.allowed_actions', ['unblock', 'report']);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->tokenFor($hodh))
            ->postJson("/api/v1/matches/{$match->public_id}/messages", ['body' => 'Interdit'], ['Idempotency-Key' => 'message-0002'])
            ->assertForbidden()
            ->assertJsonPath('code', 'interaction_blocked');
    }

    public function test_a_participant_reports_once_and_foreign_accounts_see_no_match(): void
    {
        [$rosso, , $match] = $this->createMatchedPair();

        $this->withToken($this->tokenFor($rosso))
            ->postJson("/api/v1/matches/{$match->public_id}/reports", ['reason' => 'spam', 'details' => 'Messages répétés.'], ['Idempotency-Key' => 'report-0001'])
            ->assertCreated()
            ->assertJsonPath('data.reason', 'spam')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonMissingPath('data.reporter_id')
            ->assertJsonMissingPath('data.reported_user_id');

        $this->withToken($this->tokenFor($rosso))
            ->postJson("/api/v1/matches/{$match->public_id}/reports", ['reason' => 'fraud'], ['Idempotency-Key' => 'report-0002'])
            ->assertConflict()
            ->assertJsonPath('code', 'report_already_exists');

        $outsider = User::factory()->create();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->tokenFor($outsider))
            ->getJson("/api/v1/matches/{$match->public_id}/messages")
            ->assertNotFound();
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('test', [TokenAbility::AccessApi->value])->plainTextToken;
    }
}
