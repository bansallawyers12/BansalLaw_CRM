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
        Schema::table('client_experiences', function (Blueprint $table) {
            // Full-Time Equivalent multiplier for part-time work
            $table->decimal('fte_multiplier', 3, 2)->default(1.00)->after('job_type')
                ->comment('Full-time equivalent multiplier (1.00 = full-time, 0.50 = half-time, etc.)');
            
            // Add index for points calculation queries
            $table->index(['client_id', 'job_country'], 'idx_client_country');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_experiences', function (Blueprint $table) {
            $table->dropIndex('idx_client_country');
            $table->dropColumn('fte_multiplier');
        });
    }
};
