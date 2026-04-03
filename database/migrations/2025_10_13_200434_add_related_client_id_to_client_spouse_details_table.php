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
        // Check if column doesn't exist before adding it
        if (!Schema::hasColumn('client_spouse_details', 'related_client_id')) {
            Schema::table('client_spouse_details', function (Blueprint $table) {
                // Add reference to the partner client ID for EOI calculation
                $table->unsignedBigInteger('related_client_id')->nullable()->after('client_id')
                    ->comment('Reference to the partner client ID for EOI calculation');
            });
        }
        
        // Add foreign key constraint
        try {
            Schema::table('client_spouse_details', function (Blueprint $table) {
                $table->foreign('related_client_id')->references('id')->on('admins')
                    ->onDelete('set null');
            });
        } catch (\Exception $e) {
            // Foreign key constraint might already exist, ignore the error
            \Log::info('Foreign key constraint might already exist: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_spouse_details', function (Blueprint $table) {
            // Drop foreign key constraint first
            $table->dropForeign(['related_client_id']);
            
            // Drop the column
            $table->dropColumn('related_client_id');
        });
    }
};
