<?php

namespace Tests\Feature;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * Part of the pilot gate "Le francais, l'arabe, le RTL et WCAG 2.2 AA sont verifies": both
 * languages carry exactly the same translation keys, and every literal key used by the code
 * exists in both, so that no screen falls back to a raw key or to the other language.
 */
class TranslationParityTest extends TestCase
{
    public function test_french_and_arabic_files_have_the_same_keys(): void
    {
        $frenchFiles = $this->fileNames('fr');

        $this->assertNotEmpty($frenchFiles);
        $this->assertSame($frenchFiles, $this->fileNames('ar'));

        foreach ($frenchFiles as $file) {
            $french = array_keys(Arr::dot(require lang_path("fr/{$file}")));
            $arabic = array_keys(Arr::dot(require lang_path("ar/{$file}")));

            $this->assertSame([], array_values(array_diff($french, $arabic)), "Keys of fr/{$file} missing in Arabic.");
            $this->assertSame([], array_values(array_diff($arabic, $french)), "Keys of ar/{$file} missing in French.");
        }

        $this->assertEqualsCanonicalizing(
            array_keys((array) json_decode((string) file_get_contents(lang_path('fr.json')), true)),
            array_keys((array) json_decode((string) file_get_contents(lang_path('ar.json')), true)),
        );
    }

    public function test_every_literal_key_used_by_the_code_exists_in_both_languages(): void
    {
        $keys = [];

        foreach ([...File::allFiles(app_path()), ...File::allFiles(resource_path('views'))] as $file) {
            preg_match_all("/(?:__|trans_choice|@lang)\\(\\s*'([a-z_]+\\.[A-Za-z0-9_.]+)'\\s*[,)]/", $file->getContents(), $matches);
            $keys = [...$keys, ...$matches[1]];
        }

        $keys = array_values(array_unique($keys));
        $this->assertGreaterThan(100, count($keys), 'The translation keys could not be collected.');

        foreach (['fr', 'ar'] as $locale) {
            $missing = array_values(array_filter($keys, fn (string $key): bool => ! Lang::hasForLocale($key, $locale)));

            $this->assertSame([], $missing, "Keys missing in {$locale}.");
        }
    }

    /**
     * @return list<string>
     */
    private function fileNames(string $locale): array
    {
        $names = array_map(fn ($file): string => $file->getFilename(), File::files(lang_path($locale)));
        sort($names);

        return $names;
    }
}
