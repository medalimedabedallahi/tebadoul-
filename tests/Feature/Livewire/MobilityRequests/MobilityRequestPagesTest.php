<?php

namespace Tests\Feature\Livewire\MobilityRequests;

use App\Actions\Profiles\SaveProfessionalProfile;
use App\Enums\MobilityRequestStatus;
use App\Livewire\MobilityRequests\Edit;
use App\Livewire\MobilityRequests\Show;
use App\Models\MobilityRequest;
use App\Models\User;
use App\Models\Wilaya;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesReferenceData;
use Tests\TestCase;

class MobilityRequestPagesTest extends TestCase
{
    use CreatesReferenceData;
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-01 10:00:00');
        $this->createReferenceData();
        $this->user = User::factory()->create();
        app(SaveProfessionalProfile::class)->handle($this->user, $this->teacherProfileInput());
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get(route('requests.index'))->assertRedirect(route('login'));
    }

    public function test_lists_only_the_requests_of_the_account(): void
    {
        MobilityRequest::factory()->for($this->user)->create();
        MobilityRequest::factory()->create();

        $this->actingAs($this->user)->get(route('requests.index'))
            ->assertOk()
            ->assertSee(__('requests.status.draft'))
            ->assertSee(route('requests.show', $this->user->mobilityRequests()->sole()->public_id))
            ->assertDontSee(__('requests.index.profile_missing'));
    }

    public function test_creates_a_request_with_dependent_destination_lists(): void
    {
        Livewire::actingAs($this->user)
            ->test(Edit::class)
            ->assertSet('available_from', '2026-10-01')
            ->set('destinations.0.wilaya', '06')
            ->assertViewHas('moughataas', fn (array $moughataas): bool => array_keys($moughataas[0]) === ['0606'])
            ->set('destinations.0.moughataa', '0606')
            ->set('destinations.0.wilaya', '01')
            ->assertSet('destinations.0.moughataa', '')
            ->call('addDestination')
            ->set('destinations.1.wilaya', '06')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('requests.show', $this->user->mobilityRequests()->sole()->public_id));

        $request = $this->user->mobilityRequests()->sole();
        $this->assertSame(MobilityRequestStatus::Draft, $request->status);
        $this->assertSame(['01', '06'], $request->destinations()->with('wilaya')->get()->map(fn ($destination) => $destination->wilaya->code)->all());
    }

    public function test_shows_destination_errors_on_their_row(): void
    {
        Livewire::actingAs($this->user)
            ->test(Edit::class)
            ->set('destinations.0.wilaya', '06')
            ->set('destinations.0.moughataa', '0606')
            ->call('save')
            ->assertHasErrors(['destinations.0.moughataa']);

        $this->assertSame(0, MobilityRequest::query()->count());
    }

    public function test_a_request_of_another_account_is_not_found(): void
    {
        $foreign = MobilityRequest::factory()->create();

        $this->actingAs($this->user)->get(route('requests.show', $foreign->public_id))->assertNotFound();
        $this->actingAs($this->user)->get(route('requests.edit', $foreign->public_id))->assertNotFound();
    }

    public function test_publishes_pauses_and_closes_from_the_request_page(): void
    {
        $request = MobilityRequest::factory()->for($this->user)->create();
        $request->destinations()->create(['priority' => 1, 'wilaya_id' => Wilaya::query()->where('code', '01')->value('id')]);

        $page = Livewire::actingAs($this->user)
            ->test(Show::class, ['mobilityRequest' => strtoupper($request->public_id)])
            ->call('publish')
            ->assertSet('actionErrors', [])
            ->assertSet('actionMessage', __('requests.show.published'))
            ->call('pause')
            ->assertSet('actionMessage', __('requests.show.paused'))
            ->call('close')
            ->assertHasErrors(['reason'])
            ->set('closeReason', 'permutation_completed')
            ->call('close')
            ->assertHasNoErrors()
            ->assertSet('actionMessage', __('requests.show.closed'));

        $this->assertSame(MobilityRequestStatus::Closed, $request->fresh()->status);

        $page->call('delete')->assertRedirect(route('requests.index'));
        $this->assertSoftDeleted($request);
    }

    public function test_shows_why_a_request_cannot_be_published(): void
    {
        $request = MobilityRequest::factory()->for($this->user)->create();
        MobilityRequest::factory()->for($this->user)->published()->create();

        Livewire::actingAs($this->user)
            ->test(Show::class, ['mobilityRequest' => $request->public_id])
            ->call('publish')
            ->assertSet('actionMessage', null)
            ->assertSee(__('requests.errors.destinations_required'))
            ->assertSee(__('requests.errors.active_request_exists'));

        $this->assertSame(MobilityRequestStatus::Draft, $request->fresh()->status);
    }
}
