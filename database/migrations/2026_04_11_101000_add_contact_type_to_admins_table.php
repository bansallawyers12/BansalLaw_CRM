<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Primary phone contact type for clients/leads on admins (e.g. Personal, Mobile).
     * client_contacts is canonical for multiple numbers; this column backs legacy paths.
     */
    public function up(): void
    {
        if (! Schema::hasTable('admins') || Schema::hasColumn('admins', 'contact_type')) {
            return;
        }

        Schema::table('admins', function (Blueprint $table) {
            $table->string('contact_type')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('admins') || ! Schema::hasColumn('admins', 'contact_type')) {
            return;
        }

        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn('contact_type');
        });
    }
};
