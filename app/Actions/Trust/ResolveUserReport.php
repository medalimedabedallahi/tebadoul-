<?php

namespace App\Actions\Trust;

use App\Enums\AuditAction;
use App\Enums\ReportStatus;
use App\Exceptions\Trust\InvalidReportTransitionException;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class ResolveUserReport
{
    /** @return array<string, list<mixed>> */
    public static function rules(): array
    {
        return [
            'status' => ['required', Rule::in([ReportStatus::Resolved->value, ReportStatus::Dismissed->value])],
            'resolution_note' => ['required', 'string', 'max:1000'],
        ];
    }

    public function handle(User $actor, string $publicId, ReportStatus $status, string $note, ?string $ipAddress = null): UserReport
    {
        Gate::forUser($actor)->authorize('viewAny', UserReport::class);

        return DB::transaction(function () use ($actor, $publicId, $status, $note, $ipAddress): UserReport {
            $report = UserReport::query()
                ->where('public_id', Str::lower($publicId))
                ->lockForUpdate()
                ->firstOrFail();
            Gate::forUser($actor)->authorize('update', $report);

            if ($report->status !== ReportStatus::Pending) {
                throw new InvalidReportTransitionException;
            }

            $before = ['status' => $report->status->value];
            $report->forceFill([
                'status' => $status,
                'moderator_id' => $actor->getKey(),
                'resolution_note' => trim($note),
                'reviewed_at' => now(),
            ])->save();

            AuditLog::query()->create([
                'actor_id' => $actor->getKey(),
                'target_user_id' => $report->reported_user_id,
                'action' => $status === ReportStatus::Resolved ? AuditAction::ReportResolved : AuditAction::ReportDismissed,
                'reason' => trim($note),
                'before' => $before,
                'after' => ['status' => $status->value, 'report_public_id' => $report->public_id],
                'ip_address' => $ipAddress,
            ]);

            return $report->load(['reporter:id,public_id', 'reportedUser:id,public_id', 'moderator:id,public_id']);
        });
    }
}
