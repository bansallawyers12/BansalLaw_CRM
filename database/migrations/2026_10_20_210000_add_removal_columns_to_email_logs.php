<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_logs')) {
            return;
        }

        Schema::table('email_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('email_logs', 'removed_at')) {
                $table->timestamp('removed_at')->nullable()->index();
            }
            if (! Schema::hasColumn('email_logs', 'removed_by')) {
                $table->unsignedBigInteger('removed_by')->nullable()->index();
            }
            if (! Schema::hasColumn('email_logs', 'removal_reason')) {
                $table->string('removal_reason', 32)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('email_logs')) {
            return;
        }

        Schema::table('email_logs', function (Blueprint $table) {
            foreach (['removed_at', 'removed_by', 'removal_reason'] as $column) {
                if (Schema::hasColumn('email_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
