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
     * Copies staff from admins to staff table, preserving IDs (one-time migration; staff identified by role != 7 at the time).
     * No mapping table - FK columns keep same values, will reference staff.id instead of admins.id.
     */
    public function up(): void
    {
        if (!Schema::hasTable('staff')) {
            return;
        }

        if (DB::table('staff')->exists()) {
            return;
        }

        DB::transaction(function () {
            // Columns to copy (excludes: telephone, profile_img, verified, ABN_number, is_archived, archived_by, archived_on - dropped by later migrations)
            $possibleColumns = [
                'id', 'first_name', 'last_name', 'email', 'password',
                'country_code', 'phone',
                'status',
                'role', 'position', 'team', 'permission', 'office_id',
                'show_dashboard_per', 'time_zone',
                'is_migration_agent', 'marn_number', 'legal_practitioner_number',
                'company_name', 'company_website',
                'business_address', 'business_phone', 'business_mobile',
                'business_email', 'tax_number',
                'remember_token', 'created_at', 'updated_at',
            ];
            $staffColumns = array_filter($possibleColumns, fn ($col) => Schema::hasColumn('admins', $col) && Schema::hasColumn('staff', $col));

            $staff = DB::table('admins')
                ->where('role', '!=', 7)
                ->when(Schema::hasColumn('admins', 'is_deleted'), fn ($q) => $q->whereNull('is_deleted'))
                ->orderBy('id')
                ->get($staffColumns);

            if ($staff->isEmpty()) {
                return;
            }

            // Valid FK references - null out orphaned values to avoid constraint violations
            $validBranchIds = Schema::hasTable('branches')
                ? DB::table('branches')->pluck('id')->flip()->all()
                : [];
            $validRoleIds = Schema::hasTable('user_roles')
                ? DB::table('user_roles')->pluck('id')->flip()->all()
                : [];
            foreach ($staff->chunk(50) as $chunk) {
                foreach ($chunk as $row) {
                    $insert = (array) $row;
                    if (isset($insert['office_id']) && $insert['office_id'] !== null && !isset($validBranchIds[$insert['office_id']])) {
                        $insert['office_id'] = null;
                    }
                    if (isset($insert['role']) && $insert['role'] !== null && !isset($validRoleIds[$insert['role']])) {
                        $insert['role'] = null;
                    }
                    DB::table('staff')->insert($insert);
                }
            }
            $this->updateSequence();
        });
    }

    protected function updateSequence(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $maxId = DB::table('staff')->max('id') ?? 0;

        if ($driver === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('staff', 'id'), ?)", [$maxId]);
        }

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE staff AUTO_INCREMENT = ?", [$maxId + 1]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('staff')->truncate();
    }
};
