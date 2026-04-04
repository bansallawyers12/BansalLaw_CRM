<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy installs may only have a minimal checkin_logs row (stub + walk-in columns).
 * Office visit flows require status, office, assignee, session fields, etc.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('checkin_logs')) {
            return;
        }

        Schema::table('checkin_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('checkin_logs', 'client_id')) {
                $table->unsignedBigInteger('client_id')->nullable()->index();
            }
            if (! Schema::hasColumn('checkin_logs', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->index();
            }
            if (! Schema::hasColumn('checkin_logs', 'visit_purpose')) {
                $table->text('visit_purpose')->nullable();
            }
            if (! Schema::hasColumn('checkin_logs', 'office')) {
                $table->unsignedBigInteger('office')->nullable()->index();
            }
            if (! Schema::hasColumn('checkin_logs', 'contact_type')) {
                $table->string('contact_type', 32)->nullable();
            }
            if (! Schema::hasColumn('checkin_logs', 'status')) {
                $table->unsignedTinyInteger('status')->default(0);
            }
            if (! Schema::hasColumn('checkin_logs', 'date')) {
                $table->date('date')->nullable();
            }
            if (! Schema::hasColumn('checkin_logs', 'sesion_start')) {
                $table->timestamp('sesion_start')->nullable();
            }
            if (! Schema::hasColumn('checkin_logs', 'sesion_end')) {
                $table->timestamp('sesion_end')->nullable();
            }
            if (! Schema::hasColumn('checkin_logs', 'wait_time')) {
                $table->string('wait_time', 64)->nullable();
            }
            if (! Schema::hasColumn('checkin_logs', 'attend_time')) {
                $table->string('attend_time', 64)->nullable();
            }
            if (! Schema::hasColumn('checkin_logs', 'wait_type')) {
                $table->unsignedTinyInteger('wait_type')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('checkin_logs')) {
            return;
        }

        $cols = [
            'wait_type',
            'attend_time',
            'wait_time',
            'sesion_end',
            'sesion_start',
            'date',
            'status',
            'contact_type',
            'office',
            'visit_purpose',
            'user_id',
            'client_id',
        ];
        $toDrop = array_values(array_filter($cols, fn (string $c) => Schema::hasColumn('checkin_logs', $c)));
        if ($toDrop === []) {
            return;
        }

        Schema::table('checkin_logs', function (Blueprint $table) use ($toDrop) {
            $table->dropColumn($toDrop);
        });
    }
};
