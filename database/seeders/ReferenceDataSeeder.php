<?php

namespace Database\Seeders;

use App\Actions\References\ImportReferences;
use App\Enums\ReferenceType;
use Illuminate\Database\Seeder;

/**
 * Imports the reference data shipped with the application (`database/data/references`): the two
 * sectors and the administrative geography of Mauritania. Idempotent, so it can also run in
 * production (`php artisan db:seed --class=ReferenceDataSeeder --force`).
 *
 * Professions, specialties, grades and establishments are not shipped: they await validation by
 * the ministries and are loaded with `php artisan references:import`.
 */
class ReferenceDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(ImportReferences $import): void
    {
        foreach ([ReferenceType::Sectors, ReferenceType::Wilayas, ReferenceType::Moughataas] as $type) {
            $import->handle($type, database_path("data/references/{$type->value}.csv"));
        }
    }
}
