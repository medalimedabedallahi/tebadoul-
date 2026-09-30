<?php

namespace App\Models;

use App\Contracts\ReferenceEntry;
use App\Models\Concerns\IsReferenceEntry;
use Database\Factories\MoughataaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Moughataa (department) of a wilaya.
 *
 * @property int $wilaya_id
 */
#[Fillable(['wilaya_id', 'code', 'name_fr', 'name_ar', 'active'])]
class Moughataa extends Model implements ReferenceEntry
{
    /** @use HasFactory<MoughataaFactory> */
    use HasFactory, IsReferenceEntry;

    /**
     * @return BelongsTo<Wilaya, $this>
     */
    public function wilaya(): BelongsTo
    {
        return $this->belongsTo(Wilaya::class);
    }
}
