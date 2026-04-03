<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds workflow checklist columns to documents, migrates data from application_documents, drops application_documents.
     */
    public function up(): void
    {
        // Step 1: Add client portal columns to documents if they don't exist
        // cp_list_id: FK to cp_doc_checklist.id
        // cp_rejection_reason: rejection reason when cp_doc_status = 2
        // cp_doc_status: 0=InProgress, 1=Approved, 2=Rejected
        if (!Schema::hasColumn('documents', 'cp_list_id')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->unsignedBigInteger('cp_list_id')->nullable()->after('client_matter_id')->comment('FK to cp_doc_checklist.id');
            });
        }
        if (!Schema::hasColumn('documents', 'cp_rejection_reason')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->text('cp_rejection_reason')->nullable()->after('checklist');
            });
        }
        if (!Schema::hasColumn('documents', 'cp_doc_status')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->unsignedTinyInteger('cp_doc_status')->nullable()->after('cp_rejection_reason')->comment('0=InProgress, 1=Approved, 2=Rejected');
            });
        }

        // Step 2: Migrate data from application_documents to documents
        if (Schema::hasTable('application_documents')) {
            $rows = DB::table('application_documents')->get();
            foreach ($rows as $ad) {
                $clientMatterId = $ad->client_matter_id ?? $ad->application_id ?? null;
                $clientId = null;
                if ($clientMatterId) {
                    $matter = DB::table('client_matters')->where('id', $clientMatterId)->first();
                    $clientId = $matter->client_id ?? null;
                }
                $listTable = Schema::hasTable('cp_doc_checklist') ? 'cp_doc_checklist' : 'application_document_lists';
                $list = $ad->list_id ? DB::table($listTable)->where('id', $ad->list_id)->first() : null;
                $checklistName = $list ? ($list->cp_checklist_name ?? $list->document_type ?? null) : $ad->file_name;

                DB::table('documents')->insert([
                    'cp_list_id' => $ad->list_id,
                    'user_id' => $ad->user_id,
                    'client_id' => $clientId,
                    'client_matter_id' => $clientMatterId,
                    'file_name' => $ad->file_name,
                    'filetype' => $ad->file_type ?? pathinfo($ad->file_name ?? '', PATHINFO_EXTENSION),
                    'myfile' => $ad->myfile,
                    'myfile_key' => $ad->myfile_key,
                    'file_size' => $ad->file_size,
                    'type' => 'workflow_checklist',
                    'doc_type' => $ad->type ?? 'workflow_checklist',
                    'checklist' => $checklistName,
                    'cp_doc_status' => $ad->status,
                    'cp_rejection_reason' => $ad->doc_rejection_reason ?? null,
                    'created_at' => $ad->created_at,
                    'updated_at' => $ad->updated_at,
                ]);
            }
        }

        // Step 3: Drop application_documents table
        Schema::dropIfExists('application_documents');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate application_documents table (structure matches post-2026_02_28_110000)
        Schema::create('application_documents', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->nullable();
            $table->unsignedBigInteger('client_matter_id')->nullable();
            $table->string('typename')->nullable();
            $table->string('type')->nullable();
            $table->integer('list_id')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_type')->nullable();
            $table->text('myfile')->nullable();
            $table->text('myfile_key')->nullable();
            $table->integer('file_size')->nullable();
            $table->integer('status')->default(0);
            $table->text('doc_rejection_reason')->nullable();
            $table->timestamps();
        });

        if (Schema::hasColumn('documents', 'cp_list_id')) {
            $docs = DB::table('documents')->whereNotNull('cp_list_id')->where('type', 'workflow_checklist')->get();
            foreach ($docs as $doc) {
                DB::table('application_documents')->insert([
                    'user_id' => $doc->user_id,
                    'client_matter_id' => $doc->client_matter_id,
                    'typename' => $doc->doc_type,
                    'type' => $doc->doc_type,
                    'list_id' => $doc->cp_list_id,
                    'file_name' => $doc->file_name,
                    'file_type' => $doc->filetype,
                    'myfile' => $doc->myfile,
                    'myfile_key' => $doc->myfile_key,
                    'file_size' => $doc->file_size,
                    'status' => $doc->cp_doc_status ?? 0,
                    'doc_rejection_reason' => $doc->cp_rejection_reason,
                    'created_at' => $doc->created_at,
                    'updated_at' => $doc->updated_at,
                ]);
            }
        }

        $colsToDrop = array_filter(
            ['cp_list_id', 'cp_rejection_reason', 'cp_doc_status'],
            fn ($c) => Schema::hasColumn('documents', $c)
        );
        if (!empty($colsToDrop)) {
            Schema::table('documents', fn (Blueprint $t) => $t->dropColumn($colsToDrop));
        }
    }
};
