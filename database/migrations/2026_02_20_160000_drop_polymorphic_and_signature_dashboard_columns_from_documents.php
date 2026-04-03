<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Drops polymorphic, signature workflow, and verification columns from documents table.
     * All functionality uses client_id/lead_id, file_name, status, or has been stubbed out.
     */
    public function up(): void
    {
        // Drop index before dropping polymorphic columns (IF EXISTS for pgsql; try/catch for mysql)
        $driver = Schema::getConnection()->getDriverName();
        $indexName = 'documents_documentable_type_documentable_id_index';
        if ($driver === 'pgsql') {
            DB::statement("DROP INDEX IF EXISTS {$indexName}");
        } elseif ($driver === 'mysql') {
            try {
                DB::statement("ALTER TABLE documents DROP INDEX {$indexName}");
            } catch (\Throwable $e) {
                // Index may not exist
            }
        }

        $columnsToDrop = [
            'documentable_type',
            'documentable_id',
            'title',
            'document_type',
            'due_at',
            'priority',
            'archived_at',
            'checklist_verified_by',
            'checklist_verified_at',
        ];

        foreach ($columnsToDrop as $column) {
            if (Schema::hasColumn('documents', $column)) {
                Schema::table('documents', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $columns = [];
            if (!Schema::hasColumn('documents', 'documentable_type')) {
                $table->string('documentable_type')->nullable();
            }
            if (!Schema::hasColumn('documents', 'documentable_id')) {
                $table->unsignedInteger('documentable_id')->nullable();
            }
            if (!Schema::hasColumn('documents', 'title')) {
                $table->string('title')->nullable();
            }
            if (!Schema::hasColumn('documents', 'document_type')) {
                $table->string('document_type', 50)->default('general');
            }
            if (!Schema::hasColumn('documents', 'due_at')) {
                $table->timestamp('due_at')->nullable();
            }
            if (!Schema::hasColumn('documents', 'priority')) {
                $table->string('priority', 10)->default('normal');
            }
            if (!Schema::hasColumn('documents', 'archived_at')) {
                $table->timestamp('archived_at')->nullable();
            }
            if (!Schema::hasColumn('documents', 'checklist_verified_by')) {
                $table->unsignedInteger('checklist_verified_by')->nullable();
            }
            if (!Schema::hasColumn('documents', 'checklist_verified_at')) {
                $table->timestamp('checklist_verified_at')->nullable();
            }
        });

        // Recreate the polymorphic index
        if (Schema::hasColumn('documents', 'documentable_type') && Schema::hasColumn('documents', 'documentable_id')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->index(['documentable_type', 'documentable_id']);
            });
        }
    }
};
