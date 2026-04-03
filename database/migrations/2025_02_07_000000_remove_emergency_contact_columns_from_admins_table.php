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
        Schema::table('admins', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('admins', 'emergency_country_code')) {
                $columns[] = 'emergency_country_code';
            }
            if (Schema::hasColumn('admins', 'emergency_contact_no')) {
                $columns[] = 'emergency_contact_no';
            }
            if (Schema::hasColumn('admins', 'emergency_contact_type')) {
                $columns[] = 'emergency_contact_type';
            }
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->string('emergency_country_code')->nullable();
            $table->string('emergency_contact_no')->nullable();
            $table->string('emergency_contact_type')->nullable();
        });
    }
};
