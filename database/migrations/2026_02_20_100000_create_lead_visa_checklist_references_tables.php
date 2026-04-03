<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates lead reference tables for visa-type checklist sheets (TR, Visitor, etc.).
     * Leads appear in Checklist tab for follow-up; workflow stays hidden.
     */
    public function up(): void
    {
        $tables = [
            'lead_tr_references',
            'lead_visitor_references',
            'lead_student_references',
            'lead_pr_references',
            'lead_employer_sponsored_references',
        ];

        foreach ($tables as $tableName) {
            Schema::create($tableName, function (Blueprint $t) use ($tableName) {
                $t->id();
                $t->unsignedBigInteger('lead_id')->index();
                $t->unsignedBigInteger('matter_id')->index();
                $t->date('checklist_sent_at')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();

                $t->foreign('lead_id')->references('id')->on('admins')->onDelete('cascade');
                $t->foreign('matter_id')->references('id')->on('matters')->onDelete('cascade');
                $t->foreign('created_by')->references('id')->on('staff')->onDelete('set null');
                $t->foreign('updated_by')->references('id')->on('staff')->onDelete('set null');

                $t->unique(['lead_id', 'matter_id'], $tableName . '_lead_matter_unique');
            });
        }

        // Lead reminder tables for follow-up (email/sms)
        $reminderTables = [
            'lead_tr_reminders',
            'lead_visitor_reminders',
            'lead_student_reminders',
            'lead_pr_reminders',
            'lead_employer_sponsored_reminders',
        ];

        foreach ($reminderTables as $remTable) {
            Schema::create($remTable, function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('lead_id')->index();
                $t->string('type', 20); // email, sms, phone
                $t->timestamp('reminded_at');
                $t->unsignedBigInteger('reminded_by')->nullable();
                $t->timestamps();

                $t->foreign('lead_id')->references('id')->on('admins')->onDelete('cascade');
                $t->foreign('reminded_by')->references('id')->on('staff')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $refTables = [
            'lead_tr_references', 'lead_visitor_references', 'lead_student_references',
            'lead_pr_references', 'lead_employer_sponsored_references',
        ];
        $remTables = [
            'lead_tr_reminders', 'lead_visitor_reminders', 'lead_student_reminders',
            'lead_pr_reminders', 'lead_employer_sponsored_reminders',
        ];
        foreach (array_merge($remTables, $refTables) as $t) {
            Schema::dropIfExists($t);
        }
    }
};
