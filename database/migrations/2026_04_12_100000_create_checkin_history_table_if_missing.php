<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Office visit / front-desk flows write to CheckinHistory; Laravel's default table name
 * would be checkin_histories. Legacy schema uses singular checkin_history (see docs/archive/PLAN_DEDICATED_STAFF_TABLE.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('checkin_history')) {
            return;
        }

        Schema::create('checkin_history', function (Blueprint $table) {
            $table->id();
            $table->string('subject')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('checkin_id')->index();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkin_history');
    }
};
