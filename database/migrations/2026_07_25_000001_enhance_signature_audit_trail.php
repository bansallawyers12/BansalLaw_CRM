<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('signature_activities')) {
            Schema::table('signature_activities', function (Blueprint $table) {
                if (!Schema::hasColumn('signature_activities', 'signer_id')) {
                    $table->foreignId('signer_id')->nullable()->after('document_id')
                        ->constrained('signers')->nullOnDelete();
                }
                if (!Schema::hasColumn('signature_activities', 'actor_type')) {
                    $table->string('actor_type', 20)->nullable()->after('created_by')
                        ->comment('staff|signer|system');
                }
                if (!Schema::hasColumn('signature_activities', 'ip_address')) {
                    $table->string('ip_address', 45)->nullable()->after('metadata');
                }
                if (!Schema::hasColumn('signature_activities', 'user_agent')) {
                    $table->string('user_agent', 500)->nullable()->after('ip_address');
                }
            });
        }

        if (Schema::hasTable('documents')) {
            Schema::table('documents', function (Blueprint $table) {
                if (!Schema::hasColumn('documents', 'signed_hash')) {
                    $table->string('signed_hash', 64)->nullable()->after('signed_doc_link');
                }
                if (!Schema::hasColumn('documents', 'original_hash')) {
                    $table->string('original_hash', 64)->nullable()->after('signed_hash');
                }
                if (!Schema::hasColumn('documents', 'hash_generated_at')) {
                    $table->timestamp('hash_generated_at')->nullable()->after('original_hash');
                }
                if (!Schema::hasColumn('documents', 'certificate_path')) {
                    $table->string('certificate_path')->nullable()->after('hash_generated_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('signature_activities')) {
            Schema::table('signature_activities', function (Blueprint $table) {
                foreach (['signer_id', 'actor_type', 'ip_address', 'user_agent'] as $column) {
                    if (Schema::hasColumn('signature_activities', $column)) {
                        if ($column === 'signer_id') {
                            $table->dropConstrainedForeignId('signer_id');
                        } else {
                            $table->dropColumn($column);
                        }
                    }
                }
            });
        }

        if (Schema::hasTable('documents')) {
            Schema::table('documents', function (Blueprint $table) {
                foreach (['signed_hash', 'original_hash', 'hash_generated_at', 'certificate_path'] as $column) {
                    if (Schema::hasColumn('documents', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
