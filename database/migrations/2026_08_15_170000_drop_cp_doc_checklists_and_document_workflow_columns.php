<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove unused workflow / client-portal checklist feature:
 * - cp_doc_checklists table (no current UI)
 * - documents.cp_list_id / cp_doc_status / cp_rejection_reason columns
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('documents')) {
            $drop = [];
            foreach (['cp_list_id', 'cp_doc_status', 'cp_rejection_reason'] as $column) {
                if (Schema::hasColumn('documents', $column)) {
                    $drop[] = $column;
                }
            }
            if ($drop !== []) {
                Schema::table('documents', function (Blueprint $table) use ($drop) {
                    $table->dropColumn($drop);
                });
            }
        }

        Schema::dropIfExists('cp_doc_checklists');
        Schema::dropIfExists('cp_doc_checklist');
    }

    public function down(): void
    {
        if (! Schema::hasTable('cp_doc_checklists')) {
            Schema::create('cp_doc_checklists', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->unsignedBigInteger('client_matter_id')->nullable();
                $table->string('wf_stage')->nullable();
                $table->unsignedBigInteger('wf_stage_id')->nullable();
                $table->string('cp_checklist_name')->nullable();
                $table->text('description')->nullable();
                $table->unsignedTinyInteger('allow_client')->nullable()->default(1);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('documents')) {
            Schema::table('documents', function (Blueprint $table) {
                if (! Schema::hasColumn('documents', 'cp_list_id')) {
                    $table->unsignedBigInteger('cp_list_id')->nullable()->after('client_matter_id');
                }
                if (! Schema::hasColumn('documents', 'cp_rejection_reason')) {
                    $table->text('cp_rejection_reason')->nullable();
                }
                if (! Schema::hasColumn('documents', 'cp_doc_status')) {
                    $table->unsignedTinyInteger('cp_doc_status')->nullable();
                }
            });
        }
    }
};
