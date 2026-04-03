<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds student_checklist_status for Student Visa Sheet Checklist tab (active, hold, convert_to_client, discontinue).
     */
    public function up(): void
    {
        if (!Schema::hasColumn('client_matters', 'student_checklist_status')) {
            Schema::table('client_matters', function (Blueprint $table) {
                $table->string('student_checklist_status', 32)->nullable()
                    ->after('visitor_checklist_status')
                    ->comment('Student sheet checklist status: active, hold, convert_to_client, discontinue');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('client_matters', 'student_checklist_status')) {
            Schema::table('client_matters', function (Blueprint $table) {
                $table->dropColumn('student_checklist_status');
            });
        }
    }
};
