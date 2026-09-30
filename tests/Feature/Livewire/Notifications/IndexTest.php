<?php

namespace Tests\Feature\Livewire\Notifications;

use App\Actions\Matching\InviteToMatch;
use App\Livewire\Notifications\Index;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesMatchedPair;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use CreatesMatchedPair;
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
    }

    public function test_the_page_and_the_menu_show_the_unread_notifications(): void
    {
        [$rosso, $hodh, $match] = $this->createMatchedPair();
        app(InviteToMatch::class)->handle($rosso, $match->public_id);

        $this->actingAs($hodh)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee(__('notifications.types.invitation_received'))
            ->assertSee(__('notifications.types.match_suggested'))
            ->assertSee(__('notifications.nav_unread', ['count' => 2]));
    }

    public function test_opening_a_notification_marks_it_read_and_goes_to_the_match(): void
    {
        [$rosso, $hodh, $match] = $this->createMatchedPair();
        $notification = $hodh->notifications()->sole();

        Livewire::actingAs($hodh)
            ->test(Index::class)
            ->call('open', $notification->id)
            ->assertRedirect(route('matches.show', $match->public_id));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_all_read_empties_the_unread_filter(): void
    {
        [$rosso, $hodh, $match] = $this->createMatchedPair();
        app(InviteToMatch::class)->handle($rosso, $match->public_id);

        Livewire::actingAs($hodh)
            ->test(Index::class)
            ->set('filter', 'unread')
            ->assertSee(__('notifications.types.invitation_received'))
            ->call('markAllRead')
            ->assertSet('allMarked', __('notifications.all_marked'))
            ->assertSee(__('notifications.empty_title'));

        $this->assertSame(0, $hodh->unreadNotifications()->count());
    }
}
