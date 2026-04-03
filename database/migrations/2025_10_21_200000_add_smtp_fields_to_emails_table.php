<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            if (!Schema::hasColumn('emails', 'smtp_host')) {
                $table->string('smtp_host')->nullable()->after('display_name')->default('smtp.zoho.com');
            }
            if (!Schema::hasColumn('emails', 'smtp_port')) {
                $table->integer('smtp_port')->nullable()->after('smtp_host')->default(587);
            }
            if (!Schema::hasColumn('emails', 'smtp_encryption')) {
                $table->string('smtp_encryption', 10)->nullable()->after('smtp_port')->default('tls');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            if (Schema::hasColumn('emails', 'smtp_encryption')) {
                $table->dropColumn('smtp_encryption');
            }
            if (Schema::hasColumn('emails', 'smtp_port')) {
                $table->dropColumn('smtp_port');
            }
            if (Schema::hasColumn('emails', 'smtp_host')) {
                $table->dropColumn('smtp_host');
            }
        });
    }
};

