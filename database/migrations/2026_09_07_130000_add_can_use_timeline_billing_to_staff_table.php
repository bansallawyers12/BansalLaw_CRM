<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('staff')) {
            return;
        }

        Schema::table('staff', function (Blueprint $table) {
            if (! Schema::hasColumn('staff', 'can_use_timeline_billing')) {
                $column = $table->boolean('can_use_timeline_billing')->default(false);
                if (Schema::hasColumn('staff', 'can_assign_emails_by_subject')) {
                    $column->after('can_assign_emails_by_subject');
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('staff')) {
            return;
        }

        Schema::table('staff', function (Blueprint $table) {
            if (Schema::hasColumn('staff', 'can_use_timeline_billing')) {
                $table->dropColumn('can_use_timeline_billing');
            }
        });
    }
};
