<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Unifies lead_tr_reminders, lead_visitor_reminders, lead_student_reminders,
     * lead_pr_reminders, lead_employer_sponsored_reminders into lead_reminders.
     */
    public function up(): void
    {
        Schema::create('lead_reminders', function (Blueprint $table) {
            $table->id();
            $table->string('visa_type', 50)->index();
            $table->unsignedBigInteger('lead_id')->index();
            $table->string('type', 20)->comment('email, sms, or phone');
            $table->timestamp('reminded_at');
            $table->unsignedBigInteger('reminded_by')->nullable();
            $table->timestamps();

            $table->foreign('lead_id')->references('id')->on('admins')->onDelete('cascade');
            $table->foreign('reminded_by')->references('id')->on('staff')->onDelete('set null');

            $table->index(['visa_type', 'lead_id', 'type'], 'lead_reminders_visa_lead_type_idx');
        });

        $oldTables = [
            'tr' => 'lead_tr_reminders',
            'visitor' => 'lead_visitor_reminders',
            'student' => 'lead_student_reminders',
            'pr' => 'lead_pr_reminders',
            'employer-sponsored' => 'lead_employer_sponsored_reminders',
        ];

        foreach ($oldTables as $visaType => $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }
            $rows = DB::table($tableName)->get();
            foreach ($rows as $row) {
                DB::table('lead_reminders')->insert([
                    'visa_type' => $visaType,
                    'lead_id' => $row->lead_id,
                    'type' => $row->type,
                    'reminded_at' => $row->reminded_at,
                    'reminded_by' => $row->reminded_by,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
            Schema::dropIfExists($tableName);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $oldTables = [
            'tr' => 'lead_tr_reminders',
            'visitor' => 'lead_visitor_reminders',
            'student' => 'lead_student_reminders',
            'pr' => 'lead_pr_reminders',
            'employer-sponsored' => 'lead_employer_sponsored_reminders',
        ];

        foreach (array_values($oldTables) as $tableName) {
            Schema::create($tableName, function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('lead_id')->index();
                $t->string('type', 20);
                $t->timestamp('reminded_at');
                $t->unsignedBigInteger('reminded_by')->nullable();
                $t->timestamps();

                $t->foreign('lead_id')->references('id')->on('admins')->onDelete('cascade');
                $t->foreign('reminded_by')->references('id')->on('staff')->onDelete('set null');
            });
        }

        foreach ($oldTables as $visaType => $tableName) {
            $rows = DB::table('lead_reminders')->where('visa_type', $visaType)->get();
            foreach ($rows as $row) {
                DB::table($tableName)->insert([
                    'lead_id' => $row->lead_id,
                    'type' => $row->type,
                    'reminded_at' => $row->reminded_at,
                    'reminded_by' => $row->reminded_by,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
        }

        Schema::dropIfExists('lead_reminders');
    }
};
