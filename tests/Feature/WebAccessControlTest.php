<?php

namespace Tests\Feature;

use App\Models\MobilityRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Tests\Support\CreatesMatchedPair;
use Tests\TestCase;

/**
 * Pilot gate "Tous les acces interdits sont testes" for the web pages, swept over EVERY page
 * behind `auth`: a guest is sent to the sign-in page, a suspended session is signed out, an
 * ordinary account never opens an administration page, and another account's match or request
 * is not found.
 */
class WebAccessControlTest extends TestCase
{
    use CreatesMatchedPair;
    use RefreshDatabase;

    /**
     * Administration pages a moderator may open; the other `admin.*` pages are for administrators.
     *
     * @var list<string>
     */
    private const MODERATION_PAGES = ['admin.reports.index'];

    /**
     * @var array<string, string>
     */
    private array $parameters = [];

    public function test_every_private_page_sends_a_guest_to_sign_in(): void
    {
        $this->createFixtures();

        foreach ($this->privatePages() as $route) {
            $this->get($this->urlOf($route))->assertRedirect(route('login'));
        }
    }

    public function test_every_private_page_signs_out_a_suspended_account(): void
    {
        $this->createFixtures();
        $suspended = User::factory()->suspended()->create();

        foreach ($this->privatePages() as $route) {
            $this->actingAs($suspended)->get($this->urlOf($route))->assertRedirect(route('login'));
            $this->assertGuest();
        }
    }

    public function test_administration_pages_refuse_ordinary_accounts_and_moderators_outside_moderation(): void
    {
        $this->createFixtures();
        $user = User::factory()->create();
        $moderator = User::factory()->moderator()->create();
        $adminPages = array_filter($this->privatePages(), fn (Route $route): bool => str_starts_with((string) $route->getName(), 'admin.'));

        $this->assertNotEmpty($adminPages);

        foreach ($adminPages as $route) {
            $this->actingAs($user)->get($this->urlOf($route))->assertForbidden();

            if (! in_array($route->getName(), self::MODERATION_PAGES, true)) {
                $this->actingAs($moderator)->get($this->urlOf($route))->assertForbidden();
            }
        }
    }

    public function test_another_account_match_or_request_is_not_found(): void
    {
        $this->createFixtures();
        $stranger = User::factory()->create();
        $scoped = array_filter($this->privatePages(), fn (Route $route): bool => $route->parameterNames() !== []);

        $this->assertNotEmpty($scoped);

        foreach ($scoped as $route) {
            $this->actingAs($stranger)->get($this->urlOf($route))->assertNotFound();
        }
    }

    private function createFixtures(): void
    {
        [$rosso, , $match] = $this->createMatchedPair();

        $this->parameters = [
            'match' => $match->public_id,
            'mobilityRequest' => MobilityRequest::query()->where('user_id', $rosso->id)->sole()->public_id,
        ];
    }

    /**
     * @return list<Route>
     */
    private function privatePages(): array
    {
        $routes = array_values(array_filter(
            Router::getRoutes()->getRoutes(),
            fn (Route $route): bool => in_array('GET', $route->methods(), true)
                && ! str_starts_with($route->uri(), 'api/')
                && in_array('auth', $route->gatherMiddleware(), true),
        ));

        $this->assertGreaterThan(10, count($routes), 'The private pages could not be collected.');

        return $routes;
    }

    private function urlOf(Route $route): string
    {
        return '/'.ltrim((string) preg_replace_callback('/\{(\w+)\??\}/', fn (array $name): string => $this->parameters[$name[1]], $route->uri()), '/');
    }
}
