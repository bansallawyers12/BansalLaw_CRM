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
        Schema::table('signers', function (Blueprint $table) {
            $table->string('email_template')->nullable()->after('reminder_count');
            $table->string('email_subject')->nullable()->after('email_template');
            $table->text('email_message')->nullable()->after('email_subject');
            $table->string('from_email')->nullable()->after('email_message');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('signers', function (Blueprint $table) {
            $table->dropColumn(['email_template', 'email_subject', 'email_message', 'from_email']);
        });
    }
};
