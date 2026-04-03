<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Unifies lead_tr_references, lead_visitor_references, lead_student_references,
     * lead_pr_references, lead_employer_sponsored_references into lead_matter_references.
     */
    public function up(): void
    {
        Schema::create('lead_matter_references', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50)->index();
            $table->unsignedBigInteger('lead_id')->index();
            $table->unsignedBigInteger('matter_id')->index();
            $table->date('checklist_sent_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('lead_id')->references('id')->on('admins')->onDelete('cascade');
            $table->foreign('matter_id')->references('id')->on('matters')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('staff')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('staff')->onDelete('set null');

            $table->unique(['type', 'lead_id', 'matter_id'], 'lead_matter_ref_type_lead_matter_unique');
        });

        $oldTables = [
            'tr' => 'lead_tr_references',
            'visitor' => 'lead_visitor_references',
            'student' => 'lead_student_references',
            'pr' => 'lead_pr_references',
            'employer-sponsored' => 'lead_employer_sponsored_references',
        ];

        foreach ($oldTables as $type => $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }
            $rows = DB::table($tableName)->get();
            foreach ($rows as $row) {
                DB::table('lead_matter_references')->insert([
                    'type' => $type,
                    'lead_id' => $row->lead_id,
                    'matter_id' => $row->matter_id,
                    'checklist_sent_at' => $row->checklist_sent_at,
                    'created_by' => $row->created_by,
                    'updated_by' => $row->updated_by,
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
            'tr' => 'lead_tr_references',
            'visitor' => 'lead_visitor_references',
            'student' => 'lead_student_references',
            'pr' => 'lead_pr_references',
            'employer-sponsored' => 'lead_employer_sponsored_references',
        ];

        foreach (array_values($oldTables) as $tableName) {
            Schema::create($tableName, function (Blueprint $table) use ($tableName) {
                $table->id();
                $table->unsignedBigInteger('lead_id')->index();
                $table->unsignedBigInteger('matter_id')->index();
                $table->date('checklist_sent_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->foreign('lead_id')->references('id')->on('admins')->onDelete('cascade');
                $table->foreign('matter_id')->references('id')->on('matters')->onDelete('cascade');
                $table->foreign('created_by')->references('id')->on('staff')->onDelete('set null');
                $table->foreign('updated_by')->references('id')->on('staff')->onDelete('set null');

                $table->unique(['lead_id', 'matter_id'], $tableName . '_lead_matter_unique');
            });
        }

        foreach ($oldTables as $type => $tableName) {
            $rows = DB::table('lead_matter_references')->where('type', $type)->get();
            foreach ($rows as $row) {
                DB::table($tableName)->insert([
                    'lead_id' => $row->lead_id,
                    'matter_id' => $row->matter_id,
                    'checklist_sent_at' => $row->checklist_sent_at,
                    'created_by' => $row->created_by,
                    'updated_by' => $row->updated_by,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
        }

        Schema::dropIfExists('lead_matter_references');
    }
};
