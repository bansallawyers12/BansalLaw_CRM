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
        Schema::table('mail_reports', function (Blueprint $table) {
            // Drop category and priority columns
            $table->dropColumn(['category', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mail_reports', function (Blueprint $table) {
            // Restore category and priority columns if migration is rolled back
            $table->string('category')->nullable()->after('python_rendering')->index();
            $table->enum('priority', ['low', 'medium', 'high'])->default('low')->after('category')->index();
        });
    }
};
