<?php

namespace Database\Factories;

use App\Enums\AdPlacement;
use App\Models\Advertisement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Advertisement>
 */
class AdvertisementFactory extends Factory
{
    /**
     * A valid 1x1 PNG, so tests need neither GD nor a fixture file.
     */
    public const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $bytes = (string) base64_decode(self::PNG_BASE64, true);
        $stream = fopen('php://memory', 'r+b');
        fwrite($stream, $bytes);
        rewind($stream);

        return [
            'title' => fake()->sentence(3),
            'link_url' => 'https://example.org/'.fake()->slug(2),
            'placement' => AdPlacement::Home,
            'locale' => null,
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
            // A stream is bound as a LOB, which a PostgreSQL bytea column requires.
            'image_data' => $stream,
            'image_mime_type' => 'image/png',
            'image_checksum' => hash('sha256', $bytes),
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function placement(AdPlacement $placement): static
    {
        return $this->state(['placement' => $placement]);
    }
}
