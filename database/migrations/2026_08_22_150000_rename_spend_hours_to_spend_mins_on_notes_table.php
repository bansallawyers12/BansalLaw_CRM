<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notes')) {
            return;
        }

        if (! Schema::hasColumn('notes', 'spend_mins')) {
            Schema::table('notes', function (Blueprint $table) {
                $table->unsignedInteger('spend_mins')->nullable()->after('mobile_number');
            });
        }

        if (Schema::hasColumn('notes', 'spend_hours')) {
            DB::table('notes')
                ->whereNotNull('spend_hours')
                ->update([
                    'spend_mins' => DB::raw('ROUND(spend_hours * 60)'),
                ]);

            Schema::table('notes', function (Blueprint $table) {
                $table->dropColumn('spend_hours');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('notes')) {
            return;
        }

        if (! Schema::hasColumn('notes', 'spend_hours')) {
            Schema::table('notes', function (Blueprint $table) {
                $table->decimal('spend_hours', 8, 2)->nullable()->after('mobile_number');
            });
        }

        if (Schema::hasColumn('notes', 'spend_mins')) {
            DB::table('notes')
                ->whereNotNull('spend_mins')
                ->update([
                    'spend_hours' => DB::raw('ROUND(spend_mins / 60.0, 2)'),
                ]);

            Schema::table('notes', function (Blueprint $table) {
                $table->dropColumn('spend_mins');
            });
        }
    }
};
