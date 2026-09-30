<?php

namespace App\Models;

use Database\Factories\MatchMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property int $match_id
 * @property int $sender_id
 * @property string $body
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 */
#[Fillable(['body'])]
#[RouteKey('public_id')]
class MatchMessage extends Model
{
    /** @use HasFactory<MatchMessageFactory> */
    use HasFactory, HasUlids;

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    /** @return BelongsTo<MobilityMatch, $this> */
    public function mobilityMatch(): BelongsTo
    {
        return $this->belongsTo(MobilityMatch::class, 'match_id');
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
