<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('appointment_consultants')) {
            return;
        }

        DB::table('appointment_consultants')
            ->where('calendar_type', 'ajay')
            ->whereIn('email', ['ajay@bansalmigration.com', 'ajay@bansalimmigration.com', 'ajay@bansalimmigration.com.au'])
            ->update(['email' => 'ajay@bansallawyers.com.au']);

        DB::table('appointment_consultants')
            ->where('calendar_type', 'kunal')
            ->whereIn('email', ['kunal@bansalimmigration.com', 'kunal@bansalimmigration.com.au', 'kunal@bansalmigration.com'])
            ->update(['email' => 'kunal@bansallawyers.com.au']);
    }

    public function down(): void
    {
        // Irreversible data cleanup — previous migration-era addresses are leftovers.
    }
};
