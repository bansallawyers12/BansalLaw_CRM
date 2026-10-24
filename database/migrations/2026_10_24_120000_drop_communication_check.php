<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('communication_check_runs');

        if (Schema::hasTable('staff') && Schema::hasColumn('staff', 'can_use_communication_check')) {
            Schema::table('staff', function (Blueprint $table) {
                $table->dropColumn('can_use_communication_check');
            });
        }
    }

    public function down(): void
    {
        // Communication Check was removed. Recreate via the August 2026 migrations if needed.
    }
};
