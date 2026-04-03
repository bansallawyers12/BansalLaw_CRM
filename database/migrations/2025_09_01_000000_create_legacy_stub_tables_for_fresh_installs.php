<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy CRM tables originally lived outside Laravel migrations. Stubs allow migrate:fresh on PostgreSQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        $stub = function (string $name): void {
            if (Schema::hasTable($name)) {
                return;
            }
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->timestamps();
            });
        };

        if (! Schema::hasTable('client_qualifications')) {
            Schema::create('client_qualifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->nullable()->index();
                $table->string('country', 191)->nullable();
                $table->boolean('relevant_qualification')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('client_spouse_details')) {
            Schema::create('client_spouse_details', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->nullable()->index();
                $table->date('spouse_assessment_date')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('client_experiences')) {
            Schema::create('client_experiences', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->nullable()->index();
                $table->string('job_country', 191)->nullable();
                $table->string('job_type')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('client_testscore')) {
            Schema::create('client_testscore', function (Blueprint $table) {
                $table->id();
                $table->integer('overall_score')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('client_eoi_references')) {
            Schema::create('client_eoi_references', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->nullable()->index();
                $table->string('EOI_subclass')->nullable();
                $table->string('EOI_state')->nullable();
                $table->date('EOI_submission_date')->nullable();
                $table->string('EOI_occupation')->nullable();
                $table->string('eoi_status', 32)->default('draft');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('activities_logs')) {
            Schema::create('activities_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->nullable()->index();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('signers')) {
            Schema::create('signers', function (Blueprint $table) {
                $table->id();
                $table->string('status', 7)->nullable();
                $table->unsignedInteger('reminder_count')->nullable()->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('client_matters')) {
            Schema::create('client_matters', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->nullable()->index();
                $table->string('matter_status')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('receiver_id')->nullable();
                $table->string('notification_type')->nullable();
                $table->unsignedTinyInteger('receiver_status')->nullable()->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('notes')) {
            Schema::create('notes', function (Blueprint $table) {
                $table->id();
                $table->string('type')->nullable();
                $table->string('status')->nullable();
                $table->unsignedBigInteger('assigned_to')->nullable();
                $table->tinyInteger('folloup')->nullable();
                $table->string('task_group')->nullable();
                $table->timestamp('followup_date')->nullable();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->timestamps();
            });
        }

        foreach ([
            'branches',
            'user_roles',
            'client_addresses',
            'messages',
            'client_occupations',
            'documents',
            'emails',
            'mail_reports',
            'account_client_receipts',
            'tags',
            'matters',
            'checkin_logs',
            'client_visa_countries',
        ] as $t) {
            $stub($t);
        }

        if (! Schema::hasTable('workflow_stages')) {
            Schema::create('workflow_stages', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('application_document_lists')) {
            Schema::create('application_document_lists', function (Blueprint $table) {
                $table->id();
                $table->string('typename')->nullable();
                $table->string('type', 100)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'application_document_lists',
            'workflow_stages',
            'user_roles',
            'branches',
            'client_visa_countries',
            'checkin_logs',
            'matters',
            'tags',
            'client_matters',
            'notifications',
            'account_client_receipts',
            'mail_reports',
            'notes',
            'signers',
            'emails',
            'documents',
            'activities_logs',
            'client_occupations',
            'messages',
            'client_addresses',
            'client_eoi_references',
            'client_testscore',
            'client_experiences',
            'client_spouse_details',
            'client_qualifications',
        ] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
