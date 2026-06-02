<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_matter_tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('note_id')->nullable()->after('created_by');
            $table->foreign('note_id')->references('id')->on('notes')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('client_matter_tasks', function (Blueprint $table) {
            $table->dropForeign(['note_id']);
            $table->dropColumn('note_id');
        });
    }
};
