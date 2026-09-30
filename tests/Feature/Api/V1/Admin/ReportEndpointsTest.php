<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Actions\Trust\CreateUserReport;
use App\Enums\ReportReason;
use App\Enums\TokenAbility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMatchedPair;
use Tests\TestCase;

class ReportEndpointsTest extends TestCase
{
    use CreatesMatchedPair;
    use RefreshDatabase;

    public function test_moderators_list_and_resolve_reports_by_public_identifiers(): void
    {
        [$rosso, , $match] = $this->createMatchedPair();
        $report = app(CreateUserReport::class)->handle($rosso, $match->public_id, ReportReason::Harassment, 'Contenu signalé.');
        $moderator = User::factory()->moderator()->create();
        $token = $this->tokenFor($moderator);

        $this->withToken($token)->getJson('/api/v1/admin/reports?status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.public_id', $report->public_id)
            ->assertJsonPath('data.0.match_public_id', $match->public_id)
            ->assertJsonMissingPath('data.0.reporter_id');

        $this->withToken($token)->patchJson("/api/v1/admin/reports/{$report->public_id}", [
            'status' => 'resolved',
            'resolution_note' => 'Mesure prise.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.moderator_public_id', $moderator->public_id);
    }

    public function test_ordinary_users_cannot_list_or_resolve_reports(): void
    {
        [$rosso, , $match] = $this->createMatchedPair();
        $report = app(CreateUserReport::class)->handle($rosso, $match->public_id, ReportReason::Other);
        $token = $this->tokenFor(User::factory()->create());

        $this->withToken($token)->getJson('/api/v1/admin/reports')->assertForbidden();
        $this->withToken($token)->patchJson("/api/v1/admin/reports/{$report->public_id}", [
            'status' => 'dismissed',
            'resolution_note' => 'Sans suite.',
        ])->assertForbidden();
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('test', [TokenAbility::AccessApi->value])->plainTextToken;
    }
}
