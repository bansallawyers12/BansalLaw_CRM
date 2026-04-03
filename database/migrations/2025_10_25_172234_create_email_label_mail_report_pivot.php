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
        Schema::create('email_label_mail_report', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mail_report_id'); // Manual FK
            $table->unsignedBigInteger('email_label_id'); // Manual FK
            $table->timestamps();
            
            // Unique constraint to prevent duplicate label assignments
            $table->unique(['mail_report_id', 'email_label_id'], 'mail_report_label_unique');
            
            // Indexes for performance
            $table->index('mail_report_id');
            $table->index('email_label_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_label_mail_report');
    }
};
