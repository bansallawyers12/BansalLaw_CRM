<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stub / minimal PostgreSQL `documents` (e.g. legacy stub) may only have id, status, timestamps.
 * CRM personal/matter document tabs expect client_id, classification columns, and file fields.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('documents')) {
            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            if (! Schema::hasColumn('documents', 'client_id')) {
                $table->unsignedBigInteger('client_id')->nullable()->index();
            }
            if (! Schema::hasColumn('documents', 'lead_id')) {
                $table->unsignedBigInteger('lead_id')->nullable()->index();
            }
            if (! Schema::hasColumn('documents', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->index();
            }
            if (! Schema::hasColumn('documents', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->index();
            }
            if (! Schema::hasColumn('documents', 'office_id')) {
                $table->unsignedBigInteger('office_id')->nullable()->index();
            }
            if (! Schema::hasColumn('documents', 'client_matter_id')) {
                $table->string('client_matter_id', 64)->nullable();
            }
            if (! Schema::hasColumn('documents', 'type')) {
                $table->string('type', 64)->nullable()->index();
            }
            if (! Schema::hasColumn('documents', 'doc_type')) {
                $table->string('doc_type', 64)->nullable()->index();
            }
            if (! Schema::hasColumn('documents', 'folder_name')) {
                $table->string('folder_name', 191)->nullable();
            }
            if (! Schema::hasColumn('documents', 'mail_type')) {
                $table->string('mail_type', 64)->nullable();
            }
            if (! Schema::hasColumn('documents', 'checklist')) {
                $table->string('checklist', 500)->nullable();
            }
            if (! Schema::hasColumn('documents', 'not_used_doc')) {
                $table->tinyInteger('not_used_doc')->nullable();
            }
            if (! Schema::hasColumn('documents', 'file_name')) {
                $table->string('file_name', 500)->nullable();
            }
            if (! Schema::hasColumn('documents', 'filetype')) {
                $table->string('filetype', 64)->nullable();
            }
            if (! Schema::hasColumn('documents', 'myfile')) {
                $table->text('myfile')->nullable();
            }
            if (! Schema::hasColumn('documents', 'myfile_key')) {
                $table->text('myfile_key')->nullable();
            }
            if (! Schema::hasColumn('documents', 'file_size')) {
                $table->string('file_size', 64)->nullable();
            }
            if (! Schema::hasColumn('documents', 'cp_list_id')) {
                $table->unsignedBigInteger('cp_list_id')->nullable();
            }
            if (! Schema::hasColumn('documents', 'cp_rejection_reason')) {
                $table->text('cp_rejection_reason')->nullable();
            }
            if (! Schema::hasColumn('documents', 'cp_doc_status')) {
                $table->string('cp_doc_status', 64)->nullable();
            }
            if (! Schema::hasColumn('documents', 'signature_doc_link')) {
                $table->text('signature_doc_link')->nullable();
            }
            if (! Schema::hasColumn('documents', 'signed_doc_link')) {
                $table->text('signed_doc_link')->nullable();
            }
            if (! Schema::hasColumn('documents', 'is_client_portal_verify')) {
                $table->integer('is_client_portal_verify')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('documents')) {
            return;
        }

        $cols = array_values(array_filter([
            'client_id', 'lead_id', 'user_id', 'created_by', 'office_id',
            'client_matter_id', 'type', 'doc_type', 'folder_name', 'mail_type', 'checklist',
            'not_used_doc', 'file_name', 'filetype', 'myfile', 'myfile_key', 'file_size',
            'cp_list_id', 'cp_rejection_reason', 'cp_doc_status',
            'signature_doc_link', 'signed_doc_link', 'is_client_portal_verify',
        ], fn ($c) => Schema::hasColumn('documents', $c)));

        if ($cols === []) {
            return;
        }

        Schema::table('documents', function (Blueprint $table) use ($cols) {
            $table->dropColumn($cols);
        });
    }
};
