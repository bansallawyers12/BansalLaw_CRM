<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates company_trading_names for multiple trading names per company.
     * companies.trading_name kept for backward compat.
     */
    public function up(): void
    {
        Schema::create('company_trading_names', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('trading_name', 255);
            $table->boolean('is_primary')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('has_trading_name')->default(false)->after('trading_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('has_trading_name');
        });
        Schema::dropIfExists('company_trading_names');
    }
};
