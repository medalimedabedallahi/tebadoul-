<?php

namespace Database\Factories;

use App\Models\Grade;
use App\Models\Profession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Grade>
 */
class GradeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'profession_id' => Profession::factory(),
            'code' => fake()->unique()->bothify('gra-####??'),
            'name_fr' => ucfirst(fake()->words(2, true)),
            'name_ar' => 'اسم '.fake()->unique()->numerify('####'),
            'active' => true,
        ];
    }

    /**
     * Indicate that the entry has been deactivated.
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'active' => false,
        ]);
    }
}
