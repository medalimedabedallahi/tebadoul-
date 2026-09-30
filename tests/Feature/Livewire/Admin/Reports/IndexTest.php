<?php

namespace Tests\Feature\Livewire\Admin\Reports;

use App\Actions\Trust\CreateUserReport;
use App\Enums\AuditAction;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Livewire\Admin\Reports\Index;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesMatchedPair;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use CreatesMatchedPair;
    use RefreshDatabase;

    public function test_only_moderators_and_administrators_can_open_the_queue(): void
    {
        $this->get(route('admin.reports.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('admin.reports.index'))->assertForbidden();
        $this->actingAs(User::factory()->moderator()->create())->get(route('admin.reports.index'))->assertOk();
        $this->actingAs(User::factory()->administrator()->create())->get(route('admin.reports.index'))->assertOk();
    }

    public function test_the_queue_escapes_details_and_records_a_moderation_decision(): void
    {
        [$rosso, , $match] = $this->createMatchedPair();
        $report = app(CreateUserReport::class)->handle($rosso, $match->public_id, ReportReason::Harassment, '<script>alert(1)</script>');
        $moderator = User::factory()->moderator()->create();

        Livewire::actingAs($moderator)
            ->test(Index::class)
            ->assertSee($report->public_id)
            ->assertSee('<script>alert(1)</script>')
            ->assertDontSeeHtml('<script>alert(1)</script>')
            ->call('startDecision', $report->public_id, 'resolved')
            ->set('resolutionNote', 'Contenu vérifié.')
            ->call('confirmDecision')
            ->assertHasNoErrors()
            ->assertSet('decisionRecorded', __('moderation.decision_recorded'));

        $this->assertSame(ReportStatus::Resolved, $report->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::ReportResolved->value]);
    }

    public function test_a_decision_requires_a_note_and_rejects_a_stale_report(): void
    {
        [$rosso, , $match] = $this->createMatchedPair();
        $report = app(CreateUserReport::class)->handle($rosso, $match->public_id, ReportReason::Spam);
        $moderator = User::factory()->moderator()->create();

        $component = Livewire::actingAs($moderator)
            ->test(Index::class)
            ->call('startDecision', $report->public_id, 'dismissed')
            ->set('resolutionNote', '  ')
            ->call('confirmDecision')
            ->assertHasErrors(['resolution_note' => 'required']);

        $report->forceFill(['status' => ReportStatus::Resolved])->save();
        $component
            ->set('resolutionNote', 'Page périmée.')
            ->call('confirmDecision')
            ->assertHasErrors(['resolution_note']);
    }
}
