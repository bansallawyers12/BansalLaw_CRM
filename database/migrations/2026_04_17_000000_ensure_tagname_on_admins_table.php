<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Client/lead tags JSON on admins: {"n":["…"],"r":["…"]}.
     * Must run before 2026_04_18_000000_migrate_client_tags_to_json_and_drop_tags_table (which queries tagname).
     * The migration 2025_10_21_175052_add_tagname_column_to_admins_table originally shipped empty.
     */
    public function up(): void
    {
        if (! Schema::hasTable('admins') || Schema::hasColumn('admins', 'tagname')) {
            return;
        }

        Schema::table('admins', function (Blueprint $table) {
            $table->text('tagname')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('admins') || ! Schema::hasColumn('admins', 'tagname')) {
            return;
        }

        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn('tagname');
        });
    }
};
