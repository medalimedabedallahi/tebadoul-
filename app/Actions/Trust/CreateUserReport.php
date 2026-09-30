<?php

namespace App\Actions\Trust;

use App\Actions\Matching\Concerns\ManagesParticipation;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Exceptions\Trust\ReportAlreadyExistsException;
use App\Models\User;
use App\Models\UserReport;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class CreateUserReport
{
    use ManagesParticipation;

    /** @return array<string, list<mixed>> */
    public static function rules(): array
    {
        return [
            'reason' => ['required', Rule::enum(ReportReason::class)],
            'details' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function handle(User $user, string $matchPublicId, ReportReason $reason, ?string $details = null): UserReport
    {
        try {
            return DB::transaction(function () use ($user, $matchPublicId, $reason, $details): UserReport {
                [$match, , $other] = $this->lockParticipation($user, $matchPublicId);

                if (UserReport::query()
                    ->where('match_id', $match->getKey())
                    ->where('reporter_id', $user->getKey())
                    ->where('reported_user_id', $other->user_id)
                    ->exists()) {
                    throw new ReportAlreadyExistsException;
                }

                $report = new UserReport;
                $report->forceFill([
                    'match_id' => $match->getKey(),
                    'reporter_id' => $user->getKey(),
                    'reported_user_id' => $other->user_id,
                    'reason' => $reason,
                    'details' => filled($details) ? trim((string) $details) : null,
                    'status' => ReportStatus::Pending,
                ])->save();

                return $report;
            });
        } catch (UniqueConstraintViolationException) {
            throw new ReportAlreadyExistsException;
        }
    }
}
