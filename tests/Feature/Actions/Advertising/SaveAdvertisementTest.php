<?php

namespace Tests\Feature\Actions\Advertising;

use App\Actions\Advertising\SaveAdvertisement;
use App\Enums\AdPlacement;
use App\Models\Advertisement;
use App\Models\User;
use Database\Factories\AdvertisementFactory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SaveAdvertisementTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_an_advertisement_with_its_banner_stored_in_the_row(): void
    {
        $administrator = User::factory()->administrator()->create();
        $bytes = $this->pngBytes();

        $advertisement = app(SaveAdvertisement::class)->handle($administrator, $this->input([
            'link_url' => ' https://example.org/offre ',
            'locale' => '',
            'starts_at' => '',
            'ends_at' => '2026-12-31T18:00',
        ]));

        $stored = Advertisement::query()->sole();
        $this->assertSame($advertisement->public_id, $stored->public_id);
        $this->assertSame('Formation continue', $stored->title);
        $this->assertSame('https://example.org/offre', $stored->link_url);
        $this->assertSame(AdPlacement::Home, $stored->placement);
        $this->assertNull($stored->locale);
        $this->assertNull($stored->starts_at);
        $this->assertSame('2026-12-31 18:00:00', $stored->ends_at?->toDateTimeString());
        $this->assertSame('image/png', $stored->image_mime_type);
        $this->assertSame(hash('sha256', $bytes), $stored->image_checksum);
        $this->assertSame($bytes, $stored->imageBytes());
    }

    public function test_an_update_keeps_the_banner_unless_a_new_image_is_sent(): void
    {
        $administrator = User::factory()->administrator()->create();
        $advertisement = Advertisement::factory()->create();
        $checksum = $advertisement->image_checksum;

        app(SaveAdvertisement::class)->handle($administrator, $this->input([
            'title' => 'Nouveau titre',
            'placement' => 'footer',
            'is_active' => false,
            'image' => null,
        ]), $advertisement->public_id);

        $advertisement->refresh();
        $this->assertSame('Nouveau titre', $advertisement->title);
        $this->assertSame(AdPlacement::Footer, $advertisement->placement);
        $this->assertFalse($advertisement->is_active);
        $this->assertSame($checksum, $advertisement->image_checksum);

        // Bytes after the IEND chunk keep a valid PNG with another checksum.
        $replacement = $this->pngBytes().'v2';
        app(SaveAdvertisement::class)->handle($administrator, $this->input(['image' => UploadedFile::fake()->createWithContent('banner.png', $replacement)]), $advertisement->public_id);

        $advertisement->refresh();
        $this->assertSame(hash('sha256', $replacement), $advertisement->image_checksum);
        $this->assertSame($replacement, $advertisement->imageBytes());
    }

    public function test_only_active_administrators_manage_advertisements(): void
    {
        foreach ([User::factory()->create(), User::factory()->moderator()->create(), User::factory()->administrator()->suspended()->create()] as $actor) {
            try {
                app(SaveAdvertisement::class)->handle($actor, $this->input());
                $this->fail('A non-administrator created an advertisement.');
            } catch (AuthorizationException) {
            }
        }

        $advertisement = Advertisement::factory()->create();

        $this->expectException(AuthorizationException::class);
        app(SaveAdvertisement::class)->handle(User::factory()->moderator()->create(), $this->input(['image' => null]), $advertisement->public_id);
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('invalidInput')]
    public function test_rejects_invalid_input(array $override, string $field): void
    {
        try {
            app(SaveAdvertisement::class)->handle(User::factory()->administrator()->create(), $this->input($override));
            $this->fail('The invalid input was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }

        $this->assertDatabaseCount('advertisements', 0);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidInput(): array
    {
        return [
            'missing image' => [['image' => null], 'image'],
            'svg image' => [['image' => UploadedFile::fake()->createWithContent('banner.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')], 'image'],
            'not an image' => [['image' => UploadedFile::fake()->createWithContent('banner.png', 'plain text')], 'image'],
            'oversized image' => [['image' => UploadedFile::fake()->create('banner.png', SaveAdvertisement::MAX_IMAGE_KILOBYTES + 1, 'image/png')], 'image'],
            'javascript link' => [['link_url' => 'javascript:alert(1)'], 'link_url'],
            'unknown placement' => [['placement' => 'sidebar'], 'placement'],
            'unknown locale' => [['locale' => 'en'], 'locale'],
            'end before start' => [['starts_at' => '2026-11-02T10:00', 'ends_at' => '2026-11-01T10:00'], 'ends_at'],
            'missing title' => [['title' => ''], 'title'],
        ];
    }

    /**
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function input(array $override = []): array
    {
        return array_merge([
            'title' => 'Formation continue',
            'link_url' => 'https://example.org',
            'placement' => 'home',
            'locale' => 'fr',
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
            'image' => UploadedFile::fake()->createWithContent('banner.png', $this->pngBytes()),
        ], $override);
    }

    private function pngBytes(): string
    {
        return (string) base64_decode(AdvertisementFactory::PNG_BASE64, true);
    }
}
