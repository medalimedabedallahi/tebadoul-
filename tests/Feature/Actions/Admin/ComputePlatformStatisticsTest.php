<?php

namespace Tests\Feature\Actions\Admin;

use App\Actions\Admin\ComputePlatformStatistics;
use App\Actions\Matching\InviteToMatch;
use App\Actions\Trust\CreateUserReport;
use App\Enums\ReportReason;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMatchedPair;
use Tests\TestCase;

class ComputePlatformStatisticsTest extends TestCase
{
    use CreatesMatchedPair;
    use RefreshDatabase;

    public function test_figures_count_ordinary_accounts_live_requests_matches_reports_and_zones(): void
    {
        [$rosso, , $match] = $this->createMatchedPair();
        app(InviteToMatch::class)->handle($rosso, $match->public_id);
        app(CreateUserReport::class)->handle($rosso, $match->public_id, ReportReason::Spam);
        User::factory()->suspended()->create();
        User::factory()->moderator()->create();
        $administrator = User::factory()->administrator()->create();

        $statistics = app(ComputePlatformStatistics::class)->handle($administrator);

        $this->assertSame(['total' => 3, 'active' => 2, 'pending_verification' => 0, 'suspended' => 1], $statistics['accounts']);
        $this->assertSame(
            ['published' => 2, 'paused' => 0, 'matched' => 0, 'with_match' => 2, 'by_sector' => [['code' => 'education', 'count' => 2]]],
            $statistics['requests'],
        );
        $this->assertSame(1, $statistics['matches']['invited']);
        $this->assertSame(0, $statistics['matches']['suggested']);
        $this->assertSame(0, $statistics['mutual_agreements']);
        $this->assertSame(['pending' => 1, 'resolved' => 0, 'dismissed' => 0], $statistics['reports']);
        $this->assertSame(['origin_wilayas' => 2, 'destination_wilayas' => 2], $statistics['coverage']);
    }

    public function test_expired_requests_no_longer_count_as_active(): void
    {
        $this->createMatchedPair();
        $administrator = User::factory()->administrator()->create();
        $this->travel(7)->months();

        $statistics = app(ComputePlatformStatistics::class)->handle($administrator);

        $this->assertSame(0, $statistics['requests']['published']);
        $this->assertSame(['origin_wilayas' => 0, 'destination_wilayas' => 0], $statistics['coverage']);
    }

    public function test_a_moderator_cannot_read_the_statistics(): void
    {
        $this->expectException(AuthorizationException::class);
        app(ComputePlatformStatistics::class)->handle(User::factory()->moderator()->create());
    }
}
