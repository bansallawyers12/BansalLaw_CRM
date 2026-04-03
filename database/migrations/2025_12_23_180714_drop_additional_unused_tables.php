<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Drops additional unused tables that are no longer referenced in the codebase.
     * Includes legacy/deprecated tables from incomplete features.
     */
    public function up(): void
    {
        // Disable foreign key checks temporarily to allow dropping tables
        // even if they have foreign key constraints
        Schema::disableForeignKeyConstraints();
        
        try {
            // Drop unused mapping table
            Schema::dropIfExists('admin_to_client_mapping');
            
            // Drop unused API tokens table
            Schema::dropIfExists('api_tokens');
            
            // Drop unused application-related tables
            Schema::dropIfExists('application_notes');
            
            // Drop unused attachment tables (replaced by mail_report_attachments)
            Schema::dropIfExists('attachments');
            Schema::dropIfExists('email_attachments');
            Schema::dropIfExists('email_uploads');
            
            // Drop unused people/responsibility table
            Schema::dropIfExists('responsible_people');
            
            // Drop unused task logs table
            Schema::dropIfExists('task_logs');
            
            // Drop legacy/deprecated content tables (incomplete features)
            Schema::dropIfExists('sliders');
            Schema::dropIfExists('testimonials');
            Schema::dropIfExists('our_services');
            Schema::dropIfExists('home_contents');
        } finally {
            // Re-enable foreign key checks
            Schema::enableForeignKeyConstraints();
        }
    }

    /**
     * Reverse the migrations.
     * 
     * Note: This migration drops tables, so rollback is not possible without
     * recreating the table structures. If you need to rollback, you would need
     * to restore from a database backup.
     */
    public function down(): void
    {
        // Cannot recreate dropped tables without their original structure
        // If rollback is needed, restore from database backup
    }
};
