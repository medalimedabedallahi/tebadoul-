<?php

namespace App\Models;

use App\Contracts\ReferenceEntry;
use App\Models\Concerns\IsReferenceEntry;
use Database\Factories\SectorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Professional sector covered by the platform (education, health).
 */
#[Fillable(['code', 'name_fr', 'name_ar', 'active'])]
class Sector extends Model implements ReferenceEntry
{
    /** @use HasFactory<SectorFactory> */
    use HasFactory, IsReferenceEntry;

    /**
     * @return HasMany<Profession, $this>
     */
    public function professions(): HasMany
    {
        return $this->hasMany(Profession::class);
    }
}
