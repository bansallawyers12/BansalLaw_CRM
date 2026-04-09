<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fresh PostgreSQL installs had no migration creating this table; only a MySQL→PG data migration existed.
     */
    public function up(): void
    {
        if (Schema::hasTable('client_travel_informations')) {
            return;
        }

        Schema::create('client_travel_informations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->unsignedBigInteger('admin_id')->nullable()->index();
            $table->string('travel_country_visited', 255)->nullable();
            $table->date('travel_arrival_date')->nullable();
            $table->date('travel_departure_date')->nullable();
            $table->string('travel_purpose', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_travel_informations');
    }
};
