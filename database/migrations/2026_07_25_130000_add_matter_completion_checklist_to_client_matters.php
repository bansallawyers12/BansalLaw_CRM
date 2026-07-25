<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_matters')) {
            return;
        }

        Schema::table('client_matters', function (Blueprint $table) {
            if (! Schema::hasColumn('client_matters', 'matter_completion_checklist')) {
                $table->json('matter_completion_checklist')->nullable()->after('discontinue_notes');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('client_matters')) {
            return;
        }

        Schema::table('client_matters', function (Blueprint $table) {
            if (Schema::hasColumn('client_matters', 'matter_completion_checklist')) {
                $table->dropColumn('matter_completion_checklist');
            }
        });
    }
};
