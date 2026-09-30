<?php

namespace Tests\Feature\Api\V1\MobilityRequests;

use App\Actions\Profiles\SaveProfessionalProfile;
use App\Enums\MobilityRequestStatus;
use App\Enums\TokenAbility;
use App\Models\MobilityRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesReferenceData;
use Tests\TestCase;

class MobilityRequestEndpointsTest extends TestCase
{
    use CreatesReferenceData;
    use RefreshDatabase;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-01 10:00:00');
        $this->createReferenceData();
        $this->user = User::factory()->create();
        app(SaveProfessionalProfile::class)->handle($this->user, $this->teacherProfileInput());
        $this->token = $this->user->createToken('test', [TokenAbility::AccessApi->value])->plainTextToken;
    }

    public function test_creates_publishes_pauses_renews_and_closes_through_the_api(): void
    {
        $created = $this->withToken($this->token)->postJson('/api/v1/mobility-requests', [
            'available_from' => '2026-10-15',
            'expires_at' => '2027-04-15',
            'destinations' => [['wilaya' => '01', 'moughataa' => '0101'], ['wilaya' => '06']],
        ], ['Idempotency-Key' => 'create-request-0001']);

        $created->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.available_from', '2026-10-15')
            ->assertJsonPath('data.destinations.0.priority', 1)
            ->assertJsonPath('data.destinations.0.wilaya.code', '01')
            ->assertJsonPath('data.destinations.0.moughataa.code', '0101')
            ->assertJsonPath('data.destinations.1.moughataa', null)
            ->assertJsonPath('data.allowed_actions', ['update', 'publish', 'delete'])
            ->assertJsonMissingPath('data.id')
            ->assertJsonMissingPath('data.user_id');
        $id = $created->json('data.public_id');

        $this->withToken($this->token)->postJson("/api/v1/mobility-requests/$id/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.allowed_actions', ['update', 'pause', 'close']);

        $this->withToken($this->token)->patchJson("/api/v1/mobility-requests/$id", ['destinations' => [['wilaya' => '06']]])
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonCount(1, 'data.destinations');

        $this->withToken($this->token)->postJson("/api/v1/mobility-requests/$id/pause")
            ->assertOk()
            ->assertJsonPath('data.status', 'paused');

        MobilityRequest::query()->where('public_id', $id)->update(['status' => MobilityRequestStatus::Expired]);
        $this->withToken($this->token)->postJson("/api/v1/mobility-requests/$id/renew", ['expires_at' => '2027-09-01'])
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.expires_at', '2027-09-01');

        $this->withToken($this->token)->postJson("/api/v1/mobility-requests/$id/close", ['reason' => 'no_longer_needed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.close_reason', 'no_longer_needed');

        $this->withToken($this->token)->getJson('/api/v1/mobility-requests')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1);

        $this->withToken($this->token)->deleteJson("/api/v1/mobility-requests/$id")->assertNoContent();
        $this->withToken($this->token)->getJson("/api/v1/mobility-requests/$id")->assertNotFound();
    }

    public function test_reports_lifecycle_conflicts_with_stable_codes(): void
    {
        $draft = MobilityRequest::factory()->for($this->user)->create();
        $other = MobilityRequest::factory()->for($this->user)->published()->create();

        $this->withToken($this->token)->postJson("/api/v1/mobility-requests/{$draft->public_id}/pause")
            ->assertConflict()
            ->assertJsonPath('code', 'invalid_request_transition');

        $this->withToken($this->token)->postJson("/api/v1/mobility-requests/{$draft->public_id}/publish")
            ->assertConflict()
            ->assertJsonPath('code', 'request_not_publishable')
            ->assertJsonStructure(['errors' => ['destinations', 'status']]);

        $this->withToken($this->token)->deleteJson("/api/v1/mobility-requests/{$other->public_id}")
            ->assertConflict()
            ->assertJsonPath('code', 'invalid_request_transition');
    }

    public function test_validates_input_with_field_errors(): void
    {
        $this->withToken($this->token)->postJson('/api/v1/mobility-requests', [
            'available_from' => '15/10/2026',
            'expires_at' => '2027-04-15',
            'destinations' => [['wilaya' => '06', 'moughataa' => '0606']],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['available_from']);

        $this->withToken($this->token)->postJson('/api/v1/mobility-requests', [
            'available_from' => '2026-10-15',
            'expires_at' => '2027-04-15',
            'destinations' => [['wilaya' => '06', 'moughataa' => '0606']],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['destinations.0.moughataa']);

        $this->withToken($this->token)->postJson('/api/v1/mobility-requests/'.MobilityRequest::factory()->for($this->user)->status(MobilityRequestStatus::Paused)->create()->public_id.'/close', ['reason' => 'bored'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);

        $this->assertDatabaseCount('request_destinations', 0);
    }

    public function test_the_requests_of_another_account_are_invisible(): void
    {
        $foreign = MobilityRequest::factory()->published()->create();

        $this->withToken($this->token)->getJson('/api/v1/mobility-requests')->assertJsonCount(0, 'data');

        foreach ([
            ['getJson', ''],
            ['patchJson', ''],
            ['deleteJson', ''],
            ['postJson', '/pause'],
            ['postJson', '/close'],
        ] as [$method, $suffix]) {
            $this->withToken($this->token)->{$method}("/api/v1/mobility-requests/{$foreign->public_id}$suffix", ['reason' => 'other', 'expires_at' => '2027-01-01'])
                ->assertNotFound()
                ->assertJsonPath('code', 'not_found');
        }

        $this->assertSame(MobilityRequestStatus::Published, $foreign->fresh()->status);
    }

    public function test_requires_an_active_account_with_a_full_access_token(): void
    {
        $this->getJson('/api/v1/mobility-requests')->assertUnauthorized();

        $suspended = User::factory()->suspended()->create();
        $this->withToken($suspended->createToken('test', [TokenAbility::AccessApi->value])->plainTextToken)
            ->getJson('/api/v1/mobility-requests')
            ->assertForbidden();

        $legacy = $this->user->createToken('legacy', [TokenAbility::VerifyContact->value])->plainTextToken;
        $this->withToken($legacy)->postJson('/api/v1/mobility-requests', [])->assertForbidden();
    }
}
