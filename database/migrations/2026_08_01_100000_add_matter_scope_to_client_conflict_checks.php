<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_conflict_checks')) {
            return;
        }

        Schema::table('client_conflict_checks', function (Blueprint $table) {
            if (! Schema::hasColumn('client_conflict_checks', 'client_matter_id')) {
                $table->unsignedBigInteger('client_matter_id')->nullable()->index()->after('client_id');
            }
            if (! Schema::hasColumn('client_conflict_checks', 'match_count')) {
                $table->unsignedSmallInteger('match_count')->default(0);
            }
            if (! Schema::hasColumn('client_conflict_checks', 'informational_count')) {
                $table->unsignedSmallInteger('informational_count')->default(0);
            }
            if (! Schema::hasColumn('client_conflict_checks', 'informational_matches')) {
                $table->json('informational_matches')->nullable();
            }
            if (! Schema::hasColumn('client_conflict_checks', 'parties_snapshot_at')) {
                $table->timestamp('parties_snapshot_at')->nullable();
            }
            if (! Schema::hasColumn('client_conflict_checks', 'search_hash')) {
                $table->string('search_hash', 64)->nullable();
            }
        });

        if (Schema::hasTable('client_matters') && Schema::hasColumn('client_conflict_checks', 'client_matter_id')) {
            try {
                Schema::table('client_conflict_checks', function (Blueprint $table) {
                    $table->foreign('client_matter_id')
                        ->references('id')
                        ->on('client_matters')
                        ->nullOnDelete();
                });
            } catch (\Throwable $e) {
                if (! str_contains(strtolower($e->getMessage()), 'already exists')) {
                    throw $e;
                }
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('client_conflict_checks')) {
            return;
        }

        try {
            Schema::table('client_conflict_checks', function (Blueprint $table) {
                $table->dropForeign(['client_matter_id']);
            });
        } catch (\Throwable $e) {
            // ignore
        }

        Schema::table('client_conflict_checks', function (Blueprint $table) {
            foreach (['search_hash', 'parties_snapshot_at', 'informational_matches', 'informational_count', 'match_count', 'client_matter_id'] as $col) {
                if (Schema::hasColumn('client_conflict_checks', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
