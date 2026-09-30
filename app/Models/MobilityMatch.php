<?php

namespace App\Models;

use App\Enums\MatchStatus;
use Database\Factories\MobilityMatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A match between mobility requests (table `matches`; `Match` is a reserved word in PHP).
 *
 * @property int $id
 * @property string $public_id
 * @property string $type
 * @property string $pair_key
 * @property MatchStatus $status
 * @property int $score
 * @property string $rules_version
 * @property Carbon|null $invalidated_at
 * @property string|null $invalidation_reason
 * @property string|null $outcome_reason
 * @property Carbon|null $expires_at
 */
#[Fillable(['type', 'pair_key', 'score', 'rules_version'])]
#[RouteKey('public_id')]
class MobilityMatch extends Model
{
    /** @use HasFactory<MobilityMatchFactory> */
    use HasFactory, HasUlids;

    protected $table = 'matches';

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MatchStatus::class,
            'score' => 'integer',
            'invalidated_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * The key identifying a pair of requests whatever their order.
     */
    public static function pairKey(int $requestId, int $otherRequestId): string
    {
        return min($requestId, $otherRequestId).':'.max($requestId, $otherRequestId);
    }

    /**
     * @return HasMany<MatchParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(MatchParticipant::class, 'match_id');
    }

    /**
     * @return HasMany<MatchReason, $this>
     */
    public function reasons(): HasMany
    {
        return $this->hasMany(MatchReason::class, 'match_id')->orderBy('id');
    }

    /** @return HasMany<MatchMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(MatchMessage::class, 'match_id');
    }

    /**
     * Matches in which `$user` takes part.
     *
     * @param  Builder<MobilityMatch>  $query
     */
    public function scopeInvolving(Builder $query, User $user): void
    {
        $query->whereHas('participants', fn (Builder $participants) => $participants->where('user_id', $user->getKey()));
    }

    /**
     * The participant of `$user`, or null when the user does not take part.
     */
    public function participantOf(User $user): ?MatchParticipant
    {
        return $this->participants->firstWhere('user_id', $user->getKey());
    }

    /**
     * The other participant of a direct match, seen from `$user`.
     */
    public function counterpartOf(User $user): ?MatchParticipant
    {
        return $this->participants->first(fn (MatchParticipant $participant): bool => $participant->user_id !== $user->getKey());
    }
}
