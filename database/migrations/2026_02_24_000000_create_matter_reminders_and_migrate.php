<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Unifies tr_matter_reminders, visitor_matter_reminders, student_matter_reminders,
     * pr_matter_reminders, employer_sponsored_matter_reminders into matter_reminders.
     */
    public function up(): void
    {
        Schema::create('matter_reminders', function (Blueprint $table) {
            $table->id();
            $table->string('visa_type', 50)->index();
            $table->unsignedBigInteger('client_matter_id')->index();
            $table->string('type', 20)->comment('email, sms, or phone');
            $table->timestamp('reminded_at');
            $table->unsignedBigInteger('reminded_by')->nullable()->comment('Staff who sent the reminder');
            $table->timestamps();

            $table->foreign('client_matter_id')->references('id')->on('client_matters')->onDelete('cascade');
            $table->foreign('reminded_by')->references('id')->on('staff')->onDelete('set null');

            $table->index(['visa_type', 'client_matter_id', 'type'], 'matter_reminders_visa_matter_type_idx');
        });

        $oldTables = [
            'tr' => 'tr_matter_reminders',
            'visitor' => 'visitor_matter_reminders',
            'student' => 'student_matter_reminders',
            'pr' => 'pr_matter_reminders',
            'employer-sponsored' => 'employer_sponsored_matter_reminders',
        ];

        foreach ($oldTables as $visaType => $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }
            $rows = DB::table($tableName)->get();
            foreach ($rows as $row) {
                DB::table('matter_reminders')->insert([
                    'visa_type' => $visaType,
                    'client_matter_id' => $row->client_matter_id,
                    'type' => $row->type,
                    'reminded_at' => $row->reminded_at,
                    'reminded_by' => $row->reminded_by,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
        }

        foreach ($oldTables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::dropIfExists($tableName);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $oldTables = [
            'tr' => 'tr_matter_reminders',
            'visitor' => 'visitor_matter_reminders',
            'student' => 'student_matter_reminders',
            'pr' => 'pr_matter_reminders',
            'employer-sponsored' => 'employer_sponsored_matter_reminders',
        ];

        $indexNames = [
            'tr_matter_reminders' => 'tr_matter_reminders_matter_type_idx',
            'visitor_matter_reminders' => 'visitor_matter_reminders_matter_type_idx',
            'student_matter_reminders' => 'student_matter_reminders_matter_type_idx',
            'pr_matter_reminders' => 'pr_matter_reminders_matter_type_idx',
            'employer_sponsored_matter_reminders' => 'emp_sponsored_reminders_matter_type_idx',
        ];

        foreach ($oldTables as $tableName) {
            Schema::create($tableName, function (Blueprint $table) use ($tableName, $indexNames) {
                $table->id();
                $table->unsignedBigInteger('client_matter_id')->index();
                $table->string('type', 20)->comment('email, sms, or phone');
                $table->timestamp('reminded_at');
                $table->unsignedBigInteger('reminded_by')->nullable()->comment('Staff who sent the reminder');
                $table->timestamps();

                $table->foreign('client_matter_id')->references('id')->on('client_matters')->onDelete('cascade');
                $table->foreign('reminded_by')->references('id')->on('staff')->onDelete('set null');

                $table->index(['client_matter_id', 'type'], $indexNames[$tableName]);
            });
        }

        foreach ($oldTables as $visaType => $tableName) {
            $rows = DB::table('matter_reminders')->where('visa_type', $visaType)->get();
            foreach ($rows as $row) {
                DB::table($tableName)->insert([
                    'client_matter_id' => $row->client_matter_id,
                    'type' => $row->type,
                    'reminded_at' => $row->reminded_at,
                    'reminded_by' => $row->reminded_by,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
        }

        Schema::dropIfExists('matter_reminders');
    }
};
