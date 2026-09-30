<?php

namespace App\Actions\Trust;

use App\Enums\ReportStatus;
use App\Models\User;
use App\Models\UserReport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

final class ListUserReports
{
    /** @return LengthAwarePaginator<int, UserReport> */
    public function handle(User $actor, ?ReportStatus $status = null, int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewAny', UserReport::class);

        return UserReport::query()
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->with(['mobilityMatch:id,public_id', 'reporter:id,public_id', 'reportedUser:id,public_id', 'moderator:id,public_id'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(min(max($perPage, 1), 100), ['*'], 'page', max($page, 1));
    }
}
