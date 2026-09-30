<?php

namespace Tests\Feature\Database;

use App\Models\Moughataa;
use App\Models\Sector;
use App\Models\Wilaya;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferenceDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_the_shipped_sectors_and_geography_idempotently(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(ReferenceDataSeeder::class);

        $this->assertEqualsCanonicalizing(['education', 'health'], Sector::query()->pluck('code')->all());
        $this->assertSame(15, Wilaya::query()->count());
        $this->assertSame(0, Wilaya::query()->doesntHave('moughataas')->count());
        $this->assertSame(55, Moughataa::query()->count());
        $this->assertSame(0, Moughataa::query()->where('name_ar', '')->orWhere('name_fr', '')->count());
    }
}
