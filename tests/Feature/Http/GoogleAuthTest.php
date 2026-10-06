<?php

namespace Tests\Feature\Http;

use App\Models\User;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use PHPUnit\Framework\Attributes\TestWith;
use Psr\Http\Message\RequestInterface;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_forms_offer_google_and_explain_missing_configuration(): void
    {
        config(['services.google.client_id' => null, 'services.google.client_secret' => null]);

        foreach (['login', 'register'] as $route) {
            $this->get(route($route))->assertSee(__('auth.google.continue'))->assertSee(__('auth.google.unavailable'));
        }
        $this->get(route('google.redirect'))->assertRedirect(route('login'))->assertSessionHas('status', __('auth.google.unavailable'));
    }

    public function test_redirect_uses_session_state_and_only_identity_scopes(): void
    {
        $this->configureGoogle();

        $response = $this->get(route('google.redirect'));

        $response->assertRedirect();
        $this->assertSame('accounts.google.com', parse_url($response->headers->get('Location'), PHP_URL_HOST));
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame('openid profile email', $query['scope']);
        $this->assertSame('http://localhost/auth/google/callback', $query['redirect_uri']);
        $response->assertSessionHas('state', $query['state']);
        $response->assertSessionHas('google.flow', fn (array $flow): bool => $flow['user_id'] === null);
    }

    public function test_callback_stages_only_identity_data_and_does_not_create_an_account(): void
    {
        $this->configureGoogle();
        $this->mockGoogle();

        $response = $this->withSession($this->flow())->get(route('google.callback'));

        $response->assertRedirect(route('google.register'))
            ->assertSessionMissing('google.flow')
            ->assertSessionHas('google.pending.email', 'aminetou@gmail.com');
        $this->assertSame(['subject', 'email', 'name', 'expires_at'], array_keys(session('google.pending')));
        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    public function test_google_registration_uses_server_identity_not_posted_contact_or_role(): void
    {
        $this->configureGoogle();
        $this->freezeTime();

        $response = $this->withSession($this->pending())->post(route('google.register.store'), $this->registrationInput() + [
            'google_id' => 'attacker-subject', 'email' => 'attacker@example.com', 'role' => 'administrator',
        ]);

        $response->assertRedirect(route('account.show'))->assertSessionMissing('google.pending');
        $user = User::query()->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('aminetou@gmail.com', $user->email);
        $this->assertSame('google-subject-123', $user->google_id);
        $this->assertSame('user', $user->role->value);
    }

    public function test_registration_without_terms_does_not_open_a_session(): void
    {
        $this->configureGoogle();
        $input = $this->registrationInput();
        unset($input['accept_terms']);

        $this->withSession($this->pending())->from(route('google.register'))->post(route('google.register.store'), $input)
            ->assertRedirect(route('google.register'))->assertSessionHasErrors('accept_terms');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_cannot_be_submitted_without_a_verified_google_flow(): void
    {
        $this->configureGoogle();

        $this->post(route('google.register.store'), $this->registrationInput())
            ->assertRedirect(route('login'))->assertSessionHas('status', __('auth.google.expired'));

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_expired_registration_is_cleared(): void
    {
        $this->configureGoogle();
        $this->freezeTime();
        $pending = $this->pending();
        $this->travel(10)->minutes();

        $this->withSession($pending)->post(route('google.register.store'), $this->registrationInput())
            ->assertRedirect(route('login'))->assertSessionMissing('google.pending');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_changed_terms_must_be_read_again_before_creation(): void
    {
        $this->configureGoogle();
        $input = $this->registrationInput();
        $input['terms_version'] = 'old-version';

        $this->withSession($this->pending())->from(route('google.register'))->post(route('google.register.store'), $input)
            ->assertSessionHasErrors('terms_version');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_page_escapes_google_data_and_supports_arabic(): void
    {
        $this->configureGoogle();
        $pending = $this->pending();
        $pending['google.pending']['name'] = '<script>alert(1)</script>';

        $this->withSession($pending + ['locale' => 'ar'])->get(route('google.register'))
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertDontSeeHtml('<script>alert(1)</script>')
            ->assertSee(route('legal.terms'));
    }

    public function test_linked_google_account_opens_a_session(): void
    {
        $this->configureGoogle();
        $this->mockGoogle();
        $user = User::factory()->create(['email' => 'aminetou@gmail.com', 'google_id' => 'google-subject-123']);

        $this->withSession($this->flow())->get(route('google.callback'))->assertRedirect(route('account.show'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_callback_without_flow_cannot_authenticate(): void
    {
        $this->configureGoogle();
        Socialite::shouldReceive('driver')->never();

        $this->get(route('google.callback'))->assertRedirect(route('login'))->assertSessionHas('status', __('auth.google.failed'));

        $this->assertGuest();
    }

    public function test_real_socialite_rejects_mismatched_state_before_network_access(): void
    {
        $this->configureGoogle();
        Socialite::shouldReceive('driver')->with('google')->once()->andReturnUsing(
            fn () => new GoogleProvider(request(), 'client-id', 'client-secret', 'http://localhost/auth/google/callback'),
        );

        $this->withSession($this->flow() + ['state' => 'expected-state'])->get(route('google.callback', ['code' => 'code', 'state' => 'wrong-state']))
            ->assertRedirect(route('login'))->assertSessionMissing('google.pending')->assertSessionHas('status', __('auth.google.failed'));

        $this->assertGuest();
    }

    #[TestWith([false])]
    #[TestWith(['true'])]
    public function test_google_email_must_be_explicitly_verified(bool|string $verified): void
    {
        $this->configureGoogle();
        $this->mockGoogle($verified);

        $this->withSession($this->flow())->get(route('google.callback'))
            ->assertRedirect(route('login'))->assertSessionMissing('google.pending')->assertSessionHas('status', __('auth.google.unverified'));

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_google_network_failure_returns_a_friendly_error(): void
    {
        $this->configureGoogle();
        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('user')->once()->andThrow(new ConnectException('Unavailable', Mockery::mock(RequestInterface::class)));
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);

        $this->withSession($this->flow())->get(route('google.callback'))
            ->assertRedirect(route('login'))->assertSessionHas('status', __('auth.google.failed'));

        $this->assertGuest();
    }

    public function test_cancelled_google_authorization_clears_state_without_network_access(): void
    {
        $this->configureGoogle();
        Socialite::shouldReceive('driver')->never();

        $this->withSession($this->flow() + ['state' => 'state'])->get(route('google.callback', ['error' => 'access_denied']))
            ->assertRedirect(route('login'))->assertSessionMissing('state')->assertSessionMissing('google.flow');
    }

    public function test_linking_requires_authentication_and_an_active_account(): void
    {
        $this->configureGoogle();
        $this->post(route('google.link'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->suspended()->create())->post(route('google.link'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_owner_can_link_google_from_account_without_replacing_the_password(): void
    {
        $this->configureGoogle();
        $user = User::factory()->create(['email' => 'aminetou@gmail.com']);
        $password = $user->password;
        $this->actingAs($user)->post(route('google.link'))->assertRedirect()->assertSessionHas('google.flow.user_id', $user->id);
        $this->mockGoogle();

        $this->get(route('google.callback'))->assertRedirect(route('account.show'))->assertSessionHas('status', __('auth.google.linked'));

        $this->assertSame('google-subject-123', $user->fresh()->google_id);
        $this->assertSame($password, $user->fresh()->password);
    }

    public function test_linking_is_refused_if_the_signed_in_account_changes_during_oauth(): void
    {
        $this->configureGoogle();
        $owner = User::factory()->create(['email' => 'aminetou@gmail.com']);
        $other = User::factory()->create();
        Socialite::shouldReceive('driver')->never();

        $this->actingAs($other)->withSession($this->flow($owner->id))->get(route('google.callback'))
            ->assertRedirect(route('account.show'))->assertSessionHas('status', __('auth.google.failed'));

        $this->assertNull($owner->fresh()->google_id);
        $this->assertNull($other->fresh()->google_id);
    }

    public function test_existing_unlinked_account_receives_linking_instructions_not_a_session(): void
    {
        $this->configureGoogle();
        $this->mockGoogle();
        $user = User::factory()->create(['email' => 'aminetou@gmail.com']);

        $this->withSession($this->flow())->get(route('google.callback'))
            ->assertRedirect(route('login'))->assertSessionHas('status', __('auth.google.existing_account'));

        $this->assertGuest();
        $this->assertNull($user->fresh()->google_id);
    }

    private function configureGoogle(): void
    {
        config(['services.google.client_id' => 'client-id', 'services.google.client_secret' => 'client-secret',
            'services.google.redirect' => 'http://localhost/auth/google/callback']);
    }

    private function mockGoogle(bool|string $verified = true): void
    {
        $providerUser = (new GoogleUser)->setRaw(['email_verified' => $verified])->map([
            'id' => 'google-subject-123', 'name' => 'Aminetou', 'email' => 'aminetou@gmail.com',
        ])->setToken('never-store-this-token');
        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('user')->once()->andReturn($providerUser);
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);
    }

    /** @return array<string, array{user_id: int|null, started_at: int}> */
    private function flow(?int $userId = null): array
    {
        return ['google.flow' => ['user_id' => $userId, 'started_at' => now()->timestamp]];
    }

    /** @return array<string, array{subject: string, email: string, name: string, expires_at: int}> */
    private function pending(): array
    {
        return ['google.pending' => ['subject' => 'google-subject-123', 'email' => 'aminetou@gmail.com',
            'name' => 'Aminetou', 'expires_at' => now()->addMinutes(10)->timestamp]];
    }

    /** @return array{name: string, accept_terms: bool, terms_version: string} */
    private function registrationInput(): array
    {
        return ['name' => 'Aminetou', 'accept_terms' => true, 'terms_version' => (string) config('legal.version')];
    }
}
