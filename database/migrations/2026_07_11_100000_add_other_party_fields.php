<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('admins') && ! Schema::hasColumn('admins', 'is_other_party')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->boolean('is_other_party')->default(false)->after('is_company');
                $table->index('is_other_party');
            });
        }

        if (Schema::hasTable('client_matter_opposing_parties')) {
            Schema::table('client_matter_opposing_parties', function (Blueprint $table) {
                if (! Schema::hasColumn('client_matter_opposing_parties', 'opposing_lead_id')) {
                    $table->unsignedBigInteger('opposing_lead_id')->nullable()->after('client_matter_id');
                    $table->foreign('opposing_lead_id')
                        ->references('id')
                        ->on('admins')
                        ->nullOnDelete();
                }
                if (! Schema::hasColumn('client_matter_opposing_parties', 'rep_firm')) {
                    $table->string('rep_firm', 255)->nullable()->after('party_role');
                }
                if (! Schema::hasColumn('client_matter_opposing_parties', 'rep_name')) {
                    $table->string('rep_name', 255)->nullable()->after('rep_firm');
                }
                if (! Schema::hasColumn('client_matter_opposing_parties', 'rep_email')) {
                    $table->string('rep_email', 255)->nullable()->after('rep_name');
                }
                if (! Schema::hasColumn('client_matter_opposing_parties', 'rep_phone')) {
                    $table->string('rep_phone', 64)->nullable()->after('rep_email');
                }
                if (! Schema::hasColumn('client_matter_opposing_parties', 'rep_notes')) {
                    $table->text('rep_notes')->nullable()->after('rep_phone');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('client_matter_opposing_parties')) {
            Schema::table('client_matter_opposing_parties', function (Blueprint $table) {
                if (Schema::hasColumn('client_matter_opposing_parties', 'opposing_lead_id')) {
                    $table->dropForeign(['opposing_lead_id']);
                    $table->dropColumn('opposing_lead_id');
                }
                foreach (['rep_firm', 'rep_name', 'rep_email', 'rep_phone', 'rep_notes'] as $col) {
                    if (Schema::hasColumn('client_matter_opposing_parties', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('admins') && Schema::hasColumn('admins', 'is_other_party')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->dropIndex(['is_other_party']);
                $table->dropColumn('is_other_party');
            });
        }
    }
};
