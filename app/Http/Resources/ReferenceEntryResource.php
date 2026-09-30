<?php

namespace App\Http\Resources;

use App\Models\Establishment;
use App\Models\Grade;
use App\Models\Moughataa;
use App\Models\Profession;
use App\Models\Specialty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A reference entry, identified by its stable code (never the internal identifier), with both
 * names and the codes of its parents. Parents must be eager-loaded by the caller.
 *
 * @mixin Model
 */
class ReferenceEntryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $entry = $this->resource;

        return [
            'code' => $entry->code,
            'name_fr' => $entry->name_fr,
            'name_ar' => $entry->name_ar,
            ...match (true) {
                $entry instanceof Moughataa => ['wilaya_code' => $entry->wilaya->code],
                $entry instanceof Profession => ['sector_code' => $entry->sector->code],
                $entry instanceof Specialty, $entry instanceof Grade => ['profession_code' => $entry->profession->code],
                $entry instanceof Establishment => [
                    'type' => $entry->type,
                    'sector_code' => $entry->sector->code,
                    'wilaya_code' => $entry->moughataa->wilaya->code,
                    'moughataa_code' => $entry->moughataa->code,
                ],
                default => [],
            },
        ];
    }
}
