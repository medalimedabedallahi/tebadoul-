<?php

namespace Tests\Feature\Actions\References;

use App\Actions\References\ImportReferences;
use App\Enums\ReferenceType;
use App\Exceptions\References\InvalidReferenceImportException;
use App\Models\Moughataa;
use App\Models\Wilaya;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportReferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_then_updates_entries_matched_on_their_code(): void
    {
        $wilaya = Wilaya::factory()->create(['code' => '06']);

        $first = $this->import(ReferenceType::Moughataas, "\xEF\xBB\xBFcode,wilaya_code,name_fr,name_ar\n0606,06,Rosso,روصو\n0601,06,Boutilimit,بوتلميت\n");

        $this->assertSame(['created' => 2, 'updated' => 0, 'unchanged' => 0], $first);
        $rosso = Moughataa::query()->where('code', '0606')->sole();
        $this->assertTrue($rosso->wilaya->is($wilaya));
        $this->assertSame('روصو', $rosso->name_ar);
        $this->assertTrue($rosso->active);

        $second = $this->import(ReferenceType::Moughataas, "code,wilaya_code,name_fr,name_ar,active\n0606,06,Rosso (ville),روصو,1\n0601,06,Boutilimit,بوتلميت,0\n");

        $this->assertSame(['created' => 0, 'updated' => 2, 'unchanged' => 0], $second);
        $this->assertSame('Rosso (ville)', $rosso->fresh()->name_fr);
        $this->assertFalse(Moughataa::query()->where('code', '0601')->sole()->active);
        $this->assertSame(2, Moughataa::query()->count());
    }

    public function test_reimporting_the_same_file_changes_nothing(): void
    {
        $csv = "code,name_fr,name_ar\n01,Hodh Ech Chargui,الحوض الشرقي\n";
        $this->import(ReferenceType::Wilayas, $csv);

        $this->assertSame(['created' => 0, 'updated' => 0, 'unchanged' => 1], $this->import(ReferenceType::Wilayas, $csv));
    }

    public function test_an_invalid_line_rejects_the_whole_file_with_every_error(): void
    {
        Wilaya::factory()->create(['code' => '06']);

        try {
            $this->import(ReferenceType::Moughataas, implode("\n", [
                'code,wilaya_code,name_fr,name_ar',
                '0606,06,Rosso,روصو',
                '0699,99,Unknown parent,مجهول',
                '0606,06,Duplicate,مكرر',
                'bad code!,06,,اسم',
            ]));
            $this->fail('An invalid file must be rejected.');
        } catch (InvalidReferenceImportException $exception) {
            $errors = implode("\n", $exception->errors);
            $this->assertStringContainsString('Line 3: unknown wilaya_code 99.', $errors);
            $this->assertStringContainsString('Line 4: the code 0606 already appears on line 2.', $errors);
            $this->assertStringContainsString('Line 5:', $errors);
        }

        $this->assertSame(0, Moughataa::query()->count());
    }

    public function test_rejects_a_header_that_does_not_match_the_type(): void
    {
        $this->expectException(InvalidReferenceImportException::class);

        $this->import(ReferenceType::Moughataas, "code,name_fr,name_ar\n0606,Rosso,روصو\n");
    }

    public function test_the_command_reports_errors_and_fails_without_writing(): void
    {
        $path = $this->csvFile("code,name_fr,name_ar\n,Missing code,بدون رمز\n");

        $this->artisan('references:import', ['type' => 'wilayas', 'path' => $path])
            ->expectsOutput('Nothing was imported:')
            ->assertFailed();
        $this->artisan('references:import', ['type' => 'planets', 'path' => $path])->assertFailed();

        $this->assertSame(0, Wilaya::query()->count());
    }

    /**
     * @return array{created: int, updated: int, unchanged: int}
     */
    private function import(ReferenceType $type, string $contents): array
    {
        return app(ImportReferences::class)->handle($type, $this->csvFile($contents));
    }

    private function csvFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'references');
        file_put_contents($path, $contents);
        $this->beforeApplicationDestroyed(fn () => @unlink($path));

        return $path;
    }
}
