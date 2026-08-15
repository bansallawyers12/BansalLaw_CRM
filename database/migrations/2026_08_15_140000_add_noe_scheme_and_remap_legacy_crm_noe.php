<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('booking_appointments')) {
            return;
        }

        if (! Schema::hasColumn('booking_appointments', 'noe_scheme')) {
            Schema::table('booking_appointments', function (Blueprint $table) {
                // Existing rows default to immigration; app sets crm on new Lawyers bookings.
                $table->string('noe_scheme', 32)->default('immigration')->after('noe_id');
            });
        }

        $crmEnquiryTypes = [
            'criminal_law',
            'family_law',
            'corporate_law',
            'personal_law',
            'immigration_law',
            'property_law',
            'commercial_law',
            'migration_advice',
            'migration_consultation',
        ];

        DB::table('booking_appointments')
            ->whereIn('enquiry_type', $crmEnquiryTypes)
            ->update(['noe_scheme' => 'crm']);

        // Leftover CRM NOE ids 9–12 → Immigration Law (5).
        DB::table('booking_appointments')
            ->whereIn('noe_id', [9, 10, 11, 12])
            ->update([
                'noe_id' => 5,
                'noe_scheme' => 'crm',
                'service_type' => 'Immigration Law',
                'enquiry_type' => 'immigration_law',
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('booking_appointments')) {
            return;
        }

        if (Schema::hasColumn('booking_appointments', 'noe_scheme')) {
            Schema::table('booking_appointments', function (Blueprint $table) {
                $table->dropColumn('noe_scheme');
            });
        }
    }
};
