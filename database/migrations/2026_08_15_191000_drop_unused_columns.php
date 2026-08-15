<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop unused columns confirmed with no app/controller/view usage.
 *
 * Add more table => columns entries below as you confirm further removals
 * (e.g. booking_appointments.client_timezone).
 */
return new class extends Migration
{
    /**
     * @var array<string, list<string>>
     */
    private const DROP = [
        'staff' => [
            'time_zone',
        ],
        'client_matters' => [
            'tr_checklist_status',
            'visitor_checklist_status',
            'student_checklist_status',
            'pr_checklist_status',
            'employer_sponsored_checklist_status',
            'partner_checklist_status',
            'parents_checklist_status',
        ],
        // 'booking_appointments' => [
        //     'client_timezone',
        // ],
    ];

    public function up(): void
    {
        foreach (self::DROP as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $drop = array_values(array_filter(
                $columns,
                fn (string $c) => Schema::hasColumn($table, $c)
            ));
            if ($drop === []) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($drop) {
                $blueprint->dropColumn($drop);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('staff') && ! Schema::hasColumn('staff', 'time_zone')) {
            Schema::table('staff', function (Blueprint $table) {
                $table->string('time_zone', 50)->nullable()->after('show_dashboard_per');
            });
        }

        if (! Schema::hasTable('client_matters')) {
            return;
        }

        Schema::table('client_matters', function (Blueprint $table) {
            if (! Schema::hasColumn('client_matters', 'tr_checklist_status')) {
                $table->string('tr_checklist_status', 32)->nullable()
                    ->comment('TR sheet checklist status: active, hold, convert_to_client, discontinue');
            }
            if (! Schema::hasColumn('client_matters', 'visitor_checklist_status')) {
                $table->string('visitor_checklist_status', 32)->nullable()
                    ->after('tr_checklist_status')
                    ->comment('Visitor sheet checklist status: active, hold, convert_to_client, discontinue');
            }
            if (! Schema::hasColumn('client_matters', 'student_checklist_status')) {
                $table->string('student_checklist_status', 32)->nullable()
                    ->after('visitor_checklist_status')
                    ->comment('Student sheet checklist status: active, hold, convert_to_client, discontinue');
            }
            if (! Schema::hasColumn('client_matters', 'pr_checklist_status')) {
                $table->string('pr_checklist_status', 32)->nullable()
                    ->after('student_checklist_status')
                    ->comment('PR sheet checklist status: active, hold, convert_to_client, discontinue');
            }
            if (! Schema::hasColumn('client_matters', 'employer_sponsored_checklist_status')) {
                $table->string('employer_sponsored_checklist_status', 32)->nullable()
                    ->after('pr_checklist_status')
                    ->comment('Employer sponsored sheet checklist status: active, hold, convert_to_client, discontinue');
            }
            if (! Schema::hasColumn('client_matters', 'partner_checklist_status')) {
                $table->string('partner_checklist_status', 32)->nullable()
                    ->after('employer_sponsored_checklist_status')
                    ->comment('Partner sheet checklist status: active, hold, convert_to_client, discontinue');
            }
            if (! Schema::hasColumn('client_matters', 'parents_checklist_status')) {
                $table->string('parents_checklist_status', 32)->nullable()
                    ->after('partner_checklist_status')
                    ->comment('Parents sheet checklist status: active, hold, convert_to_client, discontinue');
            }
        });

        // if (Schema::hasTable('booking_appointments') && ! Schema::hasColumn('booking_appointments', 'client_timezone')) {
        //     Schema::table('booking_appointments', function (Blueprint $table) {
        //         $table->string('client_timezone', 50)->default('Australia/Melbourne');
        //     });
        // }
    }
};
