<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Drops unused/redundant columns from documents table.
     */
    public function up(): void
    {
        $columnsToDrop = [
            'labels',
            'certificate_path',
            'signed_hash',
            'hash_generated_at',
            'client_portal_verified_by',
            'client_portal_verified_at',
            'origin',
            'primary_signer_email',
            'signer_count',
            'last_activity_at',
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
            if (!Schema::hasColumn('documents', 'labels')) {
                $table->text('labels')->nullable();
            }
            if (!Schema::hasColumn('documents', 'certificate_path')) {
                $table->string('certificate_path')->nullable();
            }
            if (!Schema::hasColumn('documents', 'signed_hash')) {
                $table->string('signed_hash', 64)->nullable();
            }
            if (!Schema::hasColumn('documents', 'hash_generated_at')) {
                $table->timestamp('hash_generated_at')->nullable();
            }
            if (!Schema::hasColumn('documents', 'client_portal_verified_by')) {
                $table->unsignedInteger('client_portal_verified_by')->nullable();
            }
            if (!Schema::hasColumn('documents', 'client_portal_verified_at')) {
                $table->timestamp('client_portal_verified_at')->nullable();
            }
            if (!Schema::hasColumn('documents', 'origin')) {
                $table->string('origin', 20)->nullable();
            }
            if (!Schema::hasColumn('documents', 'primary_signer_email')) {
                $table->string('primary_signer_email')->nullable();
            }
            if (!Schema::hasColumn('documents', 'signer_count')) {
                $table->unsignedTinyInteger('signer_count')->default(1);
            }
            if (!Schema::hasColumn('documents', 'last_activity_at')) {
                $table->timestamp('last_activity_at')->nullable();
            }
        });
    }
};
