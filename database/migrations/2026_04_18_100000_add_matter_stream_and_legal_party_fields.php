<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('matters') && ! Schema::hasColumn('matters', 'stream')) {
            Schema::table('matters', function (Blueprint $table) {
                $table->string('stream', 64)->nullable()->after('nick_name');
            });
        }

        if (Schema::hasTable('client_matters') && ! Schema::hasColumn('client_matters', 'our_party_role')) {
            Schema::table('client_matters', function (Blueprint $table) {
                $table->string('our_party_role', 64)->nullable()->after('incidence_type');
            });
        }

        if (! Schema::hasTable('client_matter_opposing_parties')) {
            Schema::create('client_matter_opposing_parties', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_matter_id');
                $table->string('name', 500);
                $table->string('party_role', 255)->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->foreign('client_matter_id')
                    ->references('id')
                    ->on('client_matters')
                    ->onDelete('cascade');

                $table->index(['client_matter_id', 'sort_order']);
            });
        }

        $map = [
            1 => 'civil_vic',
            2 => 'criminal',
            3 => 'family',
            4 => 'property',
            5 => 'corporate',
            6 => 'employment_fwc',
            7 => 'consumer',
            8 => 'banking',
            9 => 'taxation',
            10 => 'ip',
            11 => 'constitutional',
            12 => 'revenue',
            13 => 'motor_accident',
            14 => 'migration_merits',
            15 => 'judicial_review',
            16 => 'migration_merits',
        ];

        if (Schema::hasColumn('matters', 'stream')) {
            foreach ($map as $id => $stream) {
                DB::table('matters')->where('id', $id)->whereNull('stream')->update(['stream' => $stream]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('client_matter_opposing_parties')) {
            Schema::dropIfExists('client_matter_opposing_parties');
        }

        if (Schema::hasTable('client_matters') && Schema::hasColumn('client_matters', 'our_party_role')) {
            Schema::table('client_matters', function (Blueprint $table) {
                $table->dropColumn('our_party_role');
            });
        }

        if (Schema::hasTable('matters') && Schema::hasColumn('matters', 'stream')) {
            Schema::table('matters', function (Blueprint $table) {
                $table->dropColumn('stream');
            });
        }
    }
};
