<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONSTRAINT = 'users_contact_required_check';

    /**
     * An account deleted by its owner is anonymized, not removed: the audit trail, the reports and
     * the other participant's conversations still reference it. It then keeps no contact detail,
     * so the PostgreSQL constraint requiring one exempts the `deleted` status (and only it).
     *
     * Expand only: the new constraint is looser than the old one, so the previous release keeps
     * working against this schema.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('anonymized_at')->nullable()->after('status');
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS '.self::CONSTRAINT);
        DB::statement(
            'ALTER TABLE users ADD CONSTRAINT '.self::CONSTRAINT
            ." CHECK (email IS NOT NULL OR phone IS NOT NULL OR status = 'deleted')"
        );
    }

    /**
     * Restores the stricter constraint without checking the existing rows (NOT VALID): anonymized
     * accounts created meanwhile would otherwise make the rollback fail.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS '.self::CONSTRAINT);
            DB::statement(
                'ALTER TABLE users ADD CONSTRAINT '.self::CONSTRAINT
                .' CHECK (email IS NOT NULL OR phone IS NOT NULL) NOT VALID'
            );
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('anonymized_at');
        });
    }
};
