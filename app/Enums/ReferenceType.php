<?php

namespace App\Enums;

use App\Contracts\ReferenceEntry;
use App\Models\Establishment;
use App\Models\Grade;
use App\Models\Moughataa;
use App\Models\Profession;
use App\Models\Sector;
use App\Models\Specialty;
use App\Models\Wilaya;
use Illuminate\Database\Eloquent\Model;

/**
 * The reference tables, with what their CSV import and their API listing need to know.
 */
enum ReferenceType: string
{
    case Sectors = 'sectors';
    case Wilayas = 'wilayas';
    case Moughataas = 'moughataas';
    case Professions = 'professions';
    case Specialties = 'specialties';
    case Grades = 'grades';
    case Establishments = 'establishments';

    /**
     * @return class-string<Model&ReferenceEntry>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::Sectors => Sector::class,
            self::Wilayas => Wilaya::class,
            self::Moughataas => Moughataa::class,
            self::Professions => Profession::class,
            self::Specialties => Specialty::class,
            self::Grades => Grade::class,
            self::Establishments => Establishment::class,
        };
    }

    /**
     * Parent references, keyed by the CSV column holding the parent's code: the foreign key it
     * fills and the parent's type. Also the relations eager-loaded to expose parent codes.
     *
     * @return array<string, array{foreignKey: string, relation: string, type: self}>
     */
    public function parents(): array
    {
        return match ($this) {
            self::Sectors, self::Wilayas => [],
            self::Moughataas => [
                'wilaya_code' => ['foreignKey' => 'wilaya_id', 'relation' => 'wilaya', 'type' => self::Wilayas],
            ],
            self::Professions => [
                'sector_code' => ['foreignKey' => 'sector_id', 'relation' => 'sector', 'type' => self::Sectors],
            ],
            self::Specialties, self::Grades => [
                'profession_code' => ['foreignKey' => 'profession_id', 'relation' => 'profession', 'type' => self::Professions],
            ],
            self::Establishments => [
                'sector_code' => ['foreignKey' => 'sector_id', 'relation' => 'sector', 'type' => self::Sectors],
                'moughataa_code' => ['foreignKey' => 'moughataa_id', 'relation' => 'moughataa', 'type' => self::Moughataas],
            ],
        };
    }

    /**
     * Columns a CSV file of this type must contain (`active` is optional and defaults to 1).
     *
     * @return list<string>
     */
    public function requiredColumns(): array
    {
        return [
            'code',
            ...array_keys($this->parents()),
            ...($this === self::Establishments ? ['type'] : []),
            'name_fr',
            'name_ar',
        ];
    }
}
