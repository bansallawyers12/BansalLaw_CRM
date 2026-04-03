<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('client_occupations', function (Blueprint $table) {
            $table->unsignedBigInteger('anzsco_occupation_id')->nullable()->after('id');
            $table->foreign('anzsco_occupation_id')->references('id')->on('anzsco_occupations')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_occupations', function (Blueprint $table) {
            $table->dropForeign(['anzsco_occupation_id']);
            $table->dropColumn('anzsco_occupation_id');
        });
    }
};
