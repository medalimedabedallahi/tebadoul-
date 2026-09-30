<?php

namespace App\Actions\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ListUsers
{
    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'public_id' => ['sometimes', 'required', 'string', 'ulid'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function handle(
        User $actor,
        ?string $publicId = null,
        int $page = 1,
        int $perPage = 20,
    ): LengthAwarePaginator {
        Gate::forUser($actor)->authorize('viewAny', User::class);

        return User::query()
            ->where('role', UserRole::User)
            ->when($publicId !== null, fn ($query) => $query->where('public_id', Str::lower($publicId)))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['id', 'public_id', 'status'], 'page', $page);
    }
}
