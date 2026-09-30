<?php

namespace Tests\Feature\Actions\Admin;

use App\Actions\Admin\ListAuditLogs;
use App\Actions\Auth\ReinstateUser;
use App\Actions\Auth\SuspendUser;
use App\Enums\AuditAction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListAuditLogsTest extends TestCase
{
    use RefreshDatabase;

    public function test_entries_are_listed_newest_first_and_filtered_by_action_or_account(): void
    {
        $administrator = User::factory()->administrator()->create();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $this->travelTo(now()->subDay());
        app(SuspendUser::class)->handle($administrator, $first->public_id, 'Fraude.');
        $this->travelBack();
        app(ReinstateUser::class)->handle($administrator, $first->public_id, 'Erreur corrigée.');
        app(SuspendUser::class)->handle($administrator, $second->public_id, 'Harcèlement.');

        $all = app(ListAuditLogs::class)->handle($administrator);
        $suspensions = app(ListAuditLogs::class)->handle($administrator, AuditAction::UserSuspended);
        $aboutFirst = app(ListAuditLogs::class)->handle($administrator, null, strtoupper($first->public_id));

        $this->assertSame(['Harcèlement.', 'Erreur corrigée.', 'Fraude.'], $all->pluck('reason')->all());
        $this->assertSame(['Harcèlement.', 'Fraude.'], $suspensions->pluck('reason')->all());
        $this->assertSame(['Erreur corrigée.', 'Fraude.'], $aboutFirst->pluck('reason')->all());
    }

    public function test_a_moderator_cannot_read_the_audit_trail(): void
    {
        $this->expectException(AuthorizationException::class);
        app(ListAuditLogs::class)->handle(User::factory()->moderator()->create());
    }
}
