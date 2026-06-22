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
        Schema::table('client_matters', function (Blueprint $table) {
            $table->string('discontinue_reason')->nullable()->after('closed_by');
            $table->text('discontinue_notes')->nullable()->after('discontinue_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_matters', function (Blueprint $table) {
            $table->dropColumn(['discontinue_reason', 'discontinue_notes']);
        });
    }
};
