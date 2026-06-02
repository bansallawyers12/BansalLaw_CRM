<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conflict_party_emails')) {
            Schema::create('conflict_party_emails', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('conflict_party_id')->index();
                $table->string('email_type', 64)->nullable()->default('Personal');
                $table->string('email', 255);
                $table->timestamps();

                $table->foreign('conflict_party_id')
                    ->references('id')->on('client_conflict_parties')
                    ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('conflict_party_emails');
    }
};
