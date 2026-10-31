<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_roles') || Schema::hasColumn('user_roles', 'is_active')) {
            return;
        }

        Schema::table('user_roles', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_roles') || ! Schema::hasColumn('user_roles', 'is_active')) {
            return;
        }

        Schema::table('user_roles', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
