<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Superseded by signature_activities (2026_04_13). Kept as no-op for migration history.
        if (Schema::hasTable('document_notes') || Schema::hasTable('signature_activities')) {
            return;
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('document_notes');
    }
};
