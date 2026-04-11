<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Minimal / legacy `signers` tables (e.g. PostgreSQL) may omit recipient fields expected by
 * Signer model and SignatureAnalyticsService::getTopSigners() (email, name, …).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('signers')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            $this->addPgsqlColumnIfMissing('email', 'VARCHAR(255) NULL');
            $this->addPgsqlColumnIfMissing('name', 'VARCHAR(255) NULL');
            $this->addPgsqlColumnIfMissing('token', 'VARCHAR(64) NULL');
            $this->addPgsqlColumnIfMissing('status', 'VARCHAR(20) NULL');
            $this->addPgsqlColumnIfMissing('reminder_count', 'INTEGER NULL DEFAULT 0');
            $this->addPgsqlColumnIfMissing('opened_at', 'TIMESTAMP(0) WITHOUT TIME ZONE NULL');
            $this->addPgsqlColumnIfMissing('last_reminder_sent_at', 'TIMESTAMP(0) WITHOUT TIME ZONE NULL');
            $this->addPgsqlColumnIfMissing('email_template', 'VARCHAR(255) NULL');
            $this->addPgsqlColumnIfMissing('email_subject', 'VARCHAR(255) NULL');
            $this->addPgsqlColumnIfMissing('email_message', 'TEXT NULL');
            $this->addPgsqlColumnIfMissing('from_email', 'VARCHAR(255) NULL');

            return;
        }

        $add = function (callable $fn) {
            Schema::table('signers', $fn);
        };

        if (! Schema::hasColumn('signers', 'email')) {
            $add(fn (Blueprint $table) => $table->string('email')->nullable());
        }
        if (! Schema::hasColumn('signers', 'name')) {
            $add(fn (Blueprint $table) => $table->string('name')->nullable());
        }
        if (! Schema::hasColumn('signers', 'token')) {
            $add(fn (Blueprint $table) => $table->string('token', 64)->nullable());
        }
        if (! Schema::hasColumn('signers', 'status')) {
            $add(fn (Blueprint $table) => $table->string('status', 20)->nullable());
        }
        if (! Schema::hasColumn('signers', 'reminder_count')) {
            $add(fn (Blueprint $table) => $table->unsignedInteger('reminder_count')->nullable()->default(0));
        }
        if (! Schema::hasColumn('signers', 'opened_at')) {
            $add(fn (Blueprint $table) => $table->timestamp('opened_at')->nullable());
        }
        if (! Schema::hasColumn('signers', 'last_reminder_sent_at')) {
            $add(fn (Blueprint $table) => $table->timestamp('last_reminder_sent_at')->nullable());
        }
        if (! Schema::hasColumn('signers', 'email_template')) {
            $add(fn (Blueprint $table) => $table->string('email_template')->nullable());
        }
        if (! Schema::hasColumn('signers', 'email_subject')) {
            $add(fn (Blueprint $table) => $table->string('email_subject')->nullable());
        }
        if (! Schema::hasColumn('signers', 'email_message')) {
            $add(fn (Blueprint $table) => $table->text('email_message')->nullable());
        }
        if (! Schema::hasColumn('signers', 'from_email')) {
            $add(fn (Blueprint $table) => $table->string('from_email')->nullable());
        }
    }

    private function addPgsqlColumnIfMissing(string $column, string $sqlType): void
    {
        if (Schema::hasColumn('signers', $column)) {
            return;
        }

        $exists = DB::selectOne(
            'select 1 as x from information_schema.columns
             where table_schema = current_schema() and table_name = ? and column_name = ?
             limit 1',
            ['signers', $column]
        );
        if ($exists) {
            return;
        }

        DB::statement('ALTER TABLE signers ADD COLUMN '.$column.' '.$sqlType);
    }

    public function down(): void
    {
        // Non-destructive “ensure” migration — no down.
    }
};
