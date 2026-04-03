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
        Schema::table('mail_reports', function (Blueprint $table) {
            // Python Analysis Fields
            $table->json('python_analysis')->nullable();
            $table->json('python_rendering')->nullable();
            
            // AI Categorization
            $table->string('category')->nullable()->index();
            $table->enum('priority', ['low', 'medium', 'high'])->default('low')->index();
            $table->enum('sentiment', ['positive', 'neutral', 'negative'])->default('neutral');
            $table->string('language')->nullable();
            
            // Enhanced Content
            $table->longText('enhanced_html')->nullable();
            $table->longText('rendered_html')->nullable();
            $table->text('text_preview')->nullable();
            
            // Security & Threading
            $table->json('security_issues')->nullable();
            $table->json('thread_info')->nullable();
            
            // Metadata
            $table->string('message_id')->nullable()->index();
            $table->string('thread_id')->nullable()->index();
            $table->timestamp('received_date')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->string('file_hash')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mail_reports', function (Blueprint $table) {
            $table->dropColumn([
                'python_analysis',
                'python_rendering',
                'category',
                'priority',
                'sentiment',
                'language',
                'enhanced_html',
                'rendered_html',
                'text_preview',
                'security_issues',
                'thread_info',
                'message_id',
                'thread_id',
                'received_date',
                'processed_at',
                'last_accessed_at',
                'file_hash'
            ]);
        });
    }
};
