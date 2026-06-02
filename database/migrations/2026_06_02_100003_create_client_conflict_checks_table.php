<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_conflict_checks')) {
            Schema::create('client_conflict_checks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->index();
                $table->unsignedBigInteger('checked_by');

                $table->timestamp('checked_at')->useCurrent();
                $table->json('search_terms')->nullable();
                $table->json('matches')->nullable();

                $table->string('outcome', 20)->default('pending');

                $table->text('outcome_notes')->nullable();
                $table->boolean('consent_obtained')->default(false);
                $table->text('consent_notes')->nullable();

                $table->timestamps();

                $table->foreign('client_id')
                    ->references('id')->on('admins')
                    ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_conflict_checks');
    }
};
