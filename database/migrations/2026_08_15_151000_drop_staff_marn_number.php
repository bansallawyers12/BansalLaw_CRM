<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('staff')) {
            return;
        }

        if (Schema::hasColumn('staff', 'marn_number')) {
            Schema::table('staff', function (Blueprint $table) {
                $table->dropColumn('marn_number');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('staff')) {
            return;
        }

        if (! Schema::hasColumn('staff', 'marn_number')) {
            Schema::table('staff', function (Blueprint $table) {
                $table->string('marn_number', 100)->nullable()->after('is_solicitor');
            });
        }
    }
};
