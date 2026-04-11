<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('client_court_hearings')) {
            Schema::create('client_court_hearings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id');
                $table->unsignedBigInteger('client_matter_id')->nullable();
                $table->string('court_name', 255)->nullable();
                $table->string('case_number', 100)->nullable();
                $table->string('judge_name', 150)->nullable();
                $table->date('hearing_date');
                $table->time('hearing_time')->nullable();
                $table->string('hearing_type', 100)->nullable();
                $table->text('notes')->nullable();
                $table->string('status', 50)->default('Scheduled');
                $table->timestamps();

                $table->index('client_id');
                $table->index('client_matter_id');
                $table->index('hearing_date');
            });
        } else {
            // Add any missing columns to existing table
            Schema::table('client_court_hearings', function (Blueprint $table) {
                if (!Schema::hasColumn('client_court_hearings', 'hearing_type')) {
                    $table->string('hearing_type', 100)->nullable()->after('hearing_time');
                }
                if (!Schema::hasColumn('client_court_hearings', 'judge_name')) {
                    $table->string('judge_name', 150)->nullable()->after('case_number');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_court_hearings');
    }
};
