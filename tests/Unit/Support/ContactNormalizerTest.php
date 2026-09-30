<?php

namespace Tests\Unit\Support;

use App\Enums\ContactType;
use App\Support\ContactNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ContactNormalizerTest extends TestCase
{
    /**
     * @return array<string, array{0: string|null, 1: string|null}>
     */
    public static function emails(): array
    {
        return [
            'lower-cases' => ['User@Example.COM', 'user@example.com'],
            'trims' => ['  user@example.com  ', 'user@example.com'],
            'blank becomes null' => ['   ', null],
            'empty becomes null' => ['', null],
            'null stays null' => [null, null],
        ];
    }

    #[DataProvider('emails')]
    public function test_normalizes_an_email(?string $raw, ?string $expected): void
    {
        $this->assertSame($expected, ContactNormalizer::email($raw));
    }

    /**
     * @return array<string, array{0: string|null, 1: string|null}>
     */
    public static function phones(): array
    {
        return [
            'already E.164' => ['+22241111111', '+22241111111'],
            'spaces and dashes' => ['+222 41-11 11.11', '+22241111111'],
            'parentheses' => ['(+222) 4111 1111', '+22241111111'],
            'double zero prefix' => ['00222 41111111', '+22241111111'],
            'national format gets the default country code' => ['41 11 11 11', '+22241111111'],
            'another country is kept' => ['+33 6 12 34 56 78', '+33612345678'],
            'blank becomes null' => ['  ', null],
            'no digit becomes null' => ['abc', null],
            'null stays null' => [null, null],
        ];
    }

    #[DataProvider('phones')]
    public function test_normalizes_a_phone_to_e164(?string $raw, ?string $expected): void
    {
        $this->assertSame($expected, ContactNormalizer::phone($raw));
    }

    public function test_uses_the_given_default_country_code_for_a_national_number(): void
    {
        $this->assertSame('+212612345678', ContactNormalizer::phone('612345678', '212'));
    }

    public function test_detects_the_kind_of_an_unknown_contact(): void
    {
        $this->assertSame(ContactType::Email, ContactType::detect('user@example.com'));
        $this->assertSame(ContactType::Phone, ContactType::detect('+222 41 11 11 11'));
    }

    public function test_normalizes_an_unknown_contact_according_to_its_kind(): void
    {
        $this->assertSame('user@example.com', ContactNormalizer::normalize(' USER@example.com '));
        $this->assertSame('+22241111111', ContactNormalizer::normalize('41 11 11 11'));
    }

    /**
     * @return array<string, array{0: string|null, 1: bool}>
     */
    public static function phoneValidity(): array
    {
        return [
            'valid' => ['+22241111111', true],
            'without plus' => ['22241111111', false],
            'leading zero after plus' => ['+0222411111', false],
            'too short' => ['+22241', false],
            'too long' => ['+2224111111111111', false],
            'letters' => ['+222abc1111', false],
            'null' => [null, false],
        ];
    }

    #[DataProvider('phoneValidity')]
    public function test_recognizes_a_well_formed_e164_number(?string $phone, bool $valid): void
    {
        $this->assertSame($valid, ContactNormalizer::isValidPhone($phone));
    }
}
