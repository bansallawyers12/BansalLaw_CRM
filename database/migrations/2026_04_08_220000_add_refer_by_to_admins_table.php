<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admins')) {
            return;
        }
        Schema::table('admins', function (Blueprint $table) {
            if (! Schema::hasColumn('admins', 'refer_by')) {
                $table->string('refer_by', 500)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('admins') || ! Schema::hasColumn('admins', 'refer_by')) {
            return;
        }
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn('refer_by');
        });
    }
};
