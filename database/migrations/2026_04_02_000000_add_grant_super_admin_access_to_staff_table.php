<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            if (! Schema::hasColumn('staff', 'grant_super_admin_access')) {
                $table->boolean('grant_super_admin_access')->nullable()->after('quick_access_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            if (Schema::hasColumn('staff', 'grant_super_admin_access')) {
                $table->dropColumn('grant_super_admin_access');
            }
        });
    }
};
