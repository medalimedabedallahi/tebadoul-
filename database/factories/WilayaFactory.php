<?php

namespace Database\Factories;

use App\Models\Wilaya;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wilaya>
 */
class WilayaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('wil-####??'),
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
