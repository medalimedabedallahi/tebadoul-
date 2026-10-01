<?php

namespace App\Actions\Advertising;

use App\Models\Advertisement;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

/**
 * Lists every advertisement for administrators, the most recently changed first, without the
 * banner bytes.
 */
final class ListAdvertisements
{
    /**
     * @return LengthAwarePaginator<int, Advertisement>
     */
    public function handle(User $actor, int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewAny', Advertisement::class);

        return Advertisement::query()
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate($perPage, Advertisement::SUMMARY_COLUMNS, 'page', $page);
    }
}
