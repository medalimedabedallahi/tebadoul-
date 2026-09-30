<?php

namespace App\Models;

use App\Contracts\ReferenceEntry;
use App\Models\Concerns\IsReferenceEntry;
use Database\Factories\ProfessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Profession or corps of a sector.
 *
 * @property int $sector_id
 */
#[Fillable(['sector_id', 'code', 'name_fr', 'name_ar', 'active'])]
class Profession extends Model implements ReferenceEntry
{
    /** @use HasFactory<ProfessionFactory> */
    use HasFactory, IsReferenceEntry;

    /**
     * @return BelongsTo<Sector, $this>
     */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    /**
     * @return HasMany<Specialty, $this>
     */
    public function specialties(): HasMany
    {
        return $this->hasMany(Specialty::class);
    }

    /**
     * @return HasMany<Grade, $this>
     */
    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }
}
