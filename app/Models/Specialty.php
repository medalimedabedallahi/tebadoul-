<?php

namespace App\Models;

use App\Contracts\ReferenceEntry;
use App\Models\Concerns\IsReferenceEntry;
use Database\Factories\SpecialtyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Specialty or subject of a profession.
 *
 * @property int $profession_id
 */
#[Fillable(['profession_id', 'code', 'name_fr', 'name_ar', 'active'])]
class Specialty extends Model implements ReferenceEntry
{
    /** @use HasFactory<SpecialtyFactory> */
    use HasFactory, IsReferenceEntry;

    /**
     * @return BelongsTo<Profession, $this>
     */
    public function profession(): BelongsTo
    {
        return $this->belongsTo(Profession::class);
    }
}
