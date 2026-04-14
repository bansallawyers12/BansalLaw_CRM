<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('documents') && Schema::hasColumn('documents', 'form956_id')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->dropColumn('form956_id');
            });
        }

        Schema::dropIfExists('form956');
    }

    public function down(): void
    {
        // Form 956 feature removed; no automatic rollback.
    }
};
