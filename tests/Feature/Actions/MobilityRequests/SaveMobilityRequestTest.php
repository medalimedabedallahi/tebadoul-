<?php

namespace Tests\Feature\Actions\MobilityRequests;

use App\Actions\MobilityRequests\SaveMobilityRequest;
use App\Actions\Profiles\SaveProfessionalProfile;
use App\Enums\MobilityRequestStatus;
use App\Exceptions\MobilityRequests\InvalidRequestTransitionException;
use App\Models\MobilityRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesReferenceData;
use Tests\TestCase;

class SaveMobilityRequestTest extends TestCase
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

    public function test_creates_a_draft_with_destinations_in_priority_order(): void
    {
        $request = app(SaveMobilityRequest::class)->handle($this->user, $this->input([
            'destinations' => [
                ['wilaya' => '01', 'moughataa' => '0101'],
                ['wilaya' => '06'],
            ],
        ]));

        $this->assertSame(MobilityRequestStatus::Draft, $request->status);
        $this->assertSame(26, strlen($request->public_id));
        $this->assertTrue($request->user->is($this->user));
        $this->assertSame([1, 2], $request->destinations->pluck('priority')->all());
        $this->assertSame(['01', '06'], $request->destinations->map(fn ($destination) => $destination->wilaya->code)->all());
        $this->assertSame('0101', $request->destinations[0]->moughataa?->code);
        $this->assertNull($request->destinations[1]->moughataa_id);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidInputs')]
    public function test_rejects_invalid_input_on_the_right_field(array $overrides, string $field): void
    {
        try {
            app(SaveMobilityRequest::class)->handle($this->user, $this->input($overrides));
            $this->fail('Invalid input must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }

        $this->assertSame(0, MobilityRequest::query()->count());
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'no destination' => [['destinations' => []], 'destinations'],
            'too many destinations' => [['destinations' => array_fill(0, 6, ['wilaya' => '01'])], 'destinations'],
            'expiry in the past' => [['expires_at' => '2026-09-30'], 'expires_at'],
            'expiry more than a year away' => [['expires_at' => '2027-10-02'], 'expires_at'],
            'expiry before availability' => [['available_from' => '2027-03-01', 'expires_at' => '2027-02-01'], 'expires_at'],
            'unknown wilaya' => [['destinations' => [['wilaya' => '99']]], 'destinations.0.wilaya'],
            'moughataa of another wilaya' => [['destinations' => [['wilaya' => '01', 'moughataa' => '0606']]], 'destinations.0.moughataa'],
            'establishment without moughataa' => [['destinations' => [['wilaya' => '06', 'establishment' => 'lycee-rosso']]], 'destinations.0.establishment'],
            'duplicate destination' => [['destinations' => [['wilaya' => '01'], ['wilaya' => '01']]], 'destinations.1.wilaya'],
            'current assignment' => [['destinations' => [['wilaya' => '06', 'moughataa' => '0606']]], 'destinations.0.moughataa'],
            'unexpected destination key' => [['destinations' => [['wilaya' => '01', 'wilaya_id' => 1]]], 'destinations.0'],
        ];
    }

    public function test_updates_only_the_given_fields_and_replaces_destinations(): void
    {
        $request = app(SaveMobilityRequest::class)->handle($this->user, $this->input());

        $updated = app(SaveMobilityRequest::class)->handle($this->user, ['expires_at' => '2027-06-30'], $request->public_id);
        $this->assertSame('2027-06-30', $updated->expires_at->toDateString());
        $this->assertSame('2026-10-15', $updated->available_from->toDateString());
        $this->assertSame(['01'], $updated->destinations->map(fn ($destination) => $destination->wilaya->code)->all());

        $replaced = app(SaveMobilityRequest::class)->handle($this->user, ['destinations' => [['wilaya' => '06']]], strtoupper($request->public_id));
        $this->assertSame(['06'], $replaced->destinations->map(fn ($destination) => $destination->wilaya->code)->all());
        $this->assertDatabaseCount('request_destinations', 1);
    }

    public function test_a_closed_request_cannot_be_updated(): void
    {
        $request = MobilityRequest::factory()->for($this->user)->status(MobilityRequestStatus::Closed)->create();

        $this->expectException(InvalidRequestTransitionException::class);

        app(SaveMobilityRequest::class)->handle($this->user, ['expires_at' => '2027-06-30'], $request->public_id);
    }

    public function test_the_request_of_another_account_is_reported_as_unknown(): void
    {
        $foreign = MobilityRequest::factory()->create();

        $this->expectException(ModelNotFoundException::class);

        app(SaveMobilityRequest::class)->handle($this->user, ['expires_at' => '2027-06-30'], $foreign->public_id);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function input(array $overrides = []): array
    {
        return [
            'available_from' => '2026-10-15',
            'expires_at' => '2027-04-15',
            'destinations' => [['wilaya' => '01']],
            ...$overrides,
        ];
    }
}
