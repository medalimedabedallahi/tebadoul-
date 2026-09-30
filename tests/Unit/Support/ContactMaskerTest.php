<?php

namespace Tests\Unit\Support;

use App\Support\ContactMasker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ContactMaskerTest extends TestCase
{
    /**
     * @return array<string, array{0: string|null, 1: string|null}>
     */
    public static function emails(): array
    {
        return [
            'usual address' => ['mohamed@example.org', 'm***@e***.org'],
            'subdomain keeps the last suffix only' => ['a.b@mail.example.com', 'a***@m***.com'],
            'no at sign' => ['nonsense', '***'],
            'nothing before the at sign' => ['@example.org', '***'],
            'nothing after the at sign' => ['user@', '***'],
            'null' => [null, null],
            'empty' => ['', null],
        ];
    }

    #[DataProvider('emails')]
    public function test_masks_an_email(?string $email, ?string $expected): void
    {
        $this->assertSame($expected, ContactMasker::email($email));
    }

    /**
     * @return array<string, array{0: string|null, 1: string|null}>
     */
    public static function phones(): array
    {
        return [
            'mauritanian number' => ['+22241111111', '+222*****11'],
            'other country code' => ['+33612345678', '+*****78'],
            'too short to mask' => ['+2221', '***'],
            'null' => [null, null],
            'empty' => ['', null],
        ];
    }

    #[DataProvider('phones')]
    public function test_masks_a_phone_number(?string $phone, ?string $expected): void
    {
        $this->assertSame($expected, ContactMasker::phone($phone));
    }

    public function test_a_masked_value_never_contains_the_middle_of_the_contact(): void
    {
        $this->assertStringNotContainsString('4111111', (string) ContactMasker::phone('+22241111111'));
        $this->assertStringNotContainsString('ohame', (string) ContactMasker::email('mohamed@example.org'));
        $this->assertStringNotContainsString('xample', (string) ContactMasker::email('mohamed@example.org'));
    }

    public function test_mask_picks_the_kind_of_contact_from_the_value(): void
    {
        $this->assertSame('m***@e***.org', ContactMasker::mask('mohamed@example.org'));
        $this->assertSame('+222*****11', ContactMasker::mask('+22241111111'));
        $this->assertNull(ContactMasker::mask(null));
    }
}
