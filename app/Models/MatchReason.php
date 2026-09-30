<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One criterion of a match score: points obtained out of `max_points`, and a short detail code
 * (for example `exact_moughataa_both`), never personal data.
 *
 * @property int $id
 * @property int $match_id
 * @property string $criterion
 * @property int $points
 * @property int $max_points
 * @property string $detail
 */
#[Fillable(['criterion', 'points', 'max_points', 'detail'])]
class MatchReason extends Model
{
    /**
     * @return BelongsTo<MobilityMatch, $this>
     */
    public function mobilityMatch(): BelongsTo
    {
        return $this->belongsTo(MobilityMatch::class, 'match_id');
    }
}
