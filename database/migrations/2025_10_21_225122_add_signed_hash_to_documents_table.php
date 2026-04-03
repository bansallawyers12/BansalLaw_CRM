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
        // Check if columns already exist before adding them
        if (Schema::hasTable('documents')) {
            Schema::table('documents', function (Blueprint $table) {
                if (!Schema::hasColumn('documents', 'signed_hash')) {
                    $table->string('signed_hash', 64)->nullable()->after('signed_doc_link')
                        ->comment('SHA-256 hash of signed PDF for tamper detection');
                }
                
                if (!Schema::hasColumn('documents', 'hash_generated_at')) {
                    $table->timestamp('hash_generated_at')->nullable()->after('signed_hash')
                        ->comment('When the document hash was generated');
                }
                
                if (!Schema::hasColumn('documents', 'certificate_path')) {
                    $table->string('certificate_path')->nullable()->after('hash_generated_at')
                        ->comment('S3 path to completion certificate');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('documents')) {
            Schema::table('documents', function (Blueprint $table) {
                if (Schema::hasColumn('documents', 'signed_hash')) {
                    $table->dropColumn('signed_hash');
                }
                if (Schema::hasColumn('documents', 'hash_generated_at')) {
                    $table->dropColumn('hash_generated_at');
                }
                if (Schema::hasColumn('documents', 'certificate_path')) {
                    $table->dropColumn('certificate_path');
                }
            });
        }
    }
};
