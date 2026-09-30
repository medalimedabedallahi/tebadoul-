<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One accepted destination of a mobility request: a wilaya, optionally a moughataa of it,
 * optionally an establishment of that moughataa. `priority` 1 is the preferred destination.
 *
 * @property int $id
 * @property int $mobility_request_id
 * @property int $priority
 * @property int $wilaya_id
 * @property int|null $moughataa_id
 * @property int|null $establishment_id
 */
#[Fillable(['priority', 'wilaya_id', 'moughataa_id', 'establishment_id'])]
class RequestDestination extends Model
{
    /**
     * @return BelongsTo<MobilityRequest, $this>
     */
    public function mobilityRequest(): BelongsTo
    {
        return $this->belongsTo(MobilityRequest::class);
    }

    /**
     * @return BelongsTo<Wilaya, $this>
     */
    public function wilaya(): BelongsTo
    {
        return $this->belongsTo(Wilaya::class);
    }

    /**
     * @return BelongsTo<Moughataa, $this>
     */
    public function moughataa(): BelongsTo
    {
        return $this->belongsTo(Moughataa::class);
    }

    /**
     * @return BelongsTo<Establishment, $this>
     */
    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }
}
