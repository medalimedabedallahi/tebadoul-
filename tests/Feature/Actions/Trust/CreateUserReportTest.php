<?php

namespace Tests\Feature\Actions\Trust;

use App\Actions\Trust\CreateUserReport;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Exceptions\Trust\ReportAlreadyExistsException;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMatchedPair;
use Tests\TestCase;

class CreateUserReportTest extends TestCase
{
    use CreatesMatchedPair;
    use RefreshDatabase;

    public function test_a_participant_can_report_the_counterpart_once(): void
    {
        [$rosso, $hodh, $match] = $this->createMatchedPair();

        $report = app(CreateUserReport::class)->handle($rosso, $match->public_id, ReportReason::Harassment, '  Insultes.  ');

        $this->assertSame($rosso->id, $report->reporter_id);
        $this->assertSame($hodh->id, $report->reported_user_id);
        $this->assertSame(ReportStatus::Pending, $report->status);
        $this->assertSame('Insultes.', $report->details);

        $this->expectException(ReportAlreadyExistsException::class);
        app(CreateUserReport::class)->handle($rosso, $match->public_id, ReportReason::Spam);
    }

    public function test_a_foreign_match_is_hidden_from_the_reporter(): void
    {
        [, , $match] = $this->createMatchedPair();

        $this->expectException(ModelNotFoundException::class);
        app(CreateUserReport::class)->handle(User::factory()->create(), $match->public_id, ReportReason::Other);
    }
}
