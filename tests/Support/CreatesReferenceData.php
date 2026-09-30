<?php

namespace Tests\Support;

use App\Models\Establishment;
use App\Models\Grade;
use App\Models\Moughataa;
use App\Models\Profession;
use App\Models\Sector;
use App\Models\Specialty;
use App\Models\Wilaya;

/**
 * A small, consistent set of reference entries, with fixed codes, for profile tests:
 * sector `education` > profession `teacher` > specialty `maths` and grade `grade-a`,
 * wilaya `06` > moughataa `0606` > establishment `lycee-rosso`, plus a `health` sector with the
 * profession `nurse` (no specialty, no grade) and a moughataa `0101` in wilaya `01`.
 */
trait CreatesReferenceData
{
    protected function createReferenceData(): void
    {
        $education = Sector::factory()->create(['code' => 'education']);
        $health = Sector::factory()->create(['code' => 'health']);
        $teacher = Profession::factory()->for($education)->create(['code' => 'teacher']);
        Profession::factory()->for($health)->create(['code' => 'nurse']);
        Specialty::factory()->for($teacher)->create(['code' => 'maths']);
        Grade::factory()->for($teacher)->create(['code' => 'grade-a']);

        $trarza = Wilaya::factory()->create(['code' => '06']);
        $rosso = Moughataa::factory()->for($trarza)->create(['code' => '0606']);
        Moughataa::factory()->for(Wilaya::factory()->create(['code' => '01']))->create(['code' => '0101']);
        Establishment::factory()->for($education)->for($rosso)->create(['code' => 'lycee-rosso']);
    }

    /**
     * A valid teacher profile input, with `$overrides` applied.
     *
     * @param  array<string, string|null>  $overrides
     * @return array<string, string|null>
     */
    protected function teacherProfileInput(array $overrides = []): array
    {
        return [
            'sector' => 'education',
            'profession' => 'teacher',
            'specialty' => 'maths',
            'grade' => 'grade-a',
            'wilaya' => '06',
            'moughataa' => '0606',
            'establishment' => 'lycee-rosso',
            'professional_identifier' => 'MAT-12345',
            ...$overrides,
        ];
    }
}
