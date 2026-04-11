<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PostgreSQL / legacy DBs: `signers` may exist without `signed_at`, which breaks
 * SignatureAnalyticsService and e-signature flows that filter on signed_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('signers')) {
            return;
        }

        if (Schema::hasColumn('signers', 'signed_at')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            $exists = DB::selectOne(
                "select 1 as x from information_schema.columns
                 where table_schema = current_schema() and table_name = 'signers' and column_name = 'signed_at'
                 limit 1"
            );
            if ($exists) {
                return;
            }
            DB::statement('ALTER TABLE signers ADD COLUMN signed_at TIMESTAMP(0) WITHOUT TIME ZONE NULL');
               } else {
            Schema::table('signers', function (Blueprint $table) {
                $table->timestamp('signed_at')->nullable();
            });
        }

        try {
            DB::table('signers')
                ->where('status', 'signed')
                ->whereNull('signed_at')
                ->update(['signed_at' => DB::raw('COALESCE(updated_at, created_at)')]);
        } catch (\Throwable) {
            //
        }

        if (! Schema::hasColumn('signers', 'cancelled_at')) {
            if ($driver === 'pgsql') {
                DB::statement('ALTER TABLE signers ADD COLUMN cancelled_at TIMESTAMP(0) WITHOUT TIME ZONE NULL');
            } else {
                Schema::table('signers', function (Blueprint $table) {
                    $table->timestamp('cancelled_at')->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('signers')) {
            return;
        }

        if (Schema::hasColumn('signers', 'signed_at')) {
            Schema::table('signers', function (Blueprint $table) {
                $table->dropColumn('signed_at');
            });
        }
    }
};
