<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Multiple sponsorship rows per company; legacy companies.* columns mirror the first row for compatibility.
     */
    public function up(): void
    {
        Schema::create('company_sponsorships', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('sponsorship_type', 50)->nullable();
            $table->string('sponsorship_status', 50)->nullable();
            $table->date('sponsorship_start_date')->nullable();
            $table->date('sponsorship_end_date')->nullable();
            $table->string('trn', 50)->nullable();
            $table->boolean('regional_sponsorship')->default(false);
            $table->boolean('adverse_information')->default(false);
            $table->text('previous_sponsorship_notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });

        if (! Schema::hasTable('companies')) {
            return;
        }

        DB::table('companies')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $c) {
                $hasData = $c->sponsorship_type || $c->sponsorship_status || $c->trn
                    || $c->sponsorship_start_date || $c->sponsorship_end_date
                    || $c->previous_sponsorship_notes
                    || (isset($c->regional_sponsorship) && (int) $c->regional_sponsorship)
                    || (isset($c->adverse_information) && (int) $c->adverse_information);
                if (! $hasData) {
                    continue;
                }
                DB::table('company_sponsorships')->insert([
                    'company_id' => $c->id,
                    'sponsorship_type' => $c->sponsorship_type,
                    'sponsorship_status' => $c->sponsorship_status,
                    'sponsorship_start_date' => $c->sponsorship_start_date,
                    'sponsorship_end_date' => $c->sponsorship_end_date,
                    'trn' => $c->trn,
                    'regional_sponsorship' => (bool) ($c->regional_sponsorship ?? false),
                    'adverse_information' => (bool) ($c->adverse_information ?? false),
                    'previous_sponsorship_notes' => $c->previous_sponsorship_notes,
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_sponsorships');
    }
};
