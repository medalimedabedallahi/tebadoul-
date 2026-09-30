<?php

namespace Tests\Feature\Database;

use App\Enums\ContactPurpose;
use App\Models\ContactVerification;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ContactVerificationsConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_one_code_can_be_active_per_contact_and_purpose(): void
    {
        ContactVerification::factory()->create(['contact' => 'a@example.com']);

        $this->expectException(UniqueConstraintViolationException::class);

        ContactVerification::factory()->create(['contact' => 'a@example.com']);
    }

    public function test_a_consumed_code_does_not_block_a_new_one(): void
    {
        ContactVerification::factory()->consumed()->create(['contact' => 'a@example.com']);
        ContactVerification::factory()->consumed()->create(['contact' => 'a@example.com']);
        ContactVerification::factory()->create(['contact' => 'a@example.com']);

        $this->assertSame(3, ContactVerification::query()->count());
    }

    public function test_the_same_contact_can_have_one_active_code_per_purpose(): void
    {
        ContactVerification::factory()->create(['contact' => 'a@example.com']);
        ContactVerification::factory()->forPasswordReset()->create(['contact' => 'a@example.com']);

        $this->assertSame(2, ContactVerification::query()->unconsumed()->count());
        $this->assertSame(ContactPurpose::PasswordReset, ContactVerification::query()->latest('id')->firstOrFail()->purpose);
    }

    public function test_the_table_has_the_lookup_indexes(): void
    {
        $columns = collect(Schema::getIndexes('contact_verifications'))->map(fn (array $index): array => $index['columns']);

        $this->assertTrue($columns->contains(['contact', 'purpose']));
        $this->assertTrue($columns->contains(['expires_at']));
    }

    public function test_deleting_a_user_deletes_their_codes(): void
    {
        $user = User::factory()->create();
        ContactVerification::factory()->for($user)->create();

        $user->delete();

        $this->assertSame(0, ContactVerification::query()->count());
    }

    public function test_the_code_is_never_serialized(): void
    {
        $serialized = ContactVerification::factory()->create()->toArray();

        $this->assertArrayNotHasKey('code_hash', $serialized);
        $this->assertArrayNotHasKey('contact', $serialized);
    }

    public function test_emails_are_unique_whatever_their_case_even_when_the_model_is_bypassed(): void
    {
        DB::table('users')->insert($this->row('a@example.com'));

        $this->expectException(QueryException::class);

        DB::table('users')->insert($this->row('A@Example.com'));
    }

    public function test_the_model_stores_emails_in_lower_case(): void
    {
        User::factory()->create(['email' => 'MixedCase@Example.com']);

        $this->assertSame('mixedcase@example.com', User::query()->firstOrFail()->email);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $email): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'name' => 'Raw',
            'email' => $email,
            'password' => 'x',
            'status' => 'active',
            'locale' => 'fr',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
