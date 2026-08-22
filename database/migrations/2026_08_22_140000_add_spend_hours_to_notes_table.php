<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notes') || Schema::hasColumn('notes', 'spend_hours')) {
            return;
        }

        Schema::table('notes', function (Blueprint $table) {
            $table->decimal('spend_hours', 8, 2)->nullable()->after('mobile_number');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('notes') || ! Schema::hasColumn('notes', 'spend_hours')) {
            return;
        }

        Schema::table('notes', function (Blueprint $table) {
            $table->dropColumn('spend_hours');
        });
    }
};
