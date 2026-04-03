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
        Schema::table('client_passport_informations', function (Blueprint $table) {
            // Check if column doesn't already exist before adding
            if (!Schema::hasColumn('client_passport_informations', 'passport_country')) {
                $table->unsignedBigInteger('passport_country')->nullable()
                    ->comment('Country that issued the passport (reference to countries table)');
                
                // Add index for better query performance
                $table->index('passport_country');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_passport_informations', function (Blueprint $table) {
            $table->dropIndex(['passport_country']);
            $table->dropColumn('passport_country');
        });
    }
};
