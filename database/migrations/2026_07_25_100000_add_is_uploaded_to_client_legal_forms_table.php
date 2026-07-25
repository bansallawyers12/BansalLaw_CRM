<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_legal_forms', function (Blueprint $table) {
            $table->boolean('is_uploaded')->default(false)->after('form_type');
        });
    }

    public function down(): void
    {
        Schema::table('client_legal_forms', function (Blueprint $table) {
            $table->dropColumn('is_uploaded');
        });
    }
};
