<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Removes is_archived column from checkin_logs table.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('checkin_logs', function (Blueprint $table) {
            $table->dropColumn('is_archived');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('checkin_logs', function (Blueprint $table) {
            $table->boolean('is_archived')->default(0)->after('wait_type');
        });
    }
};
