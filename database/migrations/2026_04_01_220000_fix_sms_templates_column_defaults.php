<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Restore default on usage_count (PostgreSQL drift left NOT NULL without a DB default).
     * is_active is not altered: PG column type may be integer/smallint and cannot auto-cast via Schema builder.
     */
    public function up(): void
    {
        if (! Schema::hasTable('sms_templates')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE sms_templates ALTER COLUMN usage_count SET DEFAULT 0');
        } elseif ($driver === 'mysql') {
            DB::statement('ALTER TABLE sms_templates MODIFY usage_count INT NOT NULL DEFAULT 0');
        }
    }

    public function down(): void
    {
        // Intentionally empty: dropping defaults is environment-specific and rarely needed.
    }
};
