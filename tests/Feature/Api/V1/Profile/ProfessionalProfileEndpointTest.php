<?php

namespace Tests\Feature\Api\V1\Profile;

use App\Enums\TokenAbility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesReferenceData;
use Tests\TestCase;

class ProfessionalProfileEndpointTest extends TestCase
{
    use CreatesReferenceData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createReferenceData();
    }

    public function test_creates_reads_and_replaces_the_profile_by_codes(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $this->withToken($token)->getJson('/api/v1/me/profile')
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found');

        $this->withToken($token)->putJson('/api/v1/me/profile', $this->teacherProfileInput())
            ->assertCreated()
            ->assertJsonPath('data.sector.code', 'education')
            ->assertJsonPath('data.profession.code', 'teacher')
            ->assertJsonPath('data.specialty.code', 'maths')
            ->assertJsonPath('data.grade.code', 'grade-a')
            ->assertJsonPath('data.wilaya.code', '06')
            ->assertJsonPath('data.moughataa.code', '0606')
            ->assertJsonPath('data.establishment.code', 'lycee-rosso')
            ->assertJsonPath('data.professional_identifier', 'MAT-12345')
            ->assertJsonMissingPath('data.id')
            ->assertJsonMissingPath('data.user_id')
            ->assertJsonMissingPath('data.sector.id');

        $this->withToken($token)->putJson('/api/v1/me/profile', $this->teacherProfileInput(['establishment' => null]))
            ->assertOk()
            ->assertJsonPath('data.establishment', null);

        $this->withToken($token)->getJson('/api/v1/me/profile')
            ->assertOk()
            ->assertJsonPath('data.profession.code', 'teacher')
            ->assertJsonPath('data.establishment', null);
    }

    public function test_reports_inconsistent_references_as_validation_errors(): void
    {
        $this->withToken($this->tokenFor(User::factory()->create()))
            ->putJson('/api/v1/me/profile', $this->teacherProfileInput(['profession' => 'nurse', 'wilaya' => null]))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors(['wilaya']);

        $this->withToken($this->tokenFor(User::factory()->create()))
            ->putJson('/api/v1/me/profile', $this->teacherProfileInput(['profession' => 'nurse']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['profession']);
    }

    public function test_requires_an_active_account_and_a_full_access_token(): void
    {
        $this->putJson('/api/v1/me/profile', $this->teacherProfileInput())->assertUnauthorized();

        $suspended = User::factory()->suspended()->create();
        $this->withToken($this->tokenFor($suspended))
            ->putJson('/api/v1/me/profile', $this->teacherProfileInput())
            ->assertForbidden();

        $legacy = User::factory()->create()->createToken('legacy', [TokenAbility::VerifyContact->value])->plainTextToken;
        $this->withToken($legacy)->getJson('/api/v1/me/profile')->assertForbidden();

        $this->assertDatabaseCount('professional_profiles', 0);
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('test', [TokenAbility::AccessApi->value])->plainTextToken;
    }
}
