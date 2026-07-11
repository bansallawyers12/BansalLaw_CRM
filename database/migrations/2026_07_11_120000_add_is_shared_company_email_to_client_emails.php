<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_emails')) {
            return;
        }

        if (Schema::hasColumn('client_emails', 'is_shared_company_email')) {
            return;
        }

        Schema::table('client_emails', function (Blueprint $table) {
            $table->boolean('is_shared_company_email')->default(false)->after('email_type');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('client_emails') || ! Schema::hasColumn('client_emails', 'is_shared_company_email')) {
            return;
        }

        Schema::table('client_emails', function (Blueprint $table) {
            $table->dropColumn('is_shared_company_email');
        });
    }
};
