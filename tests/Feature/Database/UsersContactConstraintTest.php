<?php

namespace Tests\Feature\Database;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class UsersContactConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_postgresql_rejects_a_user_with_neither_email_nor_phone(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('The CHECK constraint is only created on PostgreSQL.');
        }

        try {
            // A savepoint keeps the test transaction usable after PostgreSQL aborts the failed statement.
            DB::transaction(fn () => $this->insertUser(['email' => null, 'phone' => null]));
            $this->fail('The insert without any contact should have been rejected.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('users_contact_required_check', $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 0);
    }

    public function test_postgresql_accepts_an_anonymized_deleted_account_without_contact(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('The CHECK constraint is only created on PostgreSQL.');
        }

        $this->insertUser(['email' => null, 'phone' => null, 'status' => 'deleted']);

        $this->assertDatabaseHas('users', ['email' => null, 'phone' => null, 'status' => 'deleted']);
    }

    public function test_a_user_with_only_an_email_is_accepted_on_every_driver(): void
    {
        $this->insertUser(['email' => 'only.email@example.com', 'phone' => null]);

        $this->assertDatabaseHas('users', ['email' => 'only.email@example.com', 'phone' => null]);
    }

    public function test_a_user_with_only_a_phone_is_accepted_on_every_driver(): void
    {
        $this->insertUser(['email' => null, 'phone' => '+22241111111']);

        $this->assertDatabaseHas('users', ['email' => null, 'phone' => '+22241111111']);
    }

    /**
     * Insert a row straight into the table, bypassing the model so that only the database is tested.
     *
     * @param  array<string, string|null>  $contact
     */
    private function insertUser(array $contact): void
    {
        DB::table('users')->insert([
            'public_id' => (string) Str::ulid(),
            'name' => 'Aminetou',
            'password' => 'not-a-real-hash',
            'created_at' => now(),
            'updated_at' => now(),
            ...$contact,
        ]);
    }
}
