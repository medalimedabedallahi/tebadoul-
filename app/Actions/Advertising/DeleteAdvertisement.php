<?php

namespace App\Actions\Advertising;

use App\Models\Advertisement;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Deletes an advertisement and its banner for good. To stop showing one temporarily, deactivate
 * it with {@see SaveAdvertisement} instead.
 */
final class DeleteAdvertisement
{
    /**
     * @throws ModelNotFoundException
     */
    public function handle(User $actor, string $publicId): void
    {
        $advertisement = Advertisement::query()
            ->select(Advertisement::SUMMARY_COLUMNS)
            ->where('public_id', Str::lower($publicId))
            ->firstOrFail();

        Gate::forUser($actor)->authorize('delete', $advertisement);

        $advertisement->delete();
    }
}
