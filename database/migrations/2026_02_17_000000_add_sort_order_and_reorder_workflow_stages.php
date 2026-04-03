<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add sort_order to workflow_stages and ensure "Ready to Close" comes before "File Closed".
     * Run the migration.
     */
    public function up(): void
    {
        Schema::table('workflow_stages', function (Blueprint $table) {
            $table->integer('sort_order')->nullable()->after('name');
        });

        // Backfill: set sort_order = id for all
        DB::table('workflow_stages')->update(['sort_order' => DB::raw('id')]);

        // Swap "File Closed" and "Ready to Close" so Ready to Close comes first
        $fileClosed = DB::table('workflow_stages')->where('name', 'File Closed')->first();
        $readyToClose = DB::table('workflow_stages')->where('name', 'Ready to Close')->first();

        if ($fileClosed && $readyToClose) {
            // Ensure Ready to Close comes before File Closed: assign Ready the lower sort_order
            $fileClosedSort = (int) ($fileClosed->sort_order ?? $fileClosed->id);
            $readyToCloseSort = (int) ($readyToClose->sort_order ?? $readyToClose->id);
            $minSort = min($fileClosedSort, $readyToCloseSort);
            $maxSort = max($fileClosedSort, $readyToCloseSort);

            // Ready to Close gets min (appears first), File Closed gets max
            DB::table('workflow_stages')->where('id', $readyToClose->id)->update(['sort_order' => $minSort]);
            DB::table('workflow_stages')->where('id', $fileClosed->id)->update(['sort_order' => $maxSort]);
        }

        // Make sort_order not null for existing rows; new rows will need it set
        DB::table('workflow_stages')->whereNull('sort_order')->update(['sort_order' => DB::raw('id')]);
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('workflow_stages', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
