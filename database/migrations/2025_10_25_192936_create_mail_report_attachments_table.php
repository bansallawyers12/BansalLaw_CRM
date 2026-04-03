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
        Schema::create('mail_report_attachments', function (Blueprint $table) {
            $table->id();
            // Use unsignedInteger to match mail_reports.id which is int(11)
            $table->unsignedInteger('mail_report_id');
            $table->string('filename');
            $table->string('display_name')->nullable();
            $table->string('content_type')->nullable();
            $table->string('file_path', 500)->nullable(); // S3 URLs can be long
            $table->string('s3_key', 500)->nullable(); // S3 keys can be long
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('content_id')->nullable();
            $table->boolean('is_inline')->default(false);
            $table->string('description')->nullable();
            $table->json('headers')->nullable();
            $table->string('extension', 10)->nullable();
            $table->timestamps();
            
            // NOTE: Skipping foreign key constraint due to charset/engine mismatch issues
            // The relationship is defined in the model, which is sufficient for Laravel
            
            // Indexes for performance
            $table->index(['mail_report_id']);
            $table->index(['is_inline']);
            $table->index(['created_at']);
        });
        
        // PostgreSQL doesn't use charset/collation like MySQL
        // This conversion is only needed for MySQL
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE mail_report_attachments CONVERT TO CHARACTER SET latin1 COLLATE latin1_swedish_ci');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_report_attachments');
    }
};
