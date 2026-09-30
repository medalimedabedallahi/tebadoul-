<?php

namespace Tests\Feature\Livewire\Profile;

use App\Actions\Profiles\SaveProfessionalProfile;
use App\Livewire\Profile\Edit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesReferenceData;
use Tests\TestCase;

class EditTest extends TestCase
{
    use CreatesReferenceData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createReferenceData();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
    }

    public function test_renders_the_page_with_the_saved_profile(): void
    {
        $user = User::factory()->create();
        app(SaveProfessionalProfile::class)->handle($user, $this->teacherProfileInput());

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee(__('profile.heading'))
            ->assertSee('MAT-12345');
    }

    public function test_fills_the_dependent_lists_and_saves_the_profile(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Edit::class)
            ->set('sector', 'education')
            ->assertViewHas('professions', fn (array $professions): bool => array_keys($professions) === ['teacher'])
            ->set('profession', 'teacher')
            ->assertViewHas('specialties', fn (array $specialties): bool => array_keys($specialties) === ['maths'])
            ->set('specialty', 'maths')
            ->set('grade', 'grade-a')
            ->set('wilaya', '06')
            ->set('moughataa', '0606')
            ->assertViewHas('establishments', fn (array $establishments): bool => array_keys($establishments) === ['lycee-rosso'])
            ->set('establishment', 'lycee-rosso')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('profileSaved', __('profile.saved'));

        $this->assertSame('lycee-rosso', $user->professionalProfile?->establishment?->code);
    }

    public function test_changing_a_parent_resets_its_dependent_choices(): void
    {
        $user = User::factory()->create();
        app(SaveProfessionalProfile::class)->handle($user, $this->teacherProfileInput());

        Livewire::actingAs($user)
            ->test(Edit::class)
            ->assertSet('profession', 'teacher')
            ->assertSet('establishment', 'lycee-rosso')
            ->set('sector', 'health')
            ->assertSet('profession', '')
            ->assertSet('specialty', '')
            ->assertSet('establishment', '')
            ->set('wilaya', '01')
            ->assertSet('moughataa', '');
    }

    public function test_shows_validation_errors_next_to_the_fields(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(Edit::class)
            ->set('sector', 'education')
            ->set('profession', 'teacher')
            ->set('wilaya', '06')
            ->set('moughataa', '0606')
            ->call('save')
            ->assertHasErrors(['specialty', 'grade']);

        $this->assertDatabaseCount('professional_profiles', 0);
    }

    public function test_a_successful_save_clears_the_errors_of_a_previous_attempt(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(Edit::class)
            ->set('sector', 'education')
            ->set('profession', 'teacher')
            ->set('wilaya', '06')
            ->set('moughataa', '0606')
            ->call('save')
            ->assertHasErrors(['specialty', 'grade'])
            ->set('specialty', 'maths')
            ->set('grade', 'grade-a')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('profileSaved', __('profile.saved'));
    }
}
