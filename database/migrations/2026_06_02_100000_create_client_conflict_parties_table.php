<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_conflict_parties')) {
            Schema::create('client_conflict_parties', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->index();

                $table->string('party_type', 20)->default('individual');

                $table->string('party_role', 64)->nullable();

                $table->string('first_name', 255)->nullable();
                $table->string('last_name', 255)->nullable();
                $table->json('aliases')->nullable();
                $table->date('dob')->nullable();

                $table->string('company_name', 255)->nullable();
                $table->string('trading_name', 255)->nullable();
                $table->string('abn', 20)->nullable()->index();
                $table->string('acn', 20)->nullable();

                $table->string('address', 500)->nullable();
                $table->string('suburb', 100)->nullable();
                $table->string('state', 64)->nullable();
                $table->string('postcode', 20)->nullable();
                $table->string('country', 100)->nullable()->default('Australia');

                $table->string('rep_firm_name', 255)->nullable();
                $table->string('rep_name', 255)->nullable();
                $table->string('rep_email', 255)->nullable();
                $table->string('rep_phone', 64)->nullable();
                $table->string('rep_country_code', 10)->nullable();

                $table->text('notes')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->unsignedBigInteger('created_by')->nullable();

                $table->unsignedBigInteger('client_matter_id')->nullable()->index();

                $table->timestamps();

                $table->foreign('client_id')
                    ->references('id')->on('admins')
                    ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_conflict_parties');
    }
};
