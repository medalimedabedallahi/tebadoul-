<?php

namespace Tests\Support;

use App\Actions\MobilityRequests\PublishMobilityRequest;
use App\Actions\MobilityRequests\SaveMobilityRequest;
use App\Actions\Profiles\SaveProfessionalProfile;
use App\Models\MobilityMatch;
use App\Models\User;

/**
 * Two teachers with mutual published requests, matched by the engine: one in Rosso (0606, with
 * the establishment `lycee-rosso`) wanting wilaya 01, one in 0101 wanting Rosso. Enables
 * matching. Use with {@see CreatesReferenceData} (called here).
 */
trait CreatesMatchedPair
{
    use CreatesReferenceData;

    /**
     * @return array{User, User, MobilityMatch}
     */
    protected function createMatchedPair(): array
    {
        config(['matching.enabled' => true]);
        $this->createReferenceData();

        $rosso = $this->publishedTeacher([], [['wilaya' => '01']]);
        $hodh = $this->publishedTeacher(['wilaya' => '01', 'moughataa' => '0101', 'establishment' => null], [['wilaya' => '06', 'moughataa' => '0606']]);

        return [$rosso, $hodh, MobilityMatch::query()->sole()];
    }

    /**
     * @param  array<string, string|null>  $profile
     * @param  list<array<string, string>>  $destinations
     */
    protected function publishedTeacher(array $profile, array $destinations): User
    {
        $user = User::factory()->create();
        app(SaveProfessionalProfile::class)->handle($user, $this->teacherProfileInput($profile));
        $request = app(SaveMobilityRequest::class)->handle($user, [
            'available_from' => today()->addDays(14)->toDateString(),
            'expires_at' => today()->addMonths(6)->toDateString(),
            'destinations' => $destinations,
        ]);
        app(PublishMobilityRequest::class)->handle($user, $request->public_id);

        return $user;
    }
}
