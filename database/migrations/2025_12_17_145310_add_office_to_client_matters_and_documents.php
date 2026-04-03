<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds office_id to client_matters for manual office assignment
     * Adds office_id to documents for ad-hoc files without matters
     */
    public function up(): void
    {
        // Add office_id to client_matters table
        Schema::table('client_matters', function (Blueprint $table) {
            $table->unsignedInteger('office_id')
                  ->nullable()
                  ->after('client_id')
                  ->comment('Manually assigned handling office');
            
            // Add indexes for performance
            $table->index('office_id', 'idx_matters_office');
            $table->index(['office_id', 'matter_status'], 'idx_matters_office_status');
        });
        
        // Add office_id to documents table (for ad-hoc documents without matter)
        if (!Schema::hasColumn('documents', 'office_id')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->unsignedInteger('office_id')
                      ->nullable()
                      ->after('client_matter_id')
                      ->comment('Office for ad-hoc documents (without matter)');
                
                $table->index('office_id', 'idx_documents_office');
            });
        }
        
        echo "\n✅ Migration completed successfully!\n";
        echo "   - office_id column added to client_matters (nullable)\n";
        echo "   - office_id column added to documents (nullable)\n";
        echo "   - Indexes created for performance\n";
        echo "\n📝 Next steps:\n";
        echo "   - New matters will require office selection\n";
        echo "   - Existing matters can be manually assigned through the UI\n";
        echo "   - Total matters needing office: " . DB::table('client_matters')->whereNull('office_id')->count() . "\n\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_matters', function (Blueprint $table) {
            $table->dropIndex('idx_matters_office');
            $table->dropIndex('idx_matters_office_status');
            $table->dropColumn('office_id');
        });
        
        if (Schema::hasColumn('documents', 'office_id')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->dropIndex('idx_documents_office');
                $table->dropColumn('office_id');
            });
        }
        
        echo "\n✅ Rollback completed - office_id columns removed\n\n";
    }
};
