<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Production PostgreSQL schemas (and older stubs) omitted columns the Note model
     * and Action/DataTables flows require (user_id creator, body text, grouping, etc.).
     */
    public function up(): void
    {
        if (! Schema::hasTable('notes')) {
            return;
        }

        if (! Schema::hasColumn('notes', 'user_id')) {
            Schema::table('notes', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->index();
            });
        }
        if (! Schema::hasColumn('notes', 'lead_id')) {
            Schema::table('notes', function (Blueprint $table) {
                $table->unsignedBigInteger('lead_id')->nullable()->index();
            });
        }
        if (! Schema::hasColumn('notes', 'unique_group_id')) {
            Schema::table('notes', function (Blueprint $table) {
                $table->string('unique_group_id', 191)->nullable()->index();
            });
        }
        if (! Schema::hasColumn('notes', 'title')) {
            Schema::table('notes', function (Blueprint $table) {
                $table->string('title', 512)->nullable();
            });
        }
        if (! Schema::hasColumn('notes', 'description')) {
            Schema::table('notes', function (Blueprint $table) {
                $table->text('description')->nullable();
            });
        }
        if (! Schema::hasColumn('notes', 'note_deadline')) {
            Schema::table('notes', function (Blueprint $table) {
                $table->timestamp('note_deadline')->nullable();
            });
        }
        if (! Schema::hasColumn('notes', 'mail_id')) {
            Schema::table('notes', function (Blueprint $table) {
                $table->unsignedBigInteger('mail_id')->nullable()->index();
            });
        }
        if (! Schema::hasColumn('notes', 'pin')) {
            Schema::table('notes', function (Blueprint $table) {
                $table->tinyInteger('pin')->nullable()->default(0);
            });
        }
        if (! Schema::hasColumn('notes', 'matter_id')) {
            Schema::table('notes', function (Blueprint $table) {
                $table->unsignedBigInteger('matter_id')->nullable()->index();
            });
        }
        if (! Schema::hasColumn('notes', 'mobile_number')) {
            Schema::table('notes', function (Blueprint $table) {
                $table->string('mobile_number', 64)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('notes')) {
            return;
        }

        $cols = [
            'user_id',
            'lead_id',
            'unique_group_id',
            'title',
            'description',
            'note_deadline',
            'mail_id',
            'pin',
            'matter_id',
            'mobile_number',
        ];

        $toDrop = array_values(array_filter($cols, fn ($c) => Schema::hasColumn('notes', $c)));
        if ($toDrop === []) {
            return;
        }

        Schema::table('notes', function (Blueprint $table) use ($toDrop) {
            $table->dropColumn($toDrop);
        });
    }
};
