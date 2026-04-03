<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('activities_logs', function (Blueprint $table) {
            // Add SMS log reference
            $table->unsignedBigInteger('sms_log_id')->nullable()->after('description')->comment('Reference to SMS log if activity is SMS-related');
            
            // Add activity type to differentiate activity types
            $table->string('activity_type', 20)->default('note')->after('sms_log_id')->comment('Type: note, document, sms, email, etc.');
            
            // Add indexes for better performance
            $table->index('sms_log_id');
            $table->index('activity_type');
            $table->index(['client_id', 'activity_type']);
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
            $table->dropIndex(['activities_logs_sms_log_id_index']);
            $table->dropIndex(['activities_logs_activity_type_index']);
            $table->dropIndex(['activities_logs_client_id_activity_type_index']);
            
            $table->dropColumn('sms_log_id');
            $table->dropColumn('activity_type');
        });
    }
};
