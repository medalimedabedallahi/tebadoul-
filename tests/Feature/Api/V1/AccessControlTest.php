<?php

namespace Tests\Feature\Api\V1;

use App\Actions\Matching\AcceptMatch;
use App\Actions\Matching\InviteToMatch;
use App\Actions\Messaging\SendMatchMessage;
use App\Actions\Trust\CreateUserReport;
use App\Enums\ReportReason;
use App\Enums\TokenAbility;
use App\Enums\UserStatus;
use App\Models\MobilityRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Illuminate\Testing\TestResponse;
use Tests\Support\CreatesMatchedPair;
use Tests\TestCase;

/**
 * Pilot gate "Tous les acces interdits sont testes", swept over EVERY protected `/api/v1` route so
 * that a new route cannot ship without its guards:
 *
 * - no token: 401 `unauthenticated`;
 * - an account that is not active: 403 `forbidden`, whatever the token;
 * - a token without the `access-api` ability: 403 (only `me` and `logout` accept a
 *   verification-only token);
 * - administration: 403 for an ordinary account, and for a moderator outside moderation;
 * - another account's match, request or notification: 404, never 403, so that its existence is
 *   not confirmed.
 *
 * Endpoint tests cover each refusal in context; this sweep proves nothing was left unguarded.
 */
class AccessControlTest extends TestCase
{
    use CreatesMatchedPair;
    use RefreshDatabase;

    /**
     * Routes that accept a token restricted to contact verification.
     *
     * @var list<string>
     */
    private const VERIFICATION_TOKEN_ROUTES = ['api.v1.auth.me', 'api.v1.auth.logout'];

    /**
     * Routes reserved to moderators and administrators; the other `api.v1.admin.*` routes are for
     * administrators only.
     *
     * @var list<string>
     */
    private const MODERATION_ROUTES = ['api.v1.admin.reports.index', 'api.v1.admin.reports.update'];

    /**
     * @var array<string, string>
     */
    private array $parameters = [];

    public function test_every_protected_route_requires_a_token(): void
    {
        $this->createFixtures();

        foreach ($this->protectedRoutes() as $route) {
            $this->callRoute($route, null)
                ->assertUnauthorized()
                ->assertJsonPath('code', 'unauthenticated');
        }
    }

    public function test_every_protected_route_refuses_an_account_that_is_not_active(): void
    {
        $this->createFixtures();

        foreach ([UserStatus::Suspended, UserStatus::PendingVerification] as $status) {
            $token = $this->tokenFor(User::factory()->create(['status' => $status]));

            foreach ($this->protectedRoutes() as $route) {
                $this->callRoute($route, $token)
                    ->assertForbidden()
                    ->assertJsonPath('code', 'forbidden');
            }
        }
    }

    public function test_a_verification_only_token_reaches_nothing_but_me_and_logout(): void
    {
        $this->createFixtures();
        $token = User::factory()->create()->createToken('verify', [TokenAbility::VerifyContact->value])->plainTextToken;

        foreach ($this->protectedRoutes() as $route) {
            if (in_array($route->getName(), self::VERIFICATION_TOKEN_ROUTES, true)) {
                continue;
            }

            $this->callRoute($route, $token)->assertForbidden();
        }
    }

    public function test_administration_routes_refuse_ordinary_accounts_and_moderators_outside_moderation(): void
    {
        $this->createFixtures();
        $userToken = $this->tokenFor(User::factory()->create());
        $moderatorToken = $this->tokenFor(User::factory()->moderator()->create());
        $adminRoutes = array_filter($this->protectedRoutes(), fn (Route $route): bool => str_starts_with((string) $route->getName(), 'api.v1.admin.'));

        $this->assertNotEmpty($adminRoutes);

        foreach ($adminRoutes as $route) {
            $this->callRoute($route, $userToken)->assertForbidden();

            if (! in_array($route->getName(), self::MODERATION_ROUTES, true)) {
                $this->callRoute($route, $moderatorToken)->assertForbidden();
            }
        }
    }

    public function test_another_account_match_request_or_notification_is_reported_as_not_found(): void
    {
        $this->createFixtures();
        $stranger = $this->tokenFor(User::factory()->create());
        $scoped = array_filter($this->protectedRoutes(), fn (Route $route): bool => array_intersect(
            $route->parameterNames(),
            ['match', 'mobilityRequest', 'notification'],
        ) !== []);

        $this->assertNotEmpty($scoped);

        foreach ($scoped as $route) {
            $this->callRoute($route, $stranger)->assertNotFound();
        }
    }

    /**
     * An agreement between Rosso and Hodh with a message, a report and notifications, so that every
     * route parameter points at a real record of Rosso's.
     */
    private function createFixtures(): void
    {
        [$rosso, $hodh, $match] = $this->createMatchedPair();
        app(InviteToMatch::class)->handle($rosso, $match->public_id);
        app(AcceptMatch::class)->handle($hodh, $match->public_id);
        $message = app(SendMatchMessage::class)->handle($hodh, $match->public_id, 'Bonjour');
        $report = app(CreateUserReport::class)->handle($rosso, $match->public_id, ReportReason::Spam);

        $this->parameters = [
            'match' => $match->public_id,
            'mobilityRequest' => MobilityRequest::query()->where('user_id', $rosso->id)->sole()->public_id,
            'message' => $message->public_id,
            'notification' => (string) $rosso->notifications()->firstOrFail()->getKey(),
            'report' => $report->public_id,
            'userPublicId' => $hodh->public_id,
        ];
    }

    /**
     * @return list<Route>
     */
    private function protectedRoutes(): array
    {
        $routes = array_values(array_filter(
            Router::getRoutes()->getRoutes(),
            fn (Route $route): bool => str_starts_with($route->uri(), 'api/v1/') && in_array('auth:sanctum', $route->gatherMiddleware(), true),
        ));

        $this->assertGreaterThan(40, count($routes), 'The protected routes could not be collected.');

        return $routes;
    }

    private function callRoute(Route $route, ?string $token): TestResponse
    {
        $method = array_values(array_diff($route->methods(), ['HEAD']))[0];
        $uri = '/'.preg_replace_callback('/\{(\w+)\}/', fn (array $name): string => $this->parameters[$name[1]], $route->uri());

        // Each call starts from scratch: no header nor resolved user left over from the previous one.
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        if ($token !== null) {
            $this->withToken($token);
        }

        return $this->json($method, $uri, $this->payloadFor((string) $route->getName()));
    }

    /**
     * A valid body, so that validation never answers before the guard under test.
     *
     * @return array<string, mixed>
     */
    private function payloadFor(string $routeName): array
    {
        return match ($routeName) {
            'api.v1.matches.messages.store' => ['body' => 'Bonjour'],
            'api.v1.matches.reports.store' => ['reason' => 'spam'],
            'api.v1.matches.progress.store' => ['status' => 'in_discussion'],
            'api.v1.mobility-requests.close' => ['reason' => 'no_longer_needed'],
            'api.v1.mobility-requests.renew' => ['expires_at' => today()->addMonths(3)->toDateString()],
            'api.v1.admin.reports.update' => ['status' => 'dismissed', 'resolution_note' => 'Sans suite.'],
            'api.v1.admin.users.suspension.store', 'api.v1.admin.users.reinstatement.store' => ['reason' => 'Contrôle.'],
            'api.v1.auth.password.update' => ['current_password' => 'password', 'password' => 'a-new-password-2026', 'password_confirmation' => 'a-new-password-2026'],
            default => [],
        };
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('test', [TokenAbility::AccessApi->value])->plainTextToken;
    }
}
