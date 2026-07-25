<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('companies')) {
            return;
        }

        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'solicitor_id')) {
                $table->unsignedBigInteger('solicitor_id')->nullable()->after('contact_person_position');
                $table->index('solicitor_id');
            }
            if (! Schema::hasColumn('companies', 'solicitor_position')) {
                $table->string('solicitor_position', 255)->nullable()->after('solicitor_id');
            }
        });

        if (Schema::hasColumn('companies', 'solicitor_id')) {
            try {
                Schema::table('companies', function (Blueprint $table) {
                    $table->foreign('solicitor_id')->references('id')->on('admins')->nullOnDelete();
                });
            } catch (\Throwable) {
                // FK may already exist on some environments.
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('companies')) {
            return;
        }

        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'solicitor_id')) {
                try {
                    $table->dropForeign(['solicitor_id']);
                } catch (\Throwable) {
                    // ignore
                }
                $table->dropIndex(['solicitor_id']);
                $table->dropColumn('solicitor_id');
            }
            if (Schema::hasColumn('companies', 'solicitor_position')) {
                $table->dropColumn('solicitor_position');
            }
        });
    }
};
