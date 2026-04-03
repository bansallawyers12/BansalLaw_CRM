<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For PostgreSQL, we need to alter the column type using raw SQL
        // Increase status column from varchar(7) to varchar(20) to accommodate 'cancelled' (9 chars) and future status values
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE signers ALTER COLUMN status TYPE VARCHAR(20) USING status::VARCHAR(20)');
        } else {
            // For MySQL/MariaDB
            Schema::table('signers', function (Blueprint $table) {
                $table->string('status', 20)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to varchar(7) - but only if safe (check for 'cancelled' status first)
        if (DB::getDriverName() === 'pgsql') {
            // Check if any signers have status longer than 7 characters
            $hasLongStatus = DB::table('signers')
                ->whereRaw('LENGTH(status) > 7')
                ->exists();
            
            if (!$hasLongStatus) {
                DB::statement('ALTER TABLE signers ALTER COLUMN status TYPE VARCHAR(7) USING status::VARCHAR(7)');
            }
        } else {
            // For MySQL/MariaDB
            Schema::table('signers', function (Blueprint $table) {
                $table->string('status', 7)->change();
            });
        }
    }
};
