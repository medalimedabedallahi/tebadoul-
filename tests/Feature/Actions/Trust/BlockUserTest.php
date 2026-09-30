<?php

namespace Tests\Feature\Actions\Trust;

use App\Actions\Matching\AcceptMatch;
use App\Actions\Matching\InviteToMatch;
use App\Actions\Matching\ShareContact;
use App\Actions\Trust\BlockUser;
use App\Actions\Trust\UnblockUser;
use App\Enums\MatchStatus;
use App\Enums\MobilityRequestStatus;
use App\Models\UserBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMatchedPair;
use Tests\TestCase;

class BlockUserTest extends TestCase
{
    use CreatesMatchedPair;
    use RefreshDatabase;

    public function test_blocking_ends_the_agreement_and_revokes_contact_access(): void
    {
        [$rosso, $hodh, $match] = $this->createMatchedPair();
        app(InviteToMatch::class)->handle($rosso, $match->public_id);
        app(AcceptMatch::class)->handle($hodh, $match->public_id);
        app(ShareContact::class)->grant($rosso, $match->public_id);
        app(ShareContact::class)->grant($hodh, $match->public_id);

        app(BlockUser::class)->handle($rosso, $match->public_id);

        $this->assertTrue(UserBlock::existsBetween($rosso->id, $hodh->id));
        $this->assertSame(MatchStatus::Invalidated, $match->refresh()->status);
        $this->assertSame('blocked', $match->invalidation_reason);
        $this->assertSame(MobilityRequestStatus::Published, $rosso->mobilityRequests()->sole()->status);
        $this->assertSame(MobilityRequestStatus::Published, $hodh->mobilityRequests()->sole()->status);
        $this->assertSame(0, $match->participants()->whereNotNull('contact_consented_at')->count());
    }

    public function test_unblocking_removes_only_the_actors_directional_block(): void
    {
        [$rosso, $hodh, $match] = $this->createMatchedPair();
        app(BlockUser::class)->handle($rosso, $match->public_id);

        app(UnblockUser::class)->handle($rosso, $match->public_id);

        $this->assertFalse(UserBlock::existsBetween($rosso->id, $hodh->id));
        $this->assertDatabaseMissing('user_blocks', ['blocker_id' => $rosso->id, 'blocked_id' => $hodh->id]);
    }
}
