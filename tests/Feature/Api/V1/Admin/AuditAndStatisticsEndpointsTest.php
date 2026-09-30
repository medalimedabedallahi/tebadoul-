<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Actions\Auth\SuspendUser;
use App\Enums\TokenAbility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditAndStatisticsEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_administrator_reads_the_audit_trail_by_public_ids_without_ip_address(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create();
        app(SuspendUser::class)->handle($administrator, $target->public_id, 'Fraude avérée.', '192.0.2.10');

        $this->withToken($this->tokenFor($administrator))->getJson('/api/v1/admin/audit-logs?action=user_suspended')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertExactJsonStructure([
                'data' => [['action', 'actor_public_id', 'target_user_public_id', 'reason', 'before', 'after', 'created_at']],
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'from', 'last_page', 'links', 'path', 'per_page', 'to', 'total'],
            ])
            ->assertJsonPath('data.0.actor_public_id', $administrator->public_id)
            ->assertJsonPath('data.0.target_user_public_id', $target->public_id)
            ->assertJsonPath('data.0.reason', 'Fraude avérée.');
    }

    public function test_an_unknown_action_filter_gets_422(): void
    {
        $this->withToken($this->tokenFor(User::factory()->administrator()->create()))
            ->getJson('/api/v1/admin/audit-logs?action=deleted_everything')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['action']);
    }

    public function test_a_moderator_gets_403_on_the_audit_trail_and_the_statistics(): void
    {
        $token = $this->tokenFor(User::factory()->moderator()->create());

        $this->withToken($token)->getJson('/api/v1/admin/audit-logs')->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/admin/statistics')->assertForbidden();
    }

    public function test_an_administrator_reads_the_statistics(): void
    {
        User::factory()->count(2)->create();

        $this->withToken($this->tokenFor(User::factory()->administrator()->create()))->getJson('/api/v1/admin/statistics')
            ->assertOk()
            ->assertJsonPath('data.accounts.total', 2)
            ->assertJsonPath('data.requests.published', 0)
            ->assertJsonPath('data.matches.suggested', 0)
            ->assertJsonStructure(['data' => ['generated_at', 'accounts', 'requests' => ['by_sector'], 'matches', 'mutual_agreements', 'reports', 'coverage']]);
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('test', [TokenAbility::AccessApi->value])->plainTextToken;
    }
}
