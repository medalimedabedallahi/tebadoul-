<?php

namespace Tests\Feature\Actions\Matching;

use App\Actions\Auth\SuspendUser;
use App\Actions\Matching\ComputeDirectMatches;
use App\Actions\MobilityRequests\PauseMobilityRequest;
use App\Actions\MobilityRequests\PublishMobilityRequest;
use App\Actions\MobilityRequests\SaveMobilityRequest;
use App\Actions\Profiles\SaveProfessionalProfile;
use App\Enums\MatchStatus;
use App\Models\Grade;
use App\Models\MobilityMatch;
use App\Models\MobilityRequest;
use App\Models\Profession;
use App\Models\Specialty;
use App\Models\User;
use App\Support\Matching\StrictRulesV1;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesReferenceData;
use Tests\TestCase;

class ComputeDirectMatchesTest extends TestCase
{
    use CreatesReferenceData;
    use RefreshDatabase;

    private User $rosso;

    private User $hodh;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-01 10:00:00');
        config(['matching.enabled' => true]);
        $this->createReferenceData();

        // Teacher in Rosso (0606, with an establishment) and teacher in 0101 (no establishment).
        $this->rosso = $this->teacher([]);
        $this->hodh = $this->teacher(['wilaya' => '01', 'moughataa' => '0101', 'establishment' => null]);
    }

    public function test_suggests_one_explained_match_for_two_mutual_requests(): void
    {
        $this->publish($this->rosso, [['wilaya' => '01', 'moughataa' => '0101']]);
        $this->publish($this->hodh, [['wilaya' => '06', 'moughataa' => '0606']]);

        $match = MobilityMatch::query()->with(['participants', 'reasons'])->sole();

        $this->assertSame(MatchStatus::Suggested, $match->status);
        $this->assertSame(StrictRulesV1::VERSION, $match->rules_version);
        $this->assertEqualsCanonicalizing([$this->rosso->id, $this->hodh->id], $match->participants->pluck('user_id')->all());
        $this->assertSame([
            'destination' => [35, 'exact_both'],
            'professional' => [25, 'identical'],
            'establishment' => [0, 'none'],
            'availability' => [10, 'close'],
            'verified_profile' => [0, 'not_available'],
            'profile_completeness' => [3, 'one'],
            'recent_activity' => [5, 'both'],
        ], $match->reasons->mapWithKeys(fn ($reason) => [$reason->criterion => [$reason->points, $reason->detail]])->all());
        $this->assertSame(78, $match->score);
        $this->assertSame(100, $match->reasons->sum('max_points'));
    }

    public function test_a_recomputation_keeps_a_single_row_per_pair(): void
    {
        $a = $this->publish($this->rosso, [['wilaya' => '01']]);
        $b = $this->publish($this->hodh, [['wilaya' => '06', 'moughataa' => '0606']]);

        $this->assertSame(['suggested' => 0, 'updated' => 1, 'invalidated' => 0], app(ComputeDirectMatches::class)->handle($a));
        $this->assertSame(['suggested' => 0, 'updated' => 1, 'invalidated' => 0], app(ComputeDirectMatches::class)->handle($b));
        $this->assertSame(1, MobilityMatch::query()->count());
        $this->assertSame('exact_one', MobilityMatch::query()->sole()->reasons()->where('criterion', 'destination')->value('detail'));
    }

    public function test_no_match_without_mutual_geography_or_professional_identity(): void
    {
        $this->publish($this->rosso, [['wilaya' => '01']]);
        $this->publish($this->hodh, [['wilaya' => '01']]);
        $this->assertSame(0, MobilityMatch::query()->count(), 'The second request does not want Rosso.');

        $otherGrade = Grade::factory()->for(Profession::query()->where('code', 'teacher')->sole())->create(['code' => 'grade-b']);
        $colleague = $this->teacher(['wilaya' => '01', 'moughataa' => '0101', 'establishment' => null, 'grade' => $otherGrade->code]);
        $this->publish($colleague, [['wilaya' => '06']]);
        $this->assertSame(0, MobilityMatch::query()->count(), 'Different grades never match under v1-strict.');
    }

    public function test_availability_periods_must_overlap_within_the_tolerance(): void
    {
        $this->publish($this->rosso, [['wilaya' => '01']], '2026-10-15', '2027-01-15');
        $late = $this->publish($this->hodh, [['wilaya' => '06']], '2027-02-01', '2027-06-01');
        $this->assertSame(0, MobilityMatch::query()->count());

        config(['matching.availability_tolerance_days' => 30]);
        app(ComputeDirectMatches::class)->handle($late);

        $this->assertSame(MatchStatus::Suggested, MobilityMatch::query()->sole()->status);
    }

    public function test_invalidates_with_a_reason_then_suggests_again_the_same_row(): void
    {
        $this->publish($this->rosso, [['wilaya' => '01']]);
        $hodhRequest = $this->publish($this->hodh, [['wilaya' => '06']]);
        $match = MobilityMatch::query()->sole();

        app(PauseMobilityRequest::class)->handle($this->hodh, $hodhRequest->public_id);
        $match->refresh();
        $this->assertSame(MatchStatus::Invalidated, $match->status);
        $this->assertSame('request_unavailable', $match->invalidation_reason);
        $this->assertNotNull($match->invalidated_at);

        app(PublishMobilityRequest::class)->handle($this->hodh, $hodhRequest->public_id);
        $match->refresh();
        $this->assertSame(MatchStatus::Suggested, $match->status);
        $this->assertNull($match->invalidation_reason);

        app(SaveMobilityRequest::class)->handle($this->hodh, ['destinations' => [['wilaya' => '01']]], $hodhRequest->public_id);
        $this->assertSame('geography', $match->refresh()->invalidation_reason);
        $this->assertSame(1, MobilityMatch::query()->count());
    }

    public function test_a_profile_change_or_a_suspension_invalidates_the_matches(): void
    {
        $this->publish($this->rosso, [['wilaya' => '01']]);
        $this->publish($this->hodh, [['wilaya' => '06']]);
        $match = MobilityMatch::query()->sole();

        Specialty::factory()->for(Profession::query()->where('code', 'teacher')->sole())->create(['code' => 'history']);
        app(SaveProfessionalProfile::class)->handle($this->hodh, $this->teacherProfileInput(['wilaya' => '01', 'moughataa' => '0101', 'establishment' => null, 'specialty' => 'history']));
        $this->assertSame('specialty', $match->refresh()->invalidation_reason);

        app(SaveProfessionalProfile::class)->handle($this->hodh, $this->teacherProfileInput(['wilaya' => '01', 'moughataa' => '0101', 'establishment' => null]));
        $this->assertSame(MatchStatus::Suggested, $match->refresh()->status);

        app(SuspendUser::class)->handle(User::factory()->administrator()->create(), $this->hodh->public_id, 'Abuse.');
        $this->assertSame('request_unavailable', $match->refresh()->invalidation_reason);
    }

    public function test_expired_requests_lose_their_matches(): void
    {
        $this->publish($this->rosso, [['wilaya' => '01']], '2026-10-15', '2027-01-15');
        $this->publish($this->hodh, [['wilaya' => '06']]);

        $this->travelTo('2027-01-16 01:00:00');
        $this->artisan('requests:expire')->assertSuccessful();

        $this->assertSame(MatchStatus::Invalidated, MobilityMatch::query()->sole()->status);
    }

    public function test_never_changes_a_final_match(): void
    {
        $this->publish($this->rosso, [['wilaya' => '01']]);
        $hodhRequest = $this->publish($this->hodh, [['wilaya' => '06']]);
        MobilityMatch::query()->update(['status' => MatchStatus::Declined]);

        app(PauseMobilityRequest::class)->handle($this->hodh, $hodhRequest->public_id);

        $this->assertSame(MatchStatus::Declined, MobilityMatch::query()->sole()->status);
    }

    public function test_does_nothing_while_matching_is_disabled(): void
    {
        config(['matching.enabled' => false]);

        $this->publish($this->rosso, [['wilaya' => '01']]);
        $this->publish($this->hodh, [['wilaya' => '06']]);
        $this->artisan('matching:recompute')->expectsOutputToContain('disabled')->assertSuccessful();

        $this->assertSame(0, MobilityMatch::query()->count());

        config(['matching.enabled' => true]);
        $this->artisan('matching:recompute')->expectsOutput('1 suggested, 1 updated, 0 invalidated.')->assertSuccessful();
        $this->assertSame(1, MobilityMatch::query()->count());
    }

    /**
     * @param  array<string, string|null>  $profileOverrides
     */
    private function teacher(array $profileOverrides): User
    {
        $user = User::factory()->create();
        app(SaveProfessionalProfile::class)->handle($user, $this->teacherProfileInput($profileOverrides));

        return $user;
    }

    /**
     * @param  list<array<string, string>>  $destinations
     */
    private function publish(User $user, array $destinations, string $from = '2026-10-15', string $until = '2027-04-15'): MobilityRequest
    {
        $request = app(SaveMobilityRequest::class)->handle($user, [
            'available_from' => $from,
            'expires_at' => $until,
            'destinations' => $destinations,
        ]);

        return app(PublishMobilityRequest::class)->handle($user, $request->public_id);
    }
}
