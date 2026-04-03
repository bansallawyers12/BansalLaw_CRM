<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Five additional workforce counts (boxes 5–9); boxes 1–4 use existing columns on companies.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->integer('workforce_foreign_494')->nullable()->after('workforce_total');
            $table->integer('workforce_foreign_other_temp_activity')->nullable()->after('workforce_foreign_494');
            $table->integer('workforce_foreign_overseas_students')->nullable()->after('workforce_foreign_other_temp_activity');
            $table->integer('workforce_foreign_working_holiday')->nullable()->after('workforce_foreign_overseas_students');
            $table->integer('workforce_foreign_other')->nullable()->after('workforce_foreign_working_holiday');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'workforce_foreign_494',
                'workforce_foreign_other_temp_activity',
                'workforce_foreign_overseas_students',
                'workforce_foreign_working_holiday',
                'workforce_foreign_other',
            ]);
        });
    }
};
