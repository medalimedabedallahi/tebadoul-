<?php

namespace App\Http\Resources;

use App\Contracts\ReferenceEntry;
use App\Models\ProfessionalProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The owner's view of their professional profile: each reference by code and names, never by
 * internal identifier. Only returned on `/me/profile`, so the professional identifier is included.
 * Expects the references (and `moughataa.wilaya`) to be loaded.
 *
 * @mixin ProfessionalProfile
 */
class ProfessionalProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ProfessionalProfile $profile */
        $profile = $this->resource;

        return [
            'sector' => $this->reference($profile->sector),
            'profession' => $this->reference($profile->profession),
            'specialty' => $this->reference($profile->specialty),
            'grade' => $this->reference($profile->grade),
            'wilaya' => $this->reference($profile->moughataa->wilaya),
            'moughataa' => $this->reference($profile->moughataa),
            'establishment' => $this->reference($profile->establishment),
            'professional_identifier' => $profile->professional_identifier,
            'updated_at' => $profile->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{code: string, name_fr: string, name_ar: string, active: bool}|null
     */
    private function reference(?ReferenceEntry $entry): ?array
    {
        return $entry?->toReferenceArray();
    }
}
