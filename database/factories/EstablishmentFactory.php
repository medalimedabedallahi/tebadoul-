<?php

namespace Database\Factories;

use App\Models\Establishment;
use App\Models\Moughataa;
use App\Models\Sector;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Establishment>
 */
class EstablishmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sector_id' => Sector::factory(),
            'moughataa_id' => Moughataa::factory(),
            'type' => fake()->randomElement(['school', 'health_center']),
            'code' => fake()->unique()->bothify('est-####??'),
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
