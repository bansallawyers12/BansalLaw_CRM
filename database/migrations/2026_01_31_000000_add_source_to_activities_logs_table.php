<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds nullable source column to distinguish Client Portal vs CRM activity.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('activities_logs', function (Blueprint $table) {
            $table->string('source', 50)->nullable()->after('activity_type')
                ->comment('Origin: client_portal, crm, etc. NULL = legacy/unset.');
            $table->index(['client_id', 'source']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('activities_logs', function (Blueprint $table) {
            $table->dropIndex(['client_id', 'source']);
            $table->dropColumn('source');
        });
    }
};
