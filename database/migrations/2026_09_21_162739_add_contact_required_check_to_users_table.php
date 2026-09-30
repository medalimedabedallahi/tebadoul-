<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CONSTRAINT = 'users_contact_required_check';

    /**
     * Require at least one contact detail (email or phone) on every account.
     *
     * The constraint is enforced by PostgreSQL only. SQLite, used by the fast test suite, cannot
     * add a CHECK constraint to an existing table without rebuilding it, so it relies on
     * application-level validation.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            'ALTER TABLE users ADD CONSTRAINT '.self::CONSTRAINT.' CHECK (email IS NOT NULL OR phone IS NOT NULL)'
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS '.self::CONSTRAINT);
    }
};
