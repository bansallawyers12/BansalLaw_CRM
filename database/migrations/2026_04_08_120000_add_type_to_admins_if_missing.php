<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Imported / minimal PostgreSQL dumps may omit admins.type while the CRM expects
 * type IN ('client','lead') for contacts (staff live in staff table or role != 7).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admins') || Schema::hasColumn('admins', 'type')) {
            return;
        }

        Schema::table('admins', function (Blueprint $table) {
            $table->string('type', 32)->nullable()->index();
        });

        $hasStaff = Schema::hasTable('staff') && DB::table('staff')->exists();

        if ($hasStaff) {
            $staffIds = DB::table('staff')->pluck('id')->all();
            DB::table('admins')->whereIn('id', $staffIds)->update(['type' => null]);
            DB::table('admins')->whereNotIn('id', $staffIds)
                ->whereNotNull('lead_status')
                ->update(['type' => 'lead']);
            DB::table('admins')->whereNotIn('id', $staffIds)
                ->whereNull('type')
                ->update(['type' => 'client']);
        } else {
            DB::table('admins')
                ->where('role', '!=', 7)
                ->whereNotNull('role')
                ->update(['type' => null]);
            DB::table('admins')
                ->whereNotNull('lead_status')
                ->whereNull('type')
                ->update(['type' => 'lead']);
            DB::table('admins')
                ->whereNull('type')
                ->update(['type' => 'client']);
        }

        if (Schema::hasColumn('admins', 'lead_status')) {
            DB::table('admins')
                ->where('type', 'lead')
                ->whereNull('lead_status')
                ->update(['lead_status' => 'new']);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('admins') || ! Schema::hasColumn('admins', 'type')) {
            return;
        }

        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
