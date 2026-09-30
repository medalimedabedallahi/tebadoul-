<?php

namespace Tests\Feature\Support;

use App\Contracts\ContactCodeSender;
use App\Contracts\SmsGateway;
use App\Enums\ContactPurpose;
use App\Models\ContactVerification;
use App\Models\User;
use App\Support\ContactCodes\ChannelContactCodeSender;
use App\Support\Sms\LogSmsGateway;
use App\Support\Sms\NullSmsGateway;
use App\Support\VerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Tests\Support\RecordingSmsGateway;
use Tests\TestCase;

class ContactCodeDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sender_contract_resolves_to_the_channel_sender(): void
    {
        $this->assertInstanceOf(ChannelContactCodeSender::class, app(ContactCodeSender::class));
    }

    public function test_the_sms_gateway_follows_the_configured_driver(): void
    {
        config(['services.sms.driver' => 'log']);
        $this->assertInstanceOf(LogSmsGateway::class, app(SmsGateway::class));

        config(['services.sms.driver' => 'null']);
        $this->assertInstanceOf(NullSmsGateway::class, app(SmsGateway::class));
    }

    public function test_the_log_driver_never_writes_the_code_or_the_full_number(): void
    {
        $logged = $this->collectLogs();

        (new LogSmsGateway)->send('+22241111111', 'Badal: your verification code is 482915.');

        $this->assertNotEmpty($logged->all());
        $dump = json_encode($logged->all(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('482915', $dump);
        $this->assertStringNotContainsString('41111111', $dump);
        $this->assertStringContainsString('+222*****11', $dump);
    }

    public function test_the_null_driver_sends_and_logs_nothing(): void
    {
        $logged = $this->collectLogs();

        (new NullSmsGateway)->send('+22241111111', 'code 482915');

        $this->assertSame([], $logged->all());
    }

    public function test_the_sms_carries_the_code_and_the_lifetime_in_the_language_of_the_recipient(): void
    {
        $gateway = new RecordingSmsGateway;
        $this->app->instance(SmsGateway::class, $gateway);
        $arabic = User::factory()->create(['email' => null, 'phone' => '+22241111111', 'locale' => 'ar']);
        $verification = ContactVerification::factory()->for($arabic)->forPhone('+22241111111')->create();

        app(ContactCodeSender::class)->send($verification, '482915');

        $this->assertStringContainsString('482915', $gateway->sent[0]['message']);
        $this->assertStringContainsString('بدل', $gateway->sent[0]['message']);
        $this->assertStringContainsString('10', $gateway->sent[0]['message']);
    }

    public function test_the_sms_for_a_password_reset_says_so(): void
    {
        $gateway = new RecordingSmsGateway;
        $this->app->instance(SmsGateway::class, $gateway);
        $verification = ContactVerification::factory()->forPhone('+22241111111')->forPasswordReset()->create();

        app(ContactCodeSender::class)->send($verification, '482915');

        $this->assertStringContainsString('réinitialisation', $gateway->sent[0]['message']);
    }

    public function test_a_code_hash_is_bound_to_its_contact_and_purpose(): void
    {
        $hash = VerificationCode::hash('a@example.com', ContactPurpose::ContactVerification, '123456');

        $this->assertTrue(VerificationCode::matches($hash, 'a@example.com', ContactPurpose::ContactVerification, '123456'));
        $this->assertFalse(VerificationCode::matches($hash, 'b@example.com', ContactPurpose::ContactVerification, '123456'));
        $this->assertFalse(VerificationCode::matches($hash, 'a@example.com', ContactPurpose::PasswordReset, '123456'));
        $this->assertFalse(VerificationCode::matches($hash, 'a@example.com', ContactPurpose::ContactVerification, '123457'));
    }

    public function test_a_code_hash_depends_on_the_application_key(): void
    {
        $before = VerificationCode::hash('a@example.com', ContactPurpose::ContactVerification, '123456');

        config(['app.key' => 'base64:'.base64_encode(str_repeat('k', 32))]);

        $this->assertNotSame($before, VerificationCode::hash('a@example.com', ContactPurpose::ContactVerification, '123456'));
    }

    /**
     * @return Collection<int, array{level: string, message: string, context: array<string, mixed>}>
     */
    private function collectLogs(): Collection
    {
        $logged = collect();

        Event::listen(MessageLogged::class, function (MessageLogged $event) use ($logged): void {
            $logged->push(['level' => $event->level, 'message' => $event->message, 'context' => $event->context]);
        });

        return $logged;
    }
}
