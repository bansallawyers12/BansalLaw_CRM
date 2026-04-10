<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fresh/stub `activities_logs` (see create_legacy_stub_tables) only had id, client_id, description, timestamps.
 * CRM code expects created_by, subject, activity_type, task_status, pin, etc.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activities_logs')) {
            return;
        }

        if (! Schema::hasColumn('activities_logs', 'created_by')) {
            Schema::table('activities_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('created_by')->nullable()->index();
            });
        }

        if (! Schema::hasColumn('activities_logs', 'subject')) {
            Schema::table('activities_logs', function (Blueprint $table) {
                $table->text('subject')->nullable();
            });
        }

        if (! Schema::hasColumn('activities_logs', 'task_status')) {
            Schema::table('activities_logs', function (Blueprint $table) {
                $table->unsignedTinyInteger('task_status')->default(0);
            });
        }

        if (! Schema::hasColumn('activities_logs', 'pin')) {
            Schema::table('activities_logs', function (Blueprint $table) {
                $table->unsignedTinyInteger('pin')->default(0);
            });
        }

        if (! Schema::hasColumn('activities_logs', 'sms_log_id')) {
            Schema::table('activities_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('sms_log_id')->nullable()->index();
            });
        }

        if (! Schema::hasColumn('activities_logs', 'activity_type')) {
            Schema::table('activities_logs', function (Blueprint $table) {
                $table->string('activity_type', 64)->default('note');
            });
        }

        if (! Schema::hasColumn('activities_logs', 'source')) {
            Schema::table('activities_logs', function (Blueprint $table) {
                $table->string('source', 50)->nullable();
            });
            try {
                Schema::table('activities_logs', function (Blueprint $table) {
                    $table->index(['client_id', 'source']);
                });
            } catch (\Throwable) {
                //
            }
        }

        if (! Schema::hasColumn('activities_logs', 'use_for')) {
            Schema::table('activities_logs', function (Blueprint $table) {
                $table->string('use_for', 64)->nullable();
            });
        }

        if (! Schema::hasColumn('activities_logs', 'followup_date')) {
            Schema::table('activities_logs', function (Blueprint $table) {
                $table->timestamp('followup_date')->nullable();
            });
        }

        if (! Schema::hasColumn('activities_logs', 'task_group')) {
            Schema::table('activities_logs', function (Blueprint $table) {
                $table->string('task_group', 128)->nullable();
            });
        }
    }

    public function down(): void
    {
        // Non-destructive: columns may also be used by earlier/later migrations.
    }
};
