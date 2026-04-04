<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Signature / document workflow expects documents.status (draft, sent, signed, archived, void, etc.).
     * Legacy stub and some installs never had this column; 2025_09_01 stub now creates it for fresh installs.
     */
    public function up(): void
    {
        if (! Schema::hasTable('documents') || Schema::hasColumn('documents', 'status')) {
            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            $table->string('status', 32)->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('documents') || ! Schema::hasColumn('documents', 'status')) {
            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
