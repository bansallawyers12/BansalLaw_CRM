<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Staff grouping is by user role only. Drop the Teams catalog and the
     * columns that pointed at it.
     */
    public function up(): void
    {
        if (Schema::hasTable('client_access_grants') && Schema::hasColumn('client_access_grants', 'team_id')) {
            Schema::table('client_access_grants', function (Blueprint $table) {
                try {
                    $table->dropForeign(['team_id']);
                } catch (\Throwable $e) {
                    // FK may already be gone or named differently.
                }
                $table->dropColumn('team_id');
            });
        }

        if (Schema::hasTable('client_access_grants') && Schema::hasColumn('client_access_grants', 'team_label_snapshot')) {
            Schema::table('client_access_grants', function (Blueprint $table) {
                $table->dropColumn('team_label_snapshot');
            });
        }

        if (Schema::hasTable('staff') && Schema::hasColumn('staff', 'team')) {
            Schema::table('staff', function (Blueprint $table) {
                $table->dropColumn('team');
            });
        }

        Schema::dropIfExists('teams');
    }

    public function down(): void
    {
        if (! Schema::hasTable('teams')) {
            Schema::create('teams', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('color')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('staff') && ! Schema::hasColumn('staff', 'team')) {
            Schema::table('staff', function (Blueprint $table) {
                $table->string('team')->nullable();
            });
        }

        if (Schema::hasTable('client_access_grants') && ! Schema::hasColumn('client_access_grants', 'team_id')) {
            Schema::table('client_access_grants', function (Blueprint $table) {
                $table->unsignedBigInteger('team_id')->nullable();
                $table->string('team_label_snapshot', 255)->nullable();
                $table->foreign('team_id')->references('id')->on('teams')->nullOnDelete();
            });
        }
    }
};
