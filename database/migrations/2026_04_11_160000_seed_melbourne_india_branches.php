<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Default offices for the Staff "Office" dropdown (branches.office_name).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('branches')) {
            return;
        }

        if (! Schema::hasColumn('branches', 'office_name')) {
            return;
        }

        $now = now();

        $rows = [
            [
                'office_name' => 'Melbourne',
                'city' => 'Melbourne',
                'state' => 'VIC',
                'country' => 'Australia',
            ],
            [
                'office_name' => 'India',
                'city' => null,
                'state' => null,
                'country' => 'India',
            ],
        ];

        $columns = array_flip(Schema::getColumnListing('branches'));

        foreach ($rows as $row) {
            if (DB::table('branches')->whereRaw('LOWER(TRIM(office_name)) = ?', [strtolower($row['office_name'])])->exists()) {
                continue;
            }

            $insert = [
                'office_name' => $row['office_name'],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            foreach (['city', 'state', 'country', 'address', 'zip', 'email', 'phone', 'mobile', 'contact_person', 'choose_admin'] as $col) {
                if (isset($row[$col]) && isset($columns[$col])) {
                    $insert[$col] = $row[$col];
                }
            }

            $insert = array_intersect_key($insert, $columns);

            DB::table('branches')->insert($insert);
        }

        $this->syncPostgresSequenceIfNeeded();
    }

    public function down(): void
    {
        // Intentionally empty: do not delete offices that may be referenced by staff/matters.
    }

    protected function syncPostgresSequenceIfNeeded(): void
    {
        if (! Schema::hasTable('branches')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        $max = DB::table('branches')->max('id');
        if ($max === null || (int) $max < 1) {
            return;
        }

        $schema = Schema::getConnection()->getConfig('schema') ?? 'public';
        $row = DB::selectOne(
            'SELECT pg_get_serial_sequence(?, ?) AS seq',
            [$schema.'.branches', 'id']
        );

        if (! $row || empty($row->seq)) {
            return;
        }

        DB::statement('SELECT setval(?, ?::bigint, true)', [$row->seq, $max]);
    }
};
