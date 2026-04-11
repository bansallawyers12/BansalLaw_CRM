<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Primary email type for clients/leads on admins (e.g. Personal, Work).
     * client_emails is canonical for multiple addresses; this column backs legacy paths.
     */
    public function up(): void
    {
        if (! Schema::hasTable('admins') || Schema::hasColumn('admins', 'email_type')) {
            return;
        }

        Schema::table('admins', function (Blueprint $table) {
            $table->string('email_type')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('admins') || ! Schema::hasColumn('admins', 'email_type')) {
            return;
        }

        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn('email_type');
        });
    }
};
