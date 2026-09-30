<?php

namespace Database\Factories;

use App\Enums\MobilityRequestStatus;
use App\Models\MobilityRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MobilityRequest>
 */
class MobilityRequestFactory extends Factory
{
    /**
     * Define the model's default state: a draft, available now, valid six months.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => MobilityRequestStatus::Draft,
            'available_from' => now()->toDateString(),
            'expires_at' => now()->addMonths(6)->toDateString(),
        ];
    }

    public function status(MobilityRequestStatus $status): static
    {
        return $this->state(fn (): array => [
            'status' => $status,
            'published_at' => $status === MobilityRequestStatus::Draft ? null : now(),
            'closed_at' => $status === MobilityRequestStatus::Closed ? now() : null,
        ]);
    }

    public function published(): static
    {
        return $this->status(MobilityRequestStatus::Published);
    }
}
