<?php

namespace App\Models;

use App\Contracts\ReferenceEntry;
use App\Models\Concerns\IsReferenceEntry;
use Database\Factories\WilayaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Wilaya (region) of Mauritania.
 */
#[Fillable(['code', 'name_fr', 'name_ar', 'active'])]
class Wilaya extends Model implements ReferenceEntry
{
    /** @use HasFactory<WilayaFactory> */
    use HasFactory, IsReferenceEntry;

    /**
     * @return HasMany<Moughataa, $this>
     */
    public function moughataas(): HasMany
    {
        return $this->hasMany(Moughataa::class);
    }
}
