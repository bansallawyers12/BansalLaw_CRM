<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('client_addresses', function (Blueprint $table) {
            $table->string('address_line_1', 255)->nullable()->after('address');
            $table->string('address_line_2', 255)->nullable()->after('address_line_1');
            $table->string('suburb', 100)->nullable()->after('address_line_2');
            $table->string('country', 100)->default('Australia')->after('state');
            
            $table->index('suburb');
            $table->index('country');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_addresses', function (Blueprint $table) {
            $table->dropColumn(['address_line_1', 'address_line_2', 'suburb', 'country']);
        });
    }
};