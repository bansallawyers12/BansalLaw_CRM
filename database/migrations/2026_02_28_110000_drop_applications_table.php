<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Migrates application_documents and application_document_lists to use client_matter_id,
     * then drops the applications table.
     */
    public function up(): void
    {
        if (!Schema::hasTable('applications')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        // Step 1: Migrate application_document_lists to use client_matter_id
        if (Schema::hasTable('application_document_lists') && Schema::hasColumn('application_document_lists', 'application_id')) {
            if (!Schema::hasColumn('application_document_lists', 'client_matter_id')) {
                Schema::table('application_document_lists', function (Blueprint $table) {
                    $table->unsignedBigInteger('client_matter_id')->nullable()->after('application_id');
                });
            }
            // Copy client_matter_id from applications
            if ($driver === 'pgsql') {
                DB::statement('
                    UPDATE application_document_lists adl
                    SET client_matter_id = a.client_matter_id
                    FROM applications a
                    WHERE adl.application_id = a.id AND a.client_matter_id IS NOT NULL
                ');
            } else {
                DB::statement('
                    UPDATE application_document_lists adl
                    INNER JOIN applications a ON adl.application_id = a.id AND a.client_matter_id IS NOT NULL
                    SET adl.client_matter_id = a.client_matter_id
                ');
            }
            Schema::table('application_document_lists', function (Blueprint $table) {
                $table->dropColumn('application_id');
            });
        }

        // Step 2: Migrate application_documents to use client_matter_id
        if (Schema::hasTable('application_documents') && Schema::hasColumn('application_documents', 'application_id')) {
            if (!Schema::hasColumn('application_documents', 'client_matter_id')) {
                Schema::table('application_documents', function (Blueprint $table) {
                    $table->unsignedBigInteger('client_matter_id')->nullable()->after('application_id');
                });
            }
            if ($driver === 'pgsql') {
                DB::statement('
                    UPDATE application_documents ad
                    SET client_matter_id = a.client_matter_id
                    FROM applications a
                    WHERE ad.application_id = a.id AND a.client_matter_id IS NOT NULL
                ');
            } else {
                DB::statement('
                    UPDATE application_documents ad
                    INNER JOIN applications a ON ad.application_id = a.id AND a.client_matter_id IS NOT NULL
                    SET ad.client_matter_id = a.client_matter_id
                ');
            }
            Schema::table('application_documents', function (Blueprint $table) {
                $table->dropColumn('application_id');
            });
        }

        // Step 3: Drop applications table
        Schema::dropIfExists('applications');
    }

    /**
     * Reverse the migrations.
     * Cannot fully restore - applications table structure is unknown.
     */
    public function down(): void
    {
        // Cannot restore - applications table was legacy with no create migration
    }
};
