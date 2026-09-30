<?php

namespace Database\Factories;

use App\Models\Moughataa;
use App\Models\Wilaya;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Moughataa>
 */
class MoughataaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wilaya_id' => Wilaya::factory(),
            'code' => fake()->unique()->bothify('mou-####??'),
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
