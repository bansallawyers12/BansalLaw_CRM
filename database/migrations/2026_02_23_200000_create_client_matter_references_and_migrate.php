<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Unifies client_tr_references, client_visitor_references, client_student_references,
     * client_pr_references, client_employer_sponsored_references into client_matter_references.
     */
    public function up(): void
    {
        Schema::create('client_matter_references', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50)->index();
            $table->unsignedBigInteger('client_id')->index();
            $table->unsignedBigInteger('client_matter_id')->index();

            $table->text('current_status')->nullable();
            $table->string('payment_display_note', 100)->nullable();
            $table->string('institute_override', 255)->nullable();
            $table->string('visa_category_override', 50)->nullable();
            $table->text('comments')->nullable();
            $table->date('checklist_sent_at')->nullable();
            $table->boolean('is_pinned')->default(false)->index();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('admins')->onDelete('cascade');
            $table->foreign('client_matter_id')->references('id')->on('client_matters')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('staff')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('staff')->onDelete('set null');

            $table->unique(['type', 'client_id', 'client_matter_id'], 'client_matter_ref_type_client_matter_unique');
        });

        $oldTables = [
            'tr' => 'client_tr_references',
            'visitor' => 'client_visitor_references',
            'student' => 'client_student_references',
            'pr' => 'client_pr_references',
            'employer-sponsored' => 'client_employer_sponsored_references',
        ];

        foreach ($oldTables as $type => $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }
            $hasIsPinned = Schema::hasColumn($tableName, 'is_pinned');
            $rows = DB::table($tableName)->get();
            foreach ($rows as $row) {
                DB::table('client_matter_references')->insert([
                    'type' => $type,
                    'client_id' => $row->client_id,
                    'client_matter_id' => $row->client_matter_id,
                    'current_status' => $row->current_status,
                    'payment_display_note' => $row->payment_display_note,
                    'institute_override' => $row->institute_override,
                    'visa_category_override' => $row->visa_category_override ?? null,
                    'comments' => $row->comments,
                    'checklist_sent_at' => $row->checklist_sent_at,
                    'is_pinned' => $hasIsPinned ? ($row->is_pinned ?? false) : false,
                    'created_by' => $row->created_by,
                    'updated_by' => $row->updated_by,
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
            'tr' => 'client_tr_references',
            'visitor' => 'client_visitor_references',
            'student' => 'client_student_references',
            'pr' => 'client_pr_references',
            'employer-sponsored' => 'client_employer_sponsored_references',
        ];

        $uniqueNames = [
            'client_tr_references' => 'client_tr_references_client_matter_unique',
            'client_visitor_references' => 'client_visitor_references_client_matter_unique',
            'client_student_references' => 'client_student_references_client_matter_unique',
            'client_pr_references' => 'client_pr_references_client_matter_unique',
            'client_employer_sponsored_references' => 'client_emp_sponsored_ref_client_matter_unique',
        ];

        foreach ($oldTables as $tableName) {
            Schema::create($tableName, function (Blueprint $table) use ($tableName, $uniqueNames) {
                $table->id();
                $table->unsignedBigInteger('client_id')->index();
                $table->unsignedBigInteger('client_matter_id')->index();
                $table->text('current_status')->nullable();
                $table->string('payment_display_note', 100)->nullable();
                $table->string('institute_override', 255)->nullable();
                $table->string('visa_category_override', 50)->nullable();
                $table->text('comments')->nullable();
                $table->date('checklist_sent_at')->nullable();
                $table->boolean('is_pinned')->default(false)->index();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->foreign('client_id')->references('id')->on('admins')->onDelete('cascade');
                $table->foreign('client_matter_id')->references('id')->on('client_matters')->onDelete('cascade');
                $table->foreign('created_by')->references('id')->on('staff')->onDelete('set null');
                $table->foreign('updated_by')->references('id')->on('staff')->onDelete('set null');
                $table->unique(['client_id', 'client_matter_id'], $uniqueNames[$tableName]);
            });
        }

        foreach ($oldTables as $type => $tableName) {
            $rows = DB::table('client_matter_references')->where('type', $type)->get();
            foreach ($rows as $row) {
                DB::table($tableName)->insert([
                    'client_id' => $row->client_id,
                    'client_matter_id' => $row->client_matter_id,
                    'current_status' => $row->current_status,
                    'payment_display_note' => $row->payment_display_note,
                    'institute_override' => $row->institute_override,
                    'visa_category_override' => $row->visa_category_override,
                    'comments' => $row->comments,
                    'checklist_sent_at' => $row->checklist_sent_at,
                    'is_pinned' => $row->is_pinned,
                    'created_by' => $row->created_by,
                    'updated_by' => $row->updated_by,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
        }

        Schema::dropIfExists('client_matter_references');
    }
};
