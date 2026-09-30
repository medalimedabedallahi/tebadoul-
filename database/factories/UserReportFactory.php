<?php

namespace Database\Factories;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\MobilityMatch;
use App\Models\User;
use App\Models\UserReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserReport>
 */
class UserReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'match_id' => MobilityMatch::factory(),
            'reporter_id' => User::factory(),
            'reported_user_id' => User::factory(),
            'reason' => ReportReason::Harassment,
            'details' => fake()->sentence(),
            'status' => ReportStatus::Pending,
            'moderator_id' => null,
            'resolution_note' => null,
            'reviewed_at' => null,
        ];
    }
}
