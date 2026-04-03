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
        // Update existing 'Defacto' records to 'De Facto' in the admins table
        DB::table('admins')
            ->where('martial_status', 'Defacto')
            ->update(['martial_status' => 'De Facto']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert 'De Facto' back to 'Defacto' if needed
        DB::table('admins')
            ->where('martial_status', 'De Facto')
            ->update(['martial_status' => 'Defacto']);
    }
};
