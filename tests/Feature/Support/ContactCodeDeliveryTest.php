<?php

namespace Tests\Feature\Support;

use App\Contracts\ContactCodeSender;
use App\Enums\ContactPurpose;
use App\Support\ContactCodes\EmailContactCodeSender;
use App\Support\VerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactCodeDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sender_contract_resolves_to_the_email_sender(): void
    {
        $this->assertInstanceOf(EmailContactCodeSender::class, app(ContactCodeSender::class));
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
}
