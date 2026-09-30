<?php

namespace App\Actions\Admin;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The audit trail of sensitive administrative decisions (PRD FR 019), newest first, filtered by
 * action or by the account concerned. Read-only: nobody edits or deletes an entry.
 */
final class ListAuditLogs
{
    /** @return array<string, list<mixed>> */
    public static function rules(): array
    {
        return [
            'action' => ['sometimes', Rule::enum(AuditAction::class)],
            'target_public_id' => ['sometimes', 'required', 'string', 'ulid'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }

    /** @return LengthAwarePaginator<int, AuditLog> */
    public function handle(User $actor, ?AuditAction $action = null, ?string $targetPublicId = null, int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewAny', AuditLog::class);

        return AuditLog::query()
            ->when($action !== null, fn (Builder $query) => $query->where('action', $action))
            ->when($targetPublicId !== null, fn (Builder $query) => $query->whereHas(
                'targetUser',
                fn (Builder $target) => $target->where('public_id', Str::lower((string) $targetPublicId)),
            ))
            ->with(['actor:id,public_id', 'targetUser:id,public_id'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(min(max($perPage, 1), 100), ['*'], 'page', max($page, 1));
    }
}
