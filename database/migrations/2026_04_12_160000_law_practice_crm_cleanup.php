<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('staff') && Schema::hasColumn('staff', 'sheet_access')) {
            Schema::table('staff', function (Blueprint $table) {
                $table->dropColumn('sheet_access');
            });
        }

        if (Schema::hasTable('documents')) {
            DB::table('documents')->where('doc_type', 'visa')->update(['doc_type' => 'matter']);
        }

        if (Schema::hasTable('matters')) {
            $row = DB::table('matters')->where('title', 'Immigration matter')->first();
            if ($row) {
                $inUse = Schema::hasTable('client_matters')
                    ? DB::table('client_matters')->where('sel_matter_id', $row->id)->exists()
                    : false;
                if (! $inUse) {
                    DB::table('matters')->where('id', $row->id)->delete();
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('staff') && ! Schema::hasColumn('staff', 'sheet_access')) {
            Schema::table('staff', function (Blueprint $table) {
                $table->text('sheet_access')->nullable()->after('permission');
            });
        }

        if (Schema::hasTable('documents')) {
            DB::table('documents')->where('doc_type', 'matter')->update(['doc_type' => 'visa']);
        }
    }
};
