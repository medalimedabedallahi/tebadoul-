<?php

namespace Database\Factories;

use App\Models\Grade;
use App\Models\Moughataa;
use App\Models\Profession;
use App\Models\ProfessionalProfile;
use App\Models\Sector;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds a consistent profile: the profession belongs to the sector, the specialty and the grade
 * to the profession.
 *
 * @extends Factory<ProfessionalProfile>
 */
class ProfessionalProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'sector_id' => Sector::factory(),
            'profession_id' => fn (array $attributes) => Profession::factory()->create(['sector_id' => $attributes['sector_id']]),
            'specialty_id' => fn (array $attributes) => Specialty::factory()->create(['profession_id' => $attributes['profession_id']]),
            'grade_id' => fn (array $attributes) => Grade::factory()->create(['profession_id' => $attributes['profession_id']]),
            'moughataa_id' => Moughataa::factory(),
            'establishment_id' => null,
            'professional_identifier' => null,
        ];
    }
}
