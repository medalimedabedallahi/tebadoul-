<?php

namespace Tests\Feature\Livewire\Concerns;

use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Concerns\AppliesNamedRateLimiter;
use App\Models\User;
use App\Support\RateLimiting\AuthRateLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Feature\Api\V1\Auth\RateLimiterContactSpoofingTest;
use Tests\Support\InteractsWithContactCodes;
use Tests\TestCase;

/**
 * {@see AppliesNamedRateLimiter} applies limits computed by {@see AuthRateLimits} from the value
 * held by the component: nothing in the request (query string or body of `/livewire/update`) can
 * change which contact keys the limit.
 */
class AppliesNamedRateLimiterTest extends TestCase
{
    use InteractsWithContactCodes;
    use RefreshDatabase;

    public function test_repeated_calls_for_the_same_contact_are_throttled(): void
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->harness()->apply('otp-send', 'victime@example.com');
        }

        $this->expectException(ValidationException::class);

        $this->harness()->apply('otp-send', 'victime@example.com');
    }

    public function test_calls_for_different_contacts_are_not_mixed_together(): void
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->harness()->apply('otp-send', 'first@example.com');
        }

        // The 4th call for "first@example.com" would throw; a different contact must still pass.
        $this->harness()->apply('otp-send', 'second@example.com');

        $this->addToAssertionCount(1);
    }

    public function test_the_web_and_the_api_share_the_same_counters(): void
    {
        $this->captureContactCodes();

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->postJson('/api/v1/auth/password/forgot', ['contact' => 'victime@example.com'])->assertAccepted();
        }

        $this->expectException(ValidationException::class);

        $this->harness()->apply('password-reset', 'victime@example.com');
    }

    /**
     * V1 (security audit, fixed): a query-string parameter such as `?identifier=...` appended to
     * the real /livewire/update URL used to replace the contact of the component in the limiter
     * key (the same bypass as {@see RateLimiterContactSpoofingTest}). The limits are now computed
     * from the component's value only.
     */
    public function test_v1_a_spoofed_identifier_query_parameter_does_not_bypass_the_contact_limit(): void
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->swapRequest(Request::create('/livewire/update?identifier=attacker-'.$attempt.'@example.com&contact=attacker-'.$attempt.'@example.com', 'POST'));

            $this->harness()->apply('password-reset', 'victime@example.com');
        }

        $this->swapRequest(Request::create('/livewire/update?identifier=attacker-99@example.com', 'POST', ['identifier' => 'x@example.com']));

        $this->expectException(ValidationException::class);

        $this->harness()->apply('password-reset', 'victime@example.com');
    }

    public function test_v1_a_real_component_ignores_spoofed_query_parameters(): void
    {
        $this->captureContactCodes();
        User::factory()->create(['email' => 'victime@example.com']);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            Livewire::withQueryParams(['identifier' => "attacker-$attempt@example.com", 'contact' => "attacker-$attempt@example.com"])
                ->test(ForgotPassword::class)
                ->set('contact', 'victime@example.com')
                ->call('request')
                ->assertHasNoErrors();
        }

        Livewire::withQueryParams(['identifier' => 'attacker-99@example.com'])
            ->test(ForgotPassword::class)
            ->set('contact', 'victime@example.com')
            ->call('request')
            ->assertHasErrors(['contact']);
    }

    private function swapRequest(Request $request): void
    {
        $this->app->instance('request', $request);
    }

    /**
     * A minimal object using the trait under test, the way every Livewire auth component does.
     */
    private function harness(): object
    {
        return new class
        {
            use AppliesNamedRateLimiter;

            public function apply(string $limiterName, string $contact): void
            {
                $limits = match ($limiterName) {
                    'otp-send' => $this->authRateLimits()->otpSend($contact, (string) request()->ip()),
                    'password-reset' => $this->authRateLimits()->passwordReset($contact, (string) request()->ip()),
                };

                $this->applyNamedRateLimiter($limiterName, $limits, 'contact', 'auth.verification.throttled_send');
            }
        };
    }
}
