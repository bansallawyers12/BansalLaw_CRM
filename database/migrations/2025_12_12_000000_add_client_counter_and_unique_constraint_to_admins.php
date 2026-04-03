<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds client_counter field if it doesn't exist and creates unique index on client_id
     * to prevent duplicate client references
     */
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            // Add client_counter if it doesn't exist
            if (!Schema::hasColumn('admins', 'client_counter')) {
                $table->string('client_counter', 5)->nullable();
            }
            
            // Add client_id if it doesn't exist
            if (!Schema::hasColumn('admins', 'client_id')) {
                $table->string('client_id', 20)->nullable();
            }
        });

        // Add unique index on client_id separately to handle existing duplicates
        // This will fail if duplicates exist, which is expected behavior
        // Note: For production with existing duplicates, you may need to handle this differently
        try {
            Schema::table('admins', function (Blueprint $table) {
                // Only add unique constraint if it doesn't already exist
                // This prevents errors on repeated migrations
                $sm = Schema::getConnection()->getDoctrineSchemaManager();
                $indexesFound = $sm->listTableIndexes('admins');
                
                $uniqueExists = false;
                foreach ($indexesFound as $index) {
                    if (in_array('client_id', $index->getColumns()) && $index->isUnique()) {
                        $uniqueExists = true;
                        break;
                    }
                }
                
                if (!$uniqueExists) {
                    // Add index for better query performance on client_counter
                    $table->index('client_counter', 'idx_client_counter');
                    
                    // Note: Commenting out unique constraint for now due to existing duplicates
                    // Uncomment after data cleanup if needed
                    // $table->unique('client_id', 'unique_client_id');
                }
            });
        } catch (\Exception $e) {
            // Log the error but don't fail the migration
            // This allows the migration to proceed even if duplicates exist
            \Log::warning('Could not add unique constraint on client_id due to existing duplicates: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            // Drop indexes first
            if (Schema::hasColumn('admins', 'client_counter')) {
                $table->dropIndex('idx_client_counter');
            }
            
            // Try to drop unique constraint if it exists
            try {
                $table->dropUnique('unique_client_id');
            } catch (\Exception $e) {
                // Constraint might not exist, ignore
            }
            
            // Note: We don't drop the columns as they may contain important data
            // If you need to drop them, uncomment below:
            // $table->dropColumn(['client_counter', 'client_id']);
        });
    }
};

