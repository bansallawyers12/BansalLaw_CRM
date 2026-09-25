<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('staff') || ! Schema::hasColumn('staff', 'can_access_personal_calendar')) {
            return;
        }

        DB::table('staff')
            ->where('status', 1)
            ->update(['can_access_personal_calendar' => 1]);
    }

    public function down(): void
    {
        // Intentionally left blank: do not revoke calendar access on rollback.
    }
};
