<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const INDEX = 'users_email_lower_unique';

    /**
     * Make the uniqueness of emails case-insensitive at the database level.
     *
     * The application already stores emails lower-cased (see User::email()), so this index only
     * protects against writes that bypass the model. It works on SQLite and PostgreSQL. It fails
     * if two existing emails differ only by case: resolve those rows first (none exist before the
     * first registration).
     */
    public function up(): void
    {
        DB::statement('CREATE UNIQUE INDEX '.self::INDEX.' ON users (LOWER(email))');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS '.self::INDEX);
    }
};
