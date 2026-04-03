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
        Schema::table('client_eoi_references', function (Blueprint $table) {
            // JSON arrays for multi-subclass and multi-state support
            $table->json('eoi_subclasses')->nullable()->after('EOI_subclass')
                ->comment('Array of subclass codes: ["189","190","491"]');
            $table->json('eoi_states')->nullable()->after('EOI_state')
                ->comment('Array of state codes: ["VIC","NSW","SA"]');
            
            // Additional date tracking
            $table->date('eoi_invitation_date')->nullable()->after('EOI_submission_date')
                ->comment('Date invitation was received');
            $table->date('eoi_nomination_date')->nullable()->after('eoi_invitation_date')
                ->comment('Date nomination was approved');
            
            // Status tracking
            $table->enum('eoi_status', [
                'draft',
                'submitted',
                'invited',
                'nominated',
                'rejected',
                'withdrawn'
            ])->default('draft')->after('eoi_nomination_date')
                ->comment('Current status of EOI');
            
            // Audit fields for reporting
            $table->unsignedBigInteger('created_by')->nullable()->after('eoi_status')
                ->comment('Admin user who created this record');
            $table->unsignedBigInteger('updated_by')->nullable()->after('created_by')
                ->comment('Admin user who last updated this record');
            
            // Add indexes for reporting queries
            $table->index(['client_id', 'eoi_status'], 'idx_client_status');
            $table->index('EOI_submission_date', 'idx_submission_date');
            $table->index('eoi_status', 'idx_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_eoi_references', function (Blueprint $table) {
            // Drop indexes
            $table->dropIndex('idx_client_status');
            $table->dropIndex('idx_submission_date');
            $table->dropIndex('idx_status');
            
            // Drop columns in reverse order
            $table->dropColumn([
                'updated_by',
                'created_by',
                'eoi_status',
                'eoi_nomination_date',
                'eoi_invitation_date',
                'eoi_states',
                'eoi_subclasses'
            ]);
        });
    }
};
