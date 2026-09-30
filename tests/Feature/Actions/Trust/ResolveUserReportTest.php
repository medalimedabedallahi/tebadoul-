<?php

namespace Tests\Feature\Actions\Trust;

use App\Actions\Trust\CreateUserReport;
use App\Actions\Trust\ResolveUserReport;
use App\Enums\AuditAction;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Exceptions\Trust\InvalidReportTransitionException;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMatchedPair;
use Tests\TestCase;

class ResolveUserReportTest extends TestCase
{
    use CreatesMatchedPair;
    use RefreshDatabase;

    public function test_a_moderator_can_resolve_a_pending_report_with_an_audit_entry(): void
    {
        [$rosso, , $match] = $this->createMatchedPair();
        $report = app(CreateUserReport::class)->handle($rosso, $match->public_id, ReportReason::Fraud);
        $moderator = User::factory()->moderator()->create();

        $resolved = app(ResolveUserReport::class)->handle($moderator, $report->public_id, ReportStatus::Resolved, 'Preuves vérifiées.', '192.0.2.10');

        $this->assertSame(ReportStatus::Resolved, $resolved->status);
        $this->assertSame($moderator->id, $resolved->moderator_id);
        $this->assertNotNull($resolved->reviewed_at);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $moderator->id,
            'target_user_id' => $report->reported_user_id,
            'action' => AuditAction::ReportResolved->value,
            'reason' => 'Preuves vérifiées.',
        ]);

        $this->expectException(InvalidReportTransitionException::class);
        app(ResolveUserReport::class)->handle($moderator, $report->public_id, ReportStatus::Dismissed, 'Nouvelle décision.');
    }

    public function test_an_ordinary_user_cannot_moderate_reports(): void
    {
        [$rosso, , $match] = $this->createMatchedPair();
        $report = app(CreateUserReport::class)->handle($rosso, $match->public_id, ReportReason::Spam);

        $this->expectException(AuthorizationException::class);
        app(ResolveUserReport::class)->handle(User::factory()->create(), $report->public_id, ReportStatus::Dismissed, 'Sans suite.');
    }
}
