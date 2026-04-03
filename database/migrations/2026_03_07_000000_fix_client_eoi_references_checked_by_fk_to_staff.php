<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The CRM uses the 'admin' guard with the 'staff' provider, so auth('admin')->id()
     * returns staff.id. The original FK pointed to admins.id, causing a foreign key
     * violation when staff verify EOI. This migration fixes the FK to reference staff.
     */
    public function up(): void
    {
        Schema::table('client_eoi_references', function (Blueprint $table) {
            $table->dropForeign(['checked_by']);
        });

        // Null out any checked_by values that are not valid staff ids (e.g. legacy admin ids)
        // so the new FK constraint can be applied.
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('
                UPDATE client_eoi_references
                SET checked_by = NULL
                WHERE checked_by IS NOT NULL
                AND NOT EXISTS (SELECT 1 FROM staff s WHERE s.id = client_eoi_references.checked_by)
            ');
        } else {
            DB::statement('
                UPDATE client_eoi_references
                SET checked_by = NULL
                WHERE checked_by IS NOT NULL
                AND checked_by NOT IN (SELECT id FROM staff)
            ');
        }

        Schema::table('client_eoi_references', function (Blueprint $table) {
            $table->foreign('checked_by')->references('id')->on('staff')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_eoi_references', function (Blueprint $table) {
            $table->dropForeign(['checked_by']);
        });

        Schema::table('client_eoi_references', function (Blueprint $table) {
            $table->foreign('checked_by')->references('id')->on('admins')->onDelete('set null');
        });
    }
};
