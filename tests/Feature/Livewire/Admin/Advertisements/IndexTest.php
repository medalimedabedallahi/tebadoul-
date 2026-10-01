<?php

namespace Tests\Feature\Livewire\Admin\Advertisements;

use App\Enums\AdPlacement;
use App\Livewire\Admin\Advertisements\Index;
use App\Models\Advertisement;
use App\Models\User;
use Database\Factories\AdvertisementFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_administrator_opens_the_page_and_sees_every_advertisement(): void
    {
        Advertisement::factory()->create(['title' => 'Salon de l’emploi']);
        Advertisement::factory()->inactive()->create(['title' => 'Ancienne campagne']);

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('admin.advertisements.index'))
            ->assertOk()
            ->assertSee('Salon de l’emploi')
            ->assertSee('Ancienne campagne')
            ->assertSee(__('admin.advertisements.states.inactive'));
    }

    public function test_creates_an_advertisement_from_an_uploaded_banner(): void
    {
        Livewire::actingAs(User::factory()->administrator()->create())
            ->test(Index::class)
            ->call('create')
            ->set('title', 'Formation continue')
            ->set('link_url', 'https://example.org')
            ->set('placement', AdPlacement::Footer->value)
            ->set('locale', 'ar')
            ->set('image', UploadedFile::fake()->createWithContent('banner.png', (string) base64_decode(AdvertisementFactory::PNG_BASE64, true)))
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('editing', null)
            ->assertSet('notice', __('admin.advertisements.created'))
            ->assertSee('Formation continue');

        $advertisement = Advertisement::query()->sole();
        $this->assertSame(AdPlacement::Footer, $advertisement->placement);
        $this->assertSame('ar', $advertisement->locale);
    }

    public function test_shows_validation_errors_on_the_form(): void
    {
        Livewire::actingAs(User::factory()->administrator()->create())
            ->test(Index::class)
            ->call('create')
            ->set('link_url', 'pas une adresse')
            ->call('save')
            ->assertHasErrors(['title', 'link_url', 'image'])
            ->assertSet('editing', 'new');

        $this->assertDatabaseCount('advertisements', 0);
    }

    public function test_edits_then_deletes_an_advertisement_after_confirmation(): void
    {
        $advertisement = Advertisement::factory()->create(['title' => 'Avant', 'ends_at' => '2026-12-31 18:00:00']);

        $component = Livewire::actingAs(User::factory()->administrator()->create())
            ->test(Index::class)
            ->call('edit', $advertisement->public_id)
            ->assertSet('title', 'Avant')
            ->assertSet('ends_at', '2026-12-31T18:00')
            ->set('title', 'Après')
            ->set('is_active', false)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('notice', __('admin.advertisements.updated'));

        $this->assertSame('Après', $advertisement->fresh()->title);
        $this->assertFalse($advertisement->fresh()->is_active);

        $component->call('confirmDelete', $advertisement->public_id)
            ->assertSee(__('admin.advertisements.delete_heading'));
        $this->assertModelExists($advertisement);

        $component->call('delete')
            ->assertSet('deleting', null)
            ->assertSet('notice', __('admin.advertisements.deleted'));
        $this->assertModelMissing($advertisement);
    }
}
