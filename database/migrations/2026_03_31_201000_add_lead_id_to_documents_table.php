<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('documents') || Schema::hasColumn('documents', 'lead_id')) {
            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            $table->unsignedBigInteger('lead_id')->nullable()->after('client_id');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('documents') || !Schema::hasColumn('documents', 'lead_id')) {
            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('lead_id');
        });
    }
};
