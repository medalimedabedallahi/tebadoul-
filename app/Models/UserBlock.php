<?php

namespace App\Models;

use Database\Factories\UserBlockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['blocker_id', 'blocked_id'])]
class UserBlock extends Model
{
    /** @use HasFactory<UserBlockFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocker_id');
    }

    /** @return BelongsTo<User, $this> */
    public function blocked(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_id');
    }

    public static function existsBetween(int $firstUserId, int $secondUserId): bool
    {
        return self::query()
            ->where(fn ($query) => $query->where('blocker_id', $firstUserId)->where('blocked_id', $secondUserId))
            ->orWhere(fn ($query) => $query->where('blocker_id', $secondUserId)->where('blocked_id', $firstUserId))
            ->exists();
    }
}
