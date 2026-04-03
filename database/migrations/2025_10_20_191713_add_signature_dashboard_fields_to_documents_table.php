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
        Schema::table('documents', function (Blueprint $table) {
            // Ownership & tracking (skip if already exists)
            if (!Schema::hasColumn('documents', 'created_by')) {
                $table->unsignedInteger('created_by')->nullable()->after('id');
            }
            if (!Schema::hasColumn('documents', 'origin')) {
                $table->string('origin', 20)->default('ad_hoc')->after('created_by'); // ad_hoc|client|lead
            }
            
            // Polymorphic association (nullable for ad-hoc)
            if (!Schema::hasColumn('documents', 'documentable_type')) {
                $table->string('documentable_type')->nullable()->after('origin');
            }
            if (!Schema::hasColumn('documents', 'documentable_id')) {
                $table->unsignedInteger('documentable_id')->nullable()->after('documentable_type');
            }
            if (!Schema::hasIndex('documents', ['documentable_type', 'documentable_id'])) {
                $table->index(['documentable_type', 'documentable_id']);
            }
            
            // Metadata for discoverability
            if (!Schema::hasColumn('documents', 'title')) {
                $table->string('title')->nullable()->after('documentable_id');
            }
            if (!Schema::hasColumn('documents', 'document_type')) {
                $table->string('document_type', 50)->default('general')->after('title'); // agreement|nda|general|etc
            }
            if (!Schema::hasColumn('documents', 'labels')) {
                $table->json('labels')->nullable()->after('document_type');
            }
            if (!Schema::hasColumn('documents', 'due_at')) {
                $table->timestamp('due_at')->nullable()->after('labels');
            }
            if (!Schema::hasColumn('documents', 'priority')) {
                $table->string('priority', 10)->default('normal')->after('due_at'); // low|normal|high
            }
            
            // Activity tracking
            if (!Schema::hasColumn('documents', 'primary_signer_email')) {
                $table->string('primary_signer_email')->nullable()->after('priority');
            }
            if (!Schema::hasColumn('documents', 'signer_count')) {
                $table->unsignedTinyInteger('signer_count')->default(1)->after('primary_signer_email');
            }
            if (!Schema::hasColumn('documents', 'last_activity_at')) {
                $table->timestamp('last_activity_at')->nullable()->after('signer_count');
            }
            
            // Lifecycle
            if (!Schema::hasColumn('documents', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('last_activity_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropIndex(['documentable_type', 'documentable_id']);
            $table->dropColumn([
                'created_by',
                'origin',
                'documentable_type',
                'documentable_id',
                'title',
                'document_type',
                'labels',
                'due_at',
                'priority',
                'primary_signer_email',
                'signer_count',
                'last_activity_at',
                'archived_at'
            ]);
        });
    }
};
