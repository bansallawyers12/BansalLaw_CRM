<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds decision_outcome and decision_note to client_matters for matters at "Decision Received" stage.
     */
    public function up(): void
    {
        Schema::table('client_matters', function (Blueprint $table) {
            $table->string('decision_outcome', 50)->nullable()->after('workflow_stage_id');
            $table->text('decision_note')->nullable()->after('decision_outcome');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_matters', function (Blueprint $table) {
            $table->dropColumn(['decision_outcome', 'decision_note']);
        });
    }
};
