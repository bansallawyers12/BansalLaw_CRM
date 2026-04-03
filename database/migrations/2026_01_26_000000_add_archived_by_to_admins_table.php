<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds archived_by column to admins table:
     * - archived_by: ID of the admin who archived the client
     */
    public function up(): void
    {
        // Check column existence OUTSIDE the Schema::table closure
        $hasArchivedBy = Schema::hasColumn('admins', 'archived_by');
        
        Schema::table('admins', function (Blueprint $table) use ($hasArchivedBy) {
            // Add archived_by column if it doesn't exist
            if (!$hasArchivedBy) {
                $table->unsignedBigInteger('archived_by')->nullable()
                    ->after('is_archived')
                    ->comment('ID of the admin who archived the client');
            }
        });
        
        // Add foreign key constraint separately (after column is created)
        if (!$hasArchivedBy && Schema::hasColumn('admins', 'archived_by')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->foreign('archived_by')
                    ->references('id')
                    ->on('admins')
                    ->onDelete('set null');
            });
        }
        
        // Add index on archived_by for better query performance
        if (!$hasArchivedBy && Schema::hasColumn('admins', 'archived_by')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->index('archived_by');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            // Drop foreign key first
            if (Schema::hasColumn('admins', 'archived_by')) {
                try {
                    $table->dropForeign(['archived_by']);
                } catch (\Exception $e) {
                    // Foreign key might not exist, ignore
                }
            }
            
            // Drop index
            if (Schema::hasColumn('admins', 'archived_by')) {
                try {
                    $table->dropIndex(['archived_by']);
                } catch (\Exception $e) {
                    // Index might not exist, ignore
                }
            }
            
            // Drop column
            if (Schema::hasColumn('admins', 'archived_by')) {
                $table->dropColumn('archived_by');
            }
        });
    }
};
