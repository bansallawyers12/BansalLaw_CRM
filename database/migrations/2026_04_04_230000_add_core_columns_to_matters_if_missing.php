<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stub `matters` (2025_09_01) only had id + timestamps; the app expects title, nick_name, status, workflow_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('matters')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE matters ADD COLUMN IF NOT EXISTS title VARCHAR(255) NOT NULL DEFAULT ''");
            DB::statement('ALTER TABLE matters ADD COLUMN IF NOT EXISTS nick_name VARCHAR(255) NULL');
            DB::statement('ALTER TABLE matters ADD COLUMN IF NOT EXISTS status SMALLINT NOT NULL DEFAULT 1');
            DB::statement('ALTER TABLE matters ADD COLUMN IF NOT EXISTS workflow_id BIGINT NULL');

            return;
        }

        Schema::table('matters', function (Blueprint $table) {
            if (! Schema::hasColumn('matters', 'title')) {
                $table->string('title')->default('');
            }
        });
        Schema::table('matters', function (Blueprint $table) {
            if (! Schema::hasColumn('matters', 'nick_name')) {
                $table->string('nick_name')->nullable();
            }
        });
        Schema::table('matters', function (Blueprint $table) {
            if (! Schema::hasColumn('matters', 'status')) {
                $table->unsignedTinyInteger('status')->default(1);
            }
        });
        Schema::table('matters', function (Blueprint $table) {
            if (! Schema::hasColumn('matters', 'workflow_id')) {
                $table->unsignedBigInteger('workflow_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        //
    }
};
