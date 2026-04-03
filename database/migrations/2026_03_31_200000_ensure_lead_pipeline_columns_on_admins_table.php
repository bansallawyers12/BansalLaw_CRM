<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admins')) {
            return;
        }

        Schema::table('admins', function (Blueprint $table) {
            if (! Schema::hasColumn('admins', 'lead_status')) {
                $table->string('lead_status', 64)->nullable();
            }
            if (! Schema::hasColumn('admins', 'followup_date')) {
                $table->timestamp('followup_date')->nullable();
            }
        });

        if (Schema::hasColumn('admins', 'lead_status')) {
            DB::table('admins')->where('type', 'lead')->whereNull('lead_status')->update(['lead_status' => 'new']);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('admins')) {
            return;
        }

        Schema::table('admins', function (Blueprint $table) {
            if (Schema::hasColumn('admins', 'followup_date')) {
                $table->dropColumn('followup_date');
            }
            if (Schema::hasColumn('admins', 'lead_status')) {
                $table->dropColumn('lead_status');
            }
        });
    }
};
