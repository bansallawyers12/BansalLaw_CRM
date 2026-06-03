<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('emails')) {
            return;
        }

        Schema::table('emails', function (Blueprint $table) {
            if (! Schema::hasColumn('emails', 'mail_provider')) {
                $table->string('mail_provider', 20)->default('zoho')->after('display_name');
            }
            if (! Schema::hasColumn('emails', 'smtp_host')) {
                $table->string('smtp_host')->nullable()->after('mail_provider')->default('smtp.zoho.com');
            }
            if (! Schema::hasColumn('emails', 'smtp_port')) {
                $table->unsignedSmallInteger('smtp_port')->nullable()->after('smtp_host')->default(587);
            }
            if (! Schema::hasColumn('emails', 'smtp_encryption')) {
                $table->string('smtp_encryption', 10)->nullable()->after('smtp_port')->default('tls');
            }
            if (! Schema::hasColumn('emails', 'password')) {
                $table->text('password')->nullable()->after('smtp_encryption');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('emails')) {
            return;
        }

        Schema::table('emails', function (Blueprint $table) {
            foreach (['mail_provider', 'smtp_host', 'smtp_port', 'smtp_encryption', 'password'] as $col) {
                if (Schema::hasColumn('emails', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
