<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add director_client_id to link directors to existing clients/leads.
     * Make director_name nullable (when linked, name comes from the person).
     */
    public function up(): void
    {
        Schema::table('company_directors', function (Blueprint $table) {
            $table->unsignedBigInteger('director_client_id')->nullable()->after('company_id')
                ->comment('FK to admins.id when director is existing client/lead');
            $table->foreign('director_client_id')->references('id')->on('admins')->onDelete('set null');
        });

        Schema::table('company_directors', function (Blueprint $table) {
            $table->string('director_name', 255)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_directors', function (Blueprint $table) {
            $table->dropForeign(['director_client_id']);
            $table->dropColumn('director_client_id');
            $table->string('director_name', 255)->nullable(false)->change();
        });
    }
};
