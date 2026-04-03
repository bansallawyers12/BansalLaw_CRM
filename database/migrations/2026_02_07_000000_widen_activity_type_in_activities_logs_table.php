<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Widen activity_type so values like 'office_visit_complete' (21 chars) fit.
     * Column was varchar(20); values used in code can be 21+ chars.
     *
     * @return void
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE activities_logs ALTER COLUMN activity_type TYPE VARCHAR(64) USING activity_type::VARCHAR(64)');
        } else {
            Schema::table('activities_logs', function (Blueprint $table) {
                $table->string('activity_type', 64)->default('note')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE activities_logs ALTER COLUMN activity_type TYPE VARCHAR(20) USING activity_type::VARCHAR(20)');
        } else {
            Schema::table('activities_logs', function (Blueprint $table) {
                $table->string('activity_type', 20)->default('note')->change();
            });
        }
    }
};
