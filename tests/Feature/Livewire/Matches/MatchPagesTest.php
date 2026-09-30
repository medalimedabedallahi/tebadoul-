<?php

namespace Tests\Feature\Livewire\Matches;

use App\Actions\Matching\AcceptMatch;
use App\Actions\Matching\InviteToMatch;
use App\Enums\MatchStatus;
use App\Livewire\Matches\Show;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesMatchedPair;
use Tests\TestCase;

class MatchPagesTest extends TestCase
{
    use CreatesMatchedPair;
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get(route('matches.index'))->assertRedirect(route('login'));
    }

    public function test_lists_and_explains_the_matches_of_the_account(): void
    {
        [$rosso, $hodh, $match] = $this->createMatchedPair();
        $rosso->forceFill(['name' => 'Aminetou Sy'])->save();

        $this->actingAs($hodh)->get(route('matches.index'))
            ->assertOk()
            ->assertSee(__('matches.index.score', ['score' => $match->score]))
            ->assertSee(route('matches.show', $match->public_id))
            ->assertDontSee(__('matches.index.disabled'));

        $this->actingAs($hodh)->get(route('matches.show', $match->public_id))
            ->assertOk()
            ->assertSee(__('matches.criteria.destination'))
            ->assertSee(__('matches.details.destination.exact_one'))
            ->assertSee(__('matches.show.score_total', ['score' => $match->score]))
            ->assertDontSee('Aminetou Sy');

        $this->actingAs($hodh)->get(route('requests.show', $hodh->mobilityRequests()->sole()->public_id))
            ->assertOk()
            ->assertSee(trans_choice('requests.show.matches_found', 1, ['count' => 1]));
    }

    public function test_shows_why_a_match_was_invalidated(): void
    {
        [, $hodh, $match] = $this->createMatchedPair();
        $match->forceFill(['status' => MatchStatus::Invalidated, 'invalidation_reason' => 'availability'])->save();

        $this->actingAs($hodh)->get(route('matches.show', $match->public_id))
            ->assertOk()
            ->assertSee(__('matches.invalidation_reasons.availability'));
    }

    public function test_a_match_of_other_accounts_is_not_found_and_disabled_matching_is_explained(): void
    {
        [, , $match] = $this->createMatchedPair();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->get(route('matches.show', $match->public_id))->assertNotFound();

        config(['matching.enabled' => false]);
        $this->actingAs($outsider)->get(route('matches.index'))
            ->assertOk()
            ->assertSee(__('matches.index.disabled'))
            ->assertSee(__('matches.index.empty_title'));
    }

    public function test_participants_can_invite_accept_and_share_contacts_from_the_page(): void
    {
        [$rosso, $hodh, $match] = $this->createMatchedPair();

        Livewire::actingAs($rosso)
            ->test(Show::class, ['match' => $match->public_id])
            ->assertSee(__('matches.actions.invite'))
            ->call('invite')
            ->assertSet('actionMessage', __('matches.messages.invited'))
            ->assertSee(__('matches.actions.withdraw'));

        Livewire::actingAs($hodh)
            ->test(Show::class, ['match' => $match->public_id])
            ->assertSee(__('matches.actions.accept'))
            ->call('accept')
            ->assertSet('actionMessage', __('matches.messages.accepted'))
            ->call('grantContactConsent');

        Livewire::actingAs($rosso)
            ->test(Show::class, ['match' => $match->public_id])
            ->call('grantContactConsent')
            ->call('revealContact')
            ->assertSet('contact.email', $hodh->email)
            ->assertSee($hodh->email)
            ->call('revokeContactConsent')
            ->assertSet('contact', null);
    }

    public function test_participants_can_message_report_and_block_from_the_page(): void
    {
        [$rosso, $hodh, $match] = $this->createMatchedPair();
        app(InviteToMatch::class)->handle($rosso, $match->public_id);
        app(AcceptMatch::class)->handle($hodh, $match->public_id);

        Livewire::actingAs($rosso)
            ->test(Show::class, ['match' => $match->public_id])
            ->set('messageBody', '<script>alert(1)</script>')
            ->call('sendMessage')
            ->assertHasNoErrors()
            ->assertSet('actionMessage', __('matches.messages.message_sent'))
            ->assertSee('<script>alert(1)</script>')
            ->assertDontSeeHtml('<script>alert(1)</script>')
            ->set('reportReason', 'spam')
            ->set('reportDetails', 'Messages répétés.')
            ->call('report')
            ->assertSet('actionMessage', __('matches.messages.reported'))
            ->call('block')
            ->assertSet('actionMessage', __('matches.messages.blocked'))
            ->assertSee(__('matches.actions.unblock'));

        $this->assertDatabaseHas('user_reports', ['reporter_id' => $rosso->id, 'reported_user_id' => $hodh->id]);
        $this->assertDatabaseHas('user_blocks', ['blocker_id' => $rosso->id, 'blocked_id' => $hodh->id]);
    }
}
