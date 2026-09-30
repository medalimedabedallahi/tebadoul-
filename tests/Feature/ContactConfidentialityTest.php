<?php

namespace Tests\Feature;

use App\Actions\Matching\AcceptMatch;
use App\Actions\Matching\InviteToMatch;
use App\Actions\Matching\ShareContact;
use App\Actions\Messaging\SendMatchMessage;
use App\Actions\Trust\CreateUserReport;
use App\Enums\ReportReason;
use App\Enums\TokenAbility;
use App\Models\MobilityMatch;
use App\Models\MobilityRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Tests\Support\CreatesMatchedPair;
use Tests\TestCase;

/**
 * Pilot gate "Aucune coordonnee n'est exposee avant consentement" (ADR 0001, decisions 5 and 6),
 * swept over EVERY readable API route and private page, for both participants, a moderator and an
 * administrator: no email address or phone number appears in clear until both participants have
 * consented, and then only through the contact route, to the other participant.
 */
class ContactConfidentialityTest extends TestCase
{
    use CreatesMatchedPair;
    use RefreshDatabase;

    private User $rosso;

    private User $hodh;

    private MobilityMatch $match;

    public function test_no_contact_detail_is_readable_during_an_agreement_without_both_consents(): void
    {
        $this->createAgreement();
        app(ShareContact::class)->grant($this->rosso, $this->match->public_id);

        foreach ($this->viewers() as $viewer) {
            $this->assertNothingLeaksTo($viewer);
        }

        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->tokenFor($this->rosso))
            ->getJson("/api/v1/matches/{$this->match->public_id}/contact")
            ->assertForbidden()
            ->assertJsonPath('code', 'contact_not_authorized');
    }

    public function test_both_consents_reveal_the_counterpart_contact_only_through_the_contact_route(): void
    {
        $this->createAgreement();
        app(ShareContact::class)->grant($this->rosso, $this->match->public_id);
        app(ShareContact::class)->grant($this->hodh, $this->match->public_id);

        $this->withToken($this->tokenFor($this->rosso))
            ->getJson("/api/v1/matches/{$this->match->public_id}/contact")
            ->assertOk()
            ->assertJsonPath('data.email', 'hodh@example.org')
            ->assertJsonPath('data.phone', '+22242222222');

        foreach ($this->viewers() as $viewer) {
            $this->assertNothingLeaksTo($viewer, except: ['api.v1.matches.contact.show']);
        }
    }

    /**
     * An agreement between two accounts with known contacts, a message, a report and
     * notifications, so that every page has something to show.
     */
    private function createAgreement(): void
    {
        [$this->rosso, $this->hodh, $this->match] = $this->createMatchedPair();
        $this->rosso->forceFill(['email' => 'rosso@example.org', 'phone' => '+22241111111', 'phone_verified_at' => now()])->save();
        $this->hodh->forceFill(['email' => 'hodh@example.org', 'phone' => '+22242222222', 'phone_verified_at' => now()])->save();

        app(InviteToMatch::class)->handle($this->rosso, $this->match->public_id);
        app(AcceptMatch::class)->handle($this->hodh, $this->match->public_id);
        app(SendMatchMessage::class)->handle($this->hodh, $this->match->public_id, 'Bonjour');
        app(CreateUserReport::class)->handle($this->rosso, $this->match->public_id, ReportReason::Spam);
    }

    /**
     * @return list<User>
     */
    private function viewers(): array
    {
        return [$this->rosso, $this->hodh, User::factory()->moderator()->create(), User::factory()->administrator()->create()];
    }

    /**
     * @param  list<string>  $except  routes allowed to show the counterpart's contact
     */
    private function assertNothingLeaksTo(User $viewer, array $except = []): void
    {
        $ownRequest = MobilityRequest::query()->where('user_id', $viewer->id)->first();
        $parameters = [
            'match' => $this->match->public_id,
            'mobilityRequest' => $ownRequest->public_id ?? '01J00000000000000000000000',
            'wilayaCode' => '06',
        ];
        $token = $this->tokenFor($viewer);
        $checked = 0;

        foreach (Router::getRoutes()->getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true) || in_array($route->getName(), $except, true)) {
                continue;
            }

            $isApi = str_starts_with($route->uri(), 'api/v1/');
            $isPrivatePage = ! str_starts_with($route->uri(), 'api/') && in_array('auth', $route->gatherMiddleware(), true);

            if (! $isApi && ! $isPrivatePage) {
                continue;
            }

            $url = '/'.ltrim((string) preg_replace_callback('/\{(\w+)\??\}/', fn (array $name): string => $parameters[$name[1]], $route->uri()), '/');
            $this->flushHeaders();
            $this->app['auth']->forgetGuards();

            $response = $isApi
                ? $this->withToken($token)->getJson($url)
                : $this->actingAs($viewer)->get($url);

            foreach (['rosso@example.org', 'hodh@example.org', '41111111', '42222222'] as $contact) {
                $this->assertStringNotContainsString($contact, (string) $response->getContent(), "{$route->getName()} shows {$contact} to {$viewer->role->value} #{$viewer->public_id}.");
            }

            $checked++;
        }

        $this->assertGreaterThan(30, $checked, 'The readable routes could not be collected.');
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('test', [TokenAbility::AccessApi->value])->plainTextToken;
    }
}
