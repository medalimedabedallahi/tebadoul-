<?php

namespace Tests\Feature\Actions\Advertising;

use App\Actions\Advertising\DeleteAdvertisement;
use App\Models\Advertisement;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteAdvertisementTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_administrator_deletes_an_advertisement_for_good(): void
    {
        $advertisement = Advertisement::factory()->create();
        $kept = Advertisement::factory()->create();

        app(DeleteAdvertisement::class)->handle(User::factory()->administrator()->create(), strtoupper($advertisement->public_id));

        $this->assertModelMissing($advertisement);
        $this->assertModelExists($kept);
    }

    public function test_a_moderator_cannot_delete_an_advertisement(): void
    {
        $advertisement = Advertisement::factory()->create();

        try {
            app(DeleteAdvertisement::class)->handle(User::factory()->moderator()->create(), $advertisement->public_id);
            $this->fail('A moderator deleted an advertisement.');
        } catch (AuthorizationException) {
        }

        $this->assertModelExists($advertisement);
    }

    public function test_an_unknown_advertisement_is_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        app(DeleteAdvertisement::class)->handle(User::factory()->administrator()->create(), '01k6gx0000000000000000000z');
    }
}
