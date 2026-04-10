<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_matters')) {
            return;
        }

        if (Schema::hasColumn('client_matters', 'sel_legal_practitioner')) {
            if (Schema::hasColumn('client_matters', 'sel_migration_agent')) {
                DB::table('client_matters')
                    ->whereNotNull('sel_migration_agent')
                    ->update(['sel_legal_practitioner' => DB::raw('sel_migration_agent')]);

                Schema::table('client_matters', function (Blueprint $table) {
                    $table->dropColumn('sel_migration_agent');
                });
            }

            return;
        }

        if (! Schema::hasColumn('client_matters', 'sel_migration_agent')) {
            return;
        }

        Schema::table('client_matters', function (Blueprint $table) {
            $table->unsignedBigInteger('sel_legal_practitioner')->nullable()->index();
        });

        DB::table('client_matters')->update([
            'sel_legal_practitioner' => DB::raw('sel_migration_agent'),
        ]);

        Schema::table('client_matters', function (Blueprint $table) {
            $table->dropColumn('sel_migration_agent');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('client_matters')) {
            return;
        }

        if (Schema::hasColumn('client_matters', 'sel_migration_agent')) {
            return;
        }

        if (! Schema::hasColumn('client_matters', 'sel_legal_practitioner')) {
            return;
        }

        Schema::table('client_matters', function (Blueprint $table) {
            $table->unsignedBigInteger('sel_migration_agent')->nullable()->index();
        });

        DB::table('client_matters')->update([
            'sel_migration_agent' => DB::raw('sel_legal_practitioner'),
        ]);

        Schema::table('client_matters', function (Blueprint $table) {
            $table->dropColumn('sel_legal_practitioner');
        });
    }
};
