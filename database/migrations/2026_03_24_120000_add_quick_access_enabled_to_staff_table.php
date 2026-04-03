<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            if (! Schema::hasColumn('staff', 'quick_access_enabled')) {
                $table->boolean('quick_access_enabled')->default(false);
            }
        });

        DB::table('staff')->where('role', 14)->update(['quick_access_enabled' => true]);
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            if (Schema::hasColumn('staff', 'quick_access_enabled')) {
                $table->dropColumn('quick_access_enabled');
            }
        });
    }
};
