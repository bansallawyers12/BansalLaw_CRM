<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Display name for the kunal calendar consultant is now "Michael".
     */
    public function up(): void
    {
        if (! Schema::hasTable('appointment_consultants')) {
            return;
        }

        DB::table('appointment_consultants')
            ->where('calendar_type', 'kunal')
            ->update([
                'name' => 'Michael',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('appointment_consultants')) {
            return;
        }

        DB::table('appointment_consultants')
            ->where('calendar_type', 'kunal')
            ->where('name', 'Michael')
            ->update([
                'name' => 'Kunal Calendar',
                'updated_at' => now(),
            ]);
    }
};
