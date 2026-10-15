<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_matter_tasks', function (Blueprint $table) {
            $table->dropForeign(['client_matter_id']);
        });

        Schema::table('client_matter_tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('client_matter_id')->nullable()->change();
            $table->foreign('client_matter_id')
                ->references('id')
                ->on('client_matters')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('client_matter_tasks', function (Blueprint $table) {
            $table->dropForeign(['client_matter_id']);
        });

        Schema::table('client_matter_tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('client_matter_id')->nullable(false)->change();
            $table->foreign('client_matter_id')
                ->references('id')
                ->on('client_matters')
                ->cascadeOnDelete();
        });
    }
};
