<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Character / declarations of interest rows per client (criminal, military, refusals, etc.).
     * No prior migration created this table on fresh PostgreSQL installs.
     */
    public function up(): void
    {
        if (Schema::hasTable('client_characters')) {
            return;
        }

        Schema::create('client_characters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->unsignedBigInteger('admin_id')->nullable()->index();
            $table->unsignedTinyInteger('type_of_character')->nullable()->index();
            $table->text('character_detail')->nullable();
            $table->date('character_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_characters');
    }
};
