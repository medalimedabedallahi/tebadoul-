<?php

namespace Database\Factories;

use App\Enums\MatchStatus;
use App\Models\MobilityMatch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A bare match row, without participants: tests of the engine create matches through it.
 *
 * @extends Factory<MobilityMatch>
 */
class MobilityMatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'direct',
            'pair_key' => (string) Str::ulid(),
            'status' => MatchStatus::Suggested,
            'score' => 50,
            'rules_version' => 'v1-strict',
        ];
    }
}
