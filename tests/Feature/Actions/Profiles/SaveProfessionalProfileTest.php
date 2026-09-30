<?php

namespace Tests\Feature\Actions\Profiles;

use App\Actions\Profiles\SaveProfessionalProfile;
use App\Models\Grade;
use App\Models\Profession;
use App\Models\ProfessionalProfile;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesReferenceData;
use Tests\TestCase;

class SaveProfessionalProfileTest extends TestCase
{
    use CreatesReferenceData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createReferenceData();
    }

    public function test_creates_then_replaces_the_single_profile_of_the_account(): void
    {
        $user = User::factory()->create();

        $created = app(SaveProfessionalProfile::class)->handle($user, $this->teacherProfileInput());

        $this->assertTrue($created->wasRecentlyCreated);
        $this->assertSame('teacher', $created->profession->code);
        $this->assertSame('maths', $created->specialty?->code);
        $this->assertSame('lycee-rosso', $created->establishment?->code);
        $this->assertSame('MAT-12345', $created->fresh()->professional_identifier);
        $this->assertNotSame('MAT-12345', DB::table('professional_profiles')->value('professional_identifier'));

        $replaced = app(SaveProfessionalProfile::class)->handle($user, [
            'sector' => 'health',
            'profession' => 'nurse',
            'wilaya' => '01',
            'moughataa' => '0101',
            'professional_identifier' => '  ',
        ]);

        $this->assertTrue($replaced->is($created));
        $this->assertSame(1, ProfessionalProfile::query()->count());
        $this->assertNull($replaced->specialty_id);
        $this->assertNull($replaced->grade_id);
        $this->assertNull($replaced->establishment_id);
        $this->assertNull($replaced->professional_identifier);
    }

    /**
     * @param  array<string, string|null>  $overrides
     */
    #[DataProvider('inconsistentInputs')]
    public function test_rejects_inconsistent_references_on_the_right_field(array $overrides, string $field): void
    {
        $user = User::factory()->create();

        try {
            app(SaveProfessionalProfile::class)->handle($user, $this->teacherProfileInput($overrides));
            $this->fail('An inconsistent profile must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }

        $this->assertSame(0, ProfessionalProfile::query()->count());
    }

    /**
     * @return array<string, array{array<string, string|null>, string}>
     */
    public static function inconsistentInputs(): array
    {
        return [
            'unknown sector' => [['sector' => 'police'], 'sector'],
            'profession of another sector' => [['profession' => 'nurse'], 'profession'],
            'missing specialty when the profession has some' => [['specialty' => null], 'specialty'],
            'missing grade when the profession has some' => [['grade' => ''], 'grade'],
            'unknown specialty' => [['specialty' => 'history'], 'specialty'],
            'moughataa of another wilaya' => [['moughataa' => '0101'], 'moughataa'],
            'establishment of another moughataa' => [['wilaya' => '01', 'moughataa' => '0101'], 'establishment'],
        ];
    }

    public function test_rejects_a_deactivated_reference(): void
    {
        Grade::query()->where('code', 'grade-a')->update(['active' => false]);
        Grade::factory()->for(Profession::query()->where('code', 'teacher')->sole())->create(['code' => 'grade-b']);

        $this->expectException(ValidationException::class);

        app(SaveProfessionalProfile::class)->handle(User::factory()->create(), $this->teacherProfileInput());
    }

    public function test_an_account_that_is_not_active_cannot_save_a_profile(): void
    {
        $this->expectException(AuthorizationException::class);

        app(SaveProfessionalProfile::class)->handle(User::factory()->suspended()->create(), $this->teacherProfileInput());
    }
}
