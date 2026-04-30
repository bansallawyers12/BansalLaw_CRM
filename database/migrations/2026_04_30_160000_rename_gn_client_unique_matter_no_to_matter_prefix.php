<?php

use App\Models\Matter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historical matters used GN_ for type id 1 (Civil). Rename GN_* to the correct prefix (e.g. CIV_*)
     * when matters.nick_name / title allows, and the target ref is not already taken for that client.
     */
    public function up(): void
    {
        if (! Schema::hasTable('client_matters')) {
            return;
        }
        if (! Schema::hasColumn('client_matters', 'client_unique_matter_no')
            || ! Schema::hasColumn('client_matters', 'sel_matter_id')
            || ! Schema::hasColumn('client_matters', 'client_id')) {
            return;
        }

        $rows = DB::table('client_matters')
            ->select(['id', 'client_id', 'sel_matter_id', 'client_unique_matter_no'])
            ->whereNotNull('client_unique_matter_no')
            ->whereNotNull('sel_matter_id')
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $ref = (string) $row->client_unique_matter_no;
            if (! preg_match('/^GN_(\d+)$/', $ref, $m)) {
                continue;
            }
            $suffix = $m[1];
            $sid = (int) $row->sel_matter_id;
            $prefix = Matter::clientUniqueMatterNoPrefix($sid);
            if ($prefix === 'GN') {
                continue;
            }
            $newRef = $prefix . '_' . $suffix;
            if ($newRef === $ref) {
                continue;
            }
            $taken = DB::table('client_matters')
                ->where('client_id', $row->client_id)
                ->where('client_unique_matter_no', $newRef)
                ->where('id', '!=', $row->id)
                ->exists();
            if ($taken) {
                continue;
            }
            DB::table('client_matters')->where('id', $row->id)->update([
                'client_unique_matter_no' => $newRef,
            ]);
        }
    }

    /**
     * Reverse is not safe (prefixes may have been correct GN for unknown types).
     */
    public function down(): void
    {
        //
    }
};
