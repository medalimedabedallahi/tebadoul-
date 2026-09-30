<?php

namespace Tests\Feature\Livewire\Admin;

use App\Actions\Auth\SuspendUser;
use App\Livewire\Admin\AuditLogs\Index as AuditLogsIndex;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesMatchedPair;
use Tests\TestCase;

class AdministrationPagesTest extends TestCase
{
    use CreatesMatchedPair;
    use RefreshDatabase;

    public function test_only_administrators_open_the_statistics_and_audit_pages(): void
    {
        $this->get(route('admin.statistics.show'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->moderator()->create())->get(route('admin.statistics.show'))->assertForbidden();
        $this->actingAs(User::factory()->moderator()->create())->get(route('admin.audit-logs.index'))->assertForbidden();
        $this->actingAs(User::factory()->administrator()->create())->get(route('admin.audit-logs.index'))->assertOk();
    }

    public function test_the_statistics_page_shows_the_figures_with_localized_sector_names(): void
    {
        $this->createMatchedPair();
        $sector = Sector::query()->where('code', 'education')->sole();

        $this->actingAs(User::factory()->administrator()->create())->get(route('admin.statistics.show'))
            ->assertOk()
            ->assertSee(__('admin.statistics.requests.published'))
            ->assertSee($sector->name_fr)
            ->assertSee(__('admin.statistics.match_rate', ['rate' => 100]));
    }

    public function test_the_audit_page_escapes_reasons_and_filters_by_account(): void
    {
        $administrator = User::factory()->administrator()->create();
        $first = User::factory()->create();
        $second = User::factory()->create();
        app(SuspendUser::class)->handle($administrator, $first->public_id, '<script>alert(1)</script>');
        app(SuspendUser::class)->handle($administrator, $second->public_id, 'Autre motif.');

        Livewire::actingAs($administrator)
            ->test(AuditLogsIndex::class)
            ->assertSee('<script>alert(1)</script>')
            ->assertDontSeeHtml('<script>alert(1)</script>')
            ->set('targetInput', $second->public_id)
            ->call('applyTarget')
            ->assertSee('Autre motif.')
            ->assertDontSee('alert(1)')
            ->set('targetInput', 'pas-un-ulid')
            ->call('applyTarget')
            ->assertSee(__('admin.users.invalid_search_title'));
    }
}
