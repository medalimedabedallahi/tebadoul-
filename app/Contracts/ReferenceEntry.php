<?php

namespace App\Contracts;

use App\Models\Concerns\IsReferenceEntry;

/**
 * A reference entry (sector, profession, specialty, grade, wilaya, moughataa, establishment),
 * implemented through {@see IsReferenceEntry}.
 */
interface ReferenceEntry
{
    /**
     * Stable code of the entry, used by imports and exposed by the API.
     */
    public function referenceCode(): string;

    /**
     * Name in the current locale.
     */
    public function localizedName(): string;

    /**
     * @return array{code: string, name_fr: string, name_ar: string, active: bool}
     */
    public function toReferenceArray(): array;
}
