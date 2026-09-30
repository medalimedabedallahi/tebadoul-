<?php

namespace Tests\Feature\Api\V1\References;

use App\Models\Establishment;
use App\Models\Grade;
use App\Models\Moughataa;
use App\Models\Profession;
use App\Models\Sector;
use App\Models\Specialty;
use App\Models\Wilaya;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferenceEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_active_sectors_publicly_by_code_without_internal_identifiers(): void
    {
        Sector::factory()->create(['code' => 'health', 'name_fr' => 'Santé', 'name_ar' => 'الصحة']);
        Sector::factory()->create(['code' => 'education', 'name_fr' => 'Enseignement', 'name_ar' => 'التعليم']);
        Sector::factory()->inactive()->create(['code' => 'retired']);

        $this->getJson('/api/v1/references/sectors')
            ->assertOk()
            ->assertExactJson(['data' => [
                ['code' => 'education', 'name_fr' => 'Enseignement', 'name_ar' => 'التعليم'],
                ['code' => 'health', 'name_fr' => 'Santé', 'name_ar' => 'الصحة'],
            ]]);
    }

    public function test_filters_professions_by_sector_and_specialties_and_grades_by_profession(): void
    {
        $education = Sector::factory()->create(['code' => 'education']);
        $teacher = Profession::factory()->for($education)->create(['code' => 'teacher']);
        Profession::factory()->create(['code' => 'nurse']);
        Profession::factory()->for($education)->inactive()->create(['code' => 'retired']);
        Specialty::factory()->for($teacher)->create(['code' => 'maths']);
        Specialty::factory()->create(['code' => 'surgery']);
        Grade::factory()->for($teacher)->create(['code' => 'grade-a']);

        $this->getJson('/api/v1/references/professions?sector=education')
            ->assertOk()
            ->assertExactJson(['data' => [[
                'code' => 'teacher',
                'name_fr' => $teacher->name_fr,
                'name_ar' => $teacher->name_ar,
                'sector_code' => 'education',
            ]]]);
        $this->getJson('/api/v1/references/specialties?profession=teacher')
            ->assertOk()
            ->assertJsonPath('data.*.code', ['maths'])
            ->assertJsonPath('data.0.profession_code', 'teacher');
        $this->getJson('/api/v1/references/grades?profession=teacher')
            ->assertOk()
            ->assertJsonPath('data.*.code', ['grade-a']);
        $this->getJson('/api/v1/references/professions?sector=unknown')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_lists_the_moughataas_of_an_active_wilaya_only(): void
    {
        $trarza = Wilaya::factory()->create(['code' => '06']);
        Moughataa::factory()->for($trarza)->create(['code' => '0606', 'name_fr' => 'Rosso']);
        Moughataa::factory()->for($trarza)->inactive()->create(['code' => '0699']);
        Moughataa::factory()->create(['code' => '0101']);
        Wilaya::factory()->inactive()->create(['code' => '99']);

        $this->getJson('/api/v1/references/wilayas/06/moughataas')
            ->assertOk()
            ->assertJsonPath('data.*.code', ['0606'])
            ->assertJsonPath('data.0.wilaya_code', '06');
        $this->getJson('/api/v1/references/wilayas/99/moughataas')
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found');
        $this->getJson('/api/v1/references/wilayas/unknown/moughataas')->assertNotFound();
    }

    public function test_searches_establishments_by_sector_zone_and_name_with_pagination(): void
    {
        $education = Sector::factory()->create(['code' => 'education']);
        $health = Sector::factory()->create(['code' => 'health']);
        $trarza = Wilaya::factory()->create(['code' => '06']);
        $rosso = Moughataa::factory()->for($trarza)->create(['code' => '0606']);
        $elsewhere = Moughataa::factory()->create();
        Establishment::factory()->for($education)->for($rosso)->create([
            'code' => 'lycee-rosso',
            'type' => 'high_school',
            'name_fr' => 'Lycée de Rosso',
        ]);
        Establishment::factory()->for($education)->for($rosso)->create(['code' => 'ecole-rosso', 'name_fr' => 'École 1 de Rosso']);
        Establishment::factory()->for($health)->for($rosso)->create(['code' => 'hopital-rosso']);
        Establishment::factory()->for($education)->for($elsewhere)->create(['code' => 'lycee-ailleurs', 'name_fr' => 'Lycée ailleurs']);
        Establishment::factory()->for($education)->for($rosso)->inactive()->create(['code' => 'lycee-ferme', 'name_fr' => 'Lycée fermé']);

        $this->getJson('/api/v1/references/establishments?sector=education&wilaya=06&q=LYCÉE')
            ->assertOk()
            ->assertJsonPath('data', [[
                'code' => 'lycee-rosso',
                'name_fr' => 'Lycée de Rosso',
                'name_ar' => Establishment::query()->where('code', 'lycee-rosso')->value('name_ar'),
                'type' => 'high_school',
                'sector_code' => 'education',
                'wilaya_code' => '06',
                'moughataa_code' => '0606',
            ]]);
        $this->getJson('/api/v1/references/establishments?moughataa=0606&per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3);
        $this->getJson('/api/v1/references/establishments?q=%25')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['q']);
        $this->getJson('/api/v1/references/establishments?q=_%25')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
