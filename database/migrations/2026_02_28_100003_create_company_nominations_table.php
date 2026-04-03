<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates company_nominations for visa nomination positions.
     * Nominated person: either nominated_client_id (FK to admins) or nominated_person_name (when not in system).
     */
    public function up(): void
    {
        Schema::create('company_nominations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();

            // Position details
            $table->string('position_title', 255)->nullable();
            $table->string('anzsco_code', 10)->nullable();
            $table->text('position_description')->nullable();
            $table->decimal('salary', 12, 2)->nullable();
            $table->string('duration', 100)->nullable();

            // Nominated person: either in system (client/lead) or name only
            $table->unsignedBigInteger('nominated_client_id')->nullable()->comment('FK to admins.id when person is client/lead');
            $table->string('nominated_person_name', 255)->nullable()->comment('Name when person not in system');

            // TRN, status, dates
            $table->string('trn', 50)->nullable();
            $table->string('status', 50)->nullable();
            $table->date('nomination_date')->nullable();
            $table->date('expiry_date')->nullable();

            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('nominated_client_id')->references('id')->on('admins')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_nominations');
    }
};
