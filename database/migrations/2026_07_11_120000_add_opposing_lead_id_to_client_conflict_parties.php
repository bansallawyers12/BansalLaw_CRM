<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_conflict_parties')) {
            return;
        }

        Schema::table('client_conflict_parties', function (Blueprint $table) {
            if (! Schema::hasColumn('client_conflict_parties', 'opposing_lead_id')) {
                $table->unsignedBigInteger('opposing_lead_id')->nullable()->after('client_id');
                $table->foreign('opposing_lead_id')
                    ->references('id')
                    ->on('admins')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('client_conflict_parties')) {
            return;
        }

        Schema::table('client_conflict_parties', function (Blueprint $table) {
            if (Schema::hasColumn('client_conflict_parties', 'opposing_lead_id')) {
                $table->dropForeign(['opposing_lead_id']);
                $table->dropColumn('opposing_lead_id');
            }
        });
    }
};
