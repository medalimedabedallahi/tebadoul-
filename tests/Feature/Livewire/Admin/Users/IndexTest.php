<?php

namespace Tests\Feature\Livewire\Admin\Users;

use App\Enums\AuditAction;
use App\Enums\UserStatus;
use App\Livewire\Admin\Users\Index;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.users.index'))->assertRedirect(route('login'));
    }

    public function test_non_administrators_are_refused(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs(User::factory()->moderator()->create())->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_lists_ordinary_accounts_by_public_id_and_status_only(): void
    {
        $administrator = User::factory()->administrator()->create();
        $user = User::factory()->create(['name' => 'Aminetou Sy', 'email' => 'aminetou@example.com']);
        $moderator = User::factory()->moderator()->create();

        $response = $this->actingAs($administrator)->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertSee($user->public_id);
        $response->assertDontSee($moderator->public_id);
        $response->assertDontSee($administrator->public_id);
        $response->assertDontSee('Aminetou Sy');
        $response->assertDontSee('aminetou@example.com');
    }

    public function test_searches_by_exact_public_id_and_flags_an_invalid_one(): void
    {
        $administrator = User::factory()->administrator()->create();
        $wanted = User::factory()->create();
        $other = User::factory()->create();

        Livewire::actingAs($administrator)
            ->test(Index::class)
            ->set('search', " {$wanted->public_id} ")
            ->call('applySearch')
            ->assertSee($wanted->public_id)
            ->assertDontSee($other->public_id)
            ->set('search', 'not-a-ulid')
            ->call('applySearch')
            ->assertSee(__('admin.users.invalid_search_title'))
            ->assertDontSee($wanted->public_id);
    }

    public function test_suspends_then_reinstates_an_account_with_audited_reasons(): void
    {
        $administrator = User::factory()->administrator()->create();
        $user = User::factory()->create();

        $component = Livewire::actingAs($administrator)
            ->test(Index::class)
            ->call('startDecision', $user->public_id, 'suspend')
            ->set('reason', 'Repeated abusive messages.')
            ->call('confirmDecision')
            ->assertHasNoErrors()
            ->assertSet('decisionRecorded', __('admin.users.suspend_done'))
            ->assertSet('targetPublicId', null);

        $this->assertSame(UserStatus::Suspended, $user->fresh()->status);

        $component
            ->call('startDecision', $user->public_id, 'reinstate')
            ->set('reason', 'Appeal accepted.')
            ->call('confirmDecision')
            ->assertHasNoErrors()
            ->assertSet('decisionRecorded', __('admin.users.reinstate_done'));

        $this->assertSame(UserStatus::Active, $user->fresh()->status);
        $this->assertSame(
            [AuditAction::UserSuspended, AuditAction::UserReinstated],
            AuditLog::query()->orderBy('id')->pluck('action')->all(),
        );
    }

    public function test_a_decision_requires_a_reason(): void
    {
        $administrator = User::factory()->administrator()->create();
        $user = User::factory()->create();

        Livewire::actingAs($administrator)
            ->test(Index::class)
            ->call('startDecision', $user->public_id, 'suspend')
            ->set('reason', '  ')
            ->call('confirmDecision')
            ->assertHasErrors(['reason' => 'required']);

        $this->assertSame(UserStatus::Active, $user->fresh()->status);
    }

    public function test_reinstating_an_account_that_is_no_longer_suspended_shows_an_error(): void
    {
        $administrator = User::factory()->administrator()->create();
        $user = User::factory()->create();

        Livewire::actingAs($administrator)
            ->test(Index::class)
            ->set('targetPublicId', $user->public_id)
            ->set('decision', 'reinstate')
            ->set('reason', 'Stale page.')
            ->call('confirmDecision')
            ->assertHasErrors(['reason']);

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_the_action_authorizes_again_a_tampered_target(): void
    {
        $administrator = User::factory()->administrator()->create();
        $other = User::factory()->administrator()->create();

        Livewire::actingAs($administrator)
            ->test(Index::class)
            ->set('targetPublicId', $other->public_id)
            ->set('decision', 'suspend')
            ->set('reason', 'Tampered target.')
            ->call('confirmDecision')
            ->assertForbidden();

        $this->assertSame(UserStatus::Active, $other->fresh()->status);
    }
}
