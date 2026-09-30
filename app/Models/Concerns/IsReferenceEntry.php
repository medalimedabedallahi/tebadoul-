<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Shared behavior of the reference tables (sectors, professions, specialties, grades, wilayas,
 * moughataas, establishments): a stable `code`, explicit French and Arabic names, and an `active`
 * flag. Entries are deactivated rather than deleted.
 *
 * @property int $id
 * @property string $code
 * @property string $name_fr
 * @property string $name_ar
 * @property bool $active
 */
trait IsReferenceEntry
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where($this->qualifyColumn('active'), true);
    }

    public function referenceCode(): string
    {
        return $this->code;
    }

    /**
     * @return array{code: string, name_fr: string, name_ar: string, active: bool}
     */
    public function toReferenceArray(): array
    {
        return [
            'code' => $this->code,
            'name_fr' => $this->name_fr,
            'name_ar' => $this->name_ar,
            'active' => $this->active,
        ];
    }

    /**
     * Name in the current locale: Arabic for `ar`, French otherwise.
     */
    public function localizedName(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name_fr;
    }
}
