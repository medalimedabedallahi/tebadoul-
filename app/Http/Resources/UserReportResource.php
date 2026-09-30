<?php

namespace App\Http\Resources;

use App\Models\UserReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin UserReport */
class UserReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var UserReport $report */
        $report = $this->resource;

        return [
            'public_id' => $report->public_id,
            'match_public_id' => $report->mobilityMatch->public_id,
            'reporter_public_id' => $report->reporter->public_id,
            'reported_user_public_id' => $report->reportedUser->public_id,
            'reason' => $report->reason->value,
            'details' => $report->details,
            'status' => $report->status->value,
            'moderator_public_id' => $report->moderator?->public_id,
            'resolution_note' => $report->resolution_note,
            'reviewed_at' => $report->reviewed_at?->toIso8601String(),
            'created_at' => $report->created_at?->toIso8601String(),
        ];
    }
}
