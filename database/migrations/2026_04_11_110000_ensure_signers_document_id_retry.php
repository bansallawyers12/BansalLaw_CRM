<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retry adding signers.document_id if an earlier migration was recorded but the column is still missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('signers')) {
            return;
        }

        if (Schema::hasColumn('signers', 'document_id')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            $exists = DB::selectOne(
                "select 1 as x from information_schema.columns
                 where table_schema = current_schema() and table_name = 'signers' and column_name = 'document_id'
                 limit 1"
            );
            if (! $exists) {
                DB::statement('ALTER TABLE signers ADD COLUMN document_id BIGINT NULL');
                try {
                    DB::statement('CREATE INDEX signers_document_id_index ON signers (document_id)');
                } catch (\Throwable) {
                    //
                }
            }
        } else {
            Schema::table('signers', function (Blueprint $table) {
                $table->unsignedBigInteger('document_id')->nullable()->index();
            });
        }

        if (Schema::hasTable('documents') && Schema::hasColumn('documents', 'id')) {
            try {
                Schema::table('signers', function (Blueprint $table) {
                    $table->foreign('document_id')
                        ->references('id')
                        ->on('documents')
                        ->nullOnDelete();
                });
            } catch (\Throwable) {
                //
            }
        }
    }

    public function down(): void
    {
        //
    }
};
