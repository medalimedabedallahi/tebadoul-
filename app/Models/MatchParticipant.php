<?php

namespace App\Models;

use App\Enums\MatchDecision;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A request, and its owner, taking part in a match.
 *
 * @property int $id
 * @property int $match_id
 * @property int $mobility_request_id
 * @property int $user_id
 * @property MatchDecision|null $decision
 * @property Carbon|null $decided_at
 * @property Carbon|null $contact_consented_at
 */
#[Fillable(['mobility_request_id', 'user_id'])]
class MatchParticipant extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decision' => MatchDecision::class,
            'decided_at' => 'datetime',
            'contact_consented_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MobilityMatch, $this>
     */
    public function mobilityMatch(): BelongsTo
    {
        return $this->belongsTo(MobilityMatch::class, 'match_id');
    }

    /**
     * @return BelongsTo<MobilityRequest, $this>
     */
    public function mobilityRequest(): BelongsTo
    {
        return $this->belongsTo(MobilityRequest::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
