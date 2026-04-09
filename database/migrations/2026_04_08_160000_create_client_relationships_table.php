<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Family / partner links between CRM clients (admins.id).
     * Missing on fresh PostgreSQL installs; only referenced from application code.
     */
    public function up(): void
    {
        if (Schema::hasTable('client_relationships')) {
            return;
        }

        Schema::create('client_relationships', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id')->nullable()->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->unsignedBigInteger('related_client_id')->nullable()->index();
            $table->text('details')->nullable();
            $table->string('relationship_type', 191)->nullable()->index();
            $table->string('company_type', 191)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('first_name', 255)->nullable();
            $table->string('last_name', 255)->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('gender', 64)->nullable();
            $table->date('dob')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_relationships');
    }
};
