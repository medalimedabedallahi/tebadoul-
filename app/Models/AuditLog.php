<?php

namespace App\Models;

use App\Enums\AuditAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable application audit entry for a sensitive administrative operation.
 *
 * @property int $id
 * @property int $actor_id
 * @property int $target_user_id
 * @property AuditAction $action
 * @property string $reason
 * @property array<string, mixed> $before
 * @property array<string, mixed> $after
 * @property string|null $ip_address
 */
#[Fillable(['actor_id', 'target_user_id', 'action', 'reason', 'before', 'after', 'ip_address'])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'before' => 'array',
            'after' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
