<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CLIENT_EMAILS = [
        'khusi.bansal01@gmail.com',
        'dwivedi@gmail.com',
    ];

    /**
     * Appointments on or before this instant are removed (inclusive of 30 April entire day in app timezone).
     */
    private function cutoff(): Carbon
    {
        return Carbon::parse('2026-04-30', config('app.timezone'))->endOfDay();
    }

    public function up(): void
    {
        if (! Schema::hasTable('booking_appointments')) {
            return;
        }

        if (! Schema::hasColumn('booking_appointments', 'appointment_datetime')) {
            return;
        }

        $emails = array_map('strtolower', self::CLIENT_EMAILS);
        $placeholders = implode(',', array_fill(0, count($emails), '?'));
        $cutoff = $this->cutoff();

        $ids = DB::table('booking_appointments')
            ->whereRaw('lower(client_email) in (' . $placeholders . ')', $emails)
            ->where('appointment_datetime', '<=', $cutoff)
            ->pluck('id')
            ->all();

        if ($ids === []) {
            return;
        }

        DB::transaction(function () use ($ids): void {
            if (Schema::hasTable('appointment_payments')) {
                DB::table('appointment_payments')->whereIn('appointment_id', $ids)->delete();
            }
            if (Schema::hasTable('front_desk_check_ins')) {
                DB::table('front_desk_check_ins')->whereIn('appointment_id', $ids)->update(['appointment_id' => null]);
            }
            DB::table('booking_appointments')->whereIn('id', $ids)->delete();
        });
    }

    /**
     * Irreversible: deleted rows are not restored.
     */
    public function down(): void
    {
    }
};
