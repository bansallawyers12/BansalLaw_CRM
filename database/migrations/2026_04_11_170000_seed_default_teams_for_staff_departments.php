<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Staff "Department (Team)" dropdown uses teams.id / teams.name (Team model).
 */
return new class extends Migration
{
    /** @var string[] */
    private const NAMES = [
        'Calling Team',
        'Reception',
        'Accounts',
        'India Team',
        'Melbourne Office Team',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('teams')) {
            return;
        }

        if (! Schema::hasColumn('teams', 'name')) {
            return;
        }

        $now = now();
        $columns = array_flip(Schema::getColumnListing('teams'));

        foreach (self::NAMES as $name) {
            if (DB::table('teams')->whereRaw('LOWER(TRIM(name)) = ?', [strtolower($name)])->exists()) {
                continue;
            }

            $insert = ['name' => $name, 'created_at' => $now, 'updated_at' => $now];
            if (isset($columns['color'])) {
                $insert['color'] = null;
            }

            $insert = array_intersect_key($insert, $columns);
            DB::table('teams')->insert($insert);
        }

        $this->syncPostgresSequenceIfNeeded();
    }

    public function down(): void
    {
        // Do not delete: staff.team may reference team ids.
    }

    protected function syncPostgresSequenceIfNeeded(): void
    {
        if (! Schema::hasTable('teams')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        $max = DB::table('teams')->max('id');
        if ($max === null || (int) $max < 1) {
            return;
        }

        $schema = Schema::getConnection()->getConfig('schema') ?? 'public';
        $row = DB::selectOne(
            'SELECT pg_get_serial_sequence(?, ?) AS seq',
            [$schema.'.teams', 'id']
        );

        if (! $row || empty($row->seq)) {
            return;
        }

        DB::statement('SELECT setval(?, ?::bigint, true)', [$row->seq, $max]);
    }
};
