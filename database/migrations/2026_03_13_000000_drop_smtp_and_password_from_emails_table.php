<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Remove SMTP and password columns from emails table (SendGrid migration cleanup).
     */
    public function up(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $columns = ['smtp_host', 'smtp_port', 'smtp_encryption', 'password'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('emails', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            if (!Schema::hasColumn('emails', 'smtp_host')) {
                $table->string('smtp_host')->nullable()->after('display_name');
            }
            if (!Schema::hasColumn('emails', 'smtp_port')) {
                $table->integer('smtp_port')->nullable()->after('smtp_host');
            }
            if (!Schema::hasColumn('emails', 'smtp_encryption')) {
                $table->string('smtp_encryption', 10)->nullable()->after('smtp_port');
            }
            if (!Schema::hasColumn('emails', 'password')) {
                $table->string('password')->nullable()->after('display_name');
            }
        });
    }
};
