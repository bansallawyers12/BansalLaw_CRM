<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Drops unused tables that are no longer referenced in the codebase.
     * Tables are dropped in a safe order to handle any foreign key constraints.
     */
    public function up(): void
    {
        // Disable foreign key checks temporarily to allow dropping tables
        // even if they have foreign key constraints
        Schema::disableForeignKeyConstraints();
        
        try {
            // Drop unused blog-related tables
            Schema::dropIfExists('blog_categories');
            Schema::dropIfExists('blogs');
            
            // Drop unused client-related tables
            Schema::dropIfExists('client_married_details');
            Schema::dropIfExists('client_ratings');
            
            // Drop unused CMS tables
            Schema::dropIfExists('cms_pages');
            Schema::dropIfExists('theme_options');
            
            // Drop unused contact tables
            Schema::dropIfExists('contacts');
            Schema::dropIfExists('verified_numbers');
            
            // Drop unused currency table
            Schema::dropIfExists('currencies');
            
            // Drop unused enquiry tables
            Schema::dropIfExists('enquiries');
            Schema::dropIfExists('enquiry_sources');
            
            // Drop unused lead tables
            Schema::dropIfExists('lead_services');
            Schema::dropIfExists('leads');
            
            // Drop unused office table
            Schema::dropIfExists('our_offices');
            
            // Drop unused content table
            Schema::dropIfExists('why_chooseuses');
            
            // Drop legacy Laravel password reset table (replaced by password_reset_tokens)
            Schema::dropIfExists('password_resets');
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
