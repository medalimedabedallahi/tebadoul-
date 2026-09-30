<?php

namespace App\Actions\MobilityRequests;

use App\Models\MobilityRequest;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

/**
 * The actor's own requests (deleted ones excluded), newest first.
 */
final class ListMobilityRequests
{
    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,50'],
        ];
    }

    /**
     * @return LengthAwarePaginator<int, MobilityRequest>
     */
    public function handle(User $user, int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        Gate::forUser($user)->authorize('create', MobilityRequest::class);

        return $user->mobilityRequests()
            ->with(['destinations.wilaya', 'destinations.moughataa', 'destinations.establishment'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }
}
