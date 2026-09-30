<?php

namespace App\Models;

use App\Contracts\ReferenceEntry;
use App\Models\Concerns\IsReferenceEntry;
use Database\Factories\EstablishmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * School or health facility, located in a moughataa.
 *
 * @property int $sector_id
 * @property int $moughataa_id
 * @property string $type
 */
#[Fillable(['sector_id', 'moughataa_id', 'code', 'type', 'name_fr', 'name_ar', 'active'])]
class Establishment extends Model implements ReferenceEntry
{
    /** @use HasFactory<EstablishmentFactory> */
    use HasFactory, IsReferenceEntry;

    /**
     * @return BelongsTo<Sector, $this>
     */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    /**
     * @return BelongsTo<Moughataa, $this>
     */
    public function moughataa(): BelongsTo
    {
        return $this->belongsTo(Moughataa::class);
    }
}
