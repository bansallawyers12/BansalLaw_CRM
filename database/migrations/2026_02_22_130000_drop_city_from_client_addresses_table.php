<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Drops city column from client_addresses. City was redundant with suburb
     * (both stored the same value). Use suburb only.
     */
    public function up(): void
    {
        if (! Schema::hasTable('client_addresses') || ! Schema::hasColumn('client_addresses', 'city')) {
            return;
        }

        Schema::table('client_addresses', function (Blueprint $table) {
            $table->dropColumn('city');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('client_addresses') || Schema::hasColumn('client_addresses', 'city')) {
            return;
        }

        Schema::table('client_addresses', function (Blueprint $table) {
            $table->string('city')->nullable();
        });
    }
};
