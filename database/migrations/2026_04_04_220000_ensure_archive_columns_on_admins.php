<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent: ensures `is_archived`, `archived_by`, `archived_on` exist on `admins`.
 * Complements 2026_04_04_210000 (PostgreSQL ADD COLUMN IF NOT EXISTS is reliable when Schema::table mis-detects state).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admins')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE admins ADD COLUMN IF NOT EXISTS is_archived SMALLINT NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE admins ADD COLUMN IF NOT EXISTS archived_by BIGINT NULL');
            DB::statement('ALTER TABLE admins ADD COLUMN IF NOT EXISTS archived_on TIMESTAMP NULL');

            $fkExists = DB::selectOne(
                "SELECT 1 AS x FROM pg_constraint c
                 JOIN pg_class t ON c.conrelid = t.oid
                 WHERE t.relname = 'admins' AND c.contype = 'f'
                 AND pg_get_constraintdef(c.oid) LIKE '%archived_by%'"
            );
            if (! $fkExists) {
                try {
                    DB::statement(
                        'ALTER TABLE admins ADD CONSTRAINT admins_archived_by_foreign
                         FOREIGN KEY (archived_by) REFERENCES admins(id) ON DELETE SET NULL'
                    );
                } catch (\Throwable) {
                    //
                }
            }

            try {
                DB::statement('CREATE INDEX IF NOT EXISTS admins_archived_by_index ON admins (archived_by)');
            } catch (\Throwable) {
                //
            }

            return;
        }

        if (! Schema::hasColumn('admins', 'is_archived')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->unsignedTinyInteger('is_archived')->default(0);
            });
        }
        if (! Schema::hasColumn('admins', 'archived_by')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->unsignedBigInteger('archived_by')->nullable();
            });
        }
        if (! Schema::hasColumn('admins', 'archived_on')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->timestamp('archived_on')->nullable();
            });
        }

        try {
            Schema::table('admins', function (Blueprint $table) {
                $table->foreign('archived_by')
                    ->references('id')
                    ->on('admins')
                    ->onDelete('set null');
            });
        } catch (\Throwable) {
            //
        }

        try {
            Schema::table('admins', function (Blueprint $table) {
                $table->index('archived_by');
            });
        } catch (\Throwable) {
            //
        }
    }

    public function down(): void
    {
        //
    }
};
