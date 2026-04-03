<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            // Add tag_type column: 'normal' or 'red'
            if (!Schema::hasColumn('tags', 'tag_type')) {
                $table->string('tag_type', 20)->default('normal');
            }
            
            // Add is_hidden column: true for red tags, false for normal tags
            if (!Schema::hasColumn('tags', 'is_hidden')) {
                $table->boolean('is_hidden')->default(false);
            }
            
            // Add index for filtering by tag type
            if (!$this->hasIndex('tags', 'tags_tag_type_index')) {
                $table->index('tag_type', 'tags_tag_type_index');
            }
        });
        
        // Update existing tags to be normal type
        DB::table('tags')->whereNull('tag_type')->update(['tag_type' => 'normal', 'is_hidden' => false]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            if (Schema::hasColumn('tags', 'tag_type')) {
                $table->dropIndex('tags_tag_type_index');
                $table->dropColumn('tag_type');
            }
            
            if (Schema::hasColumn('tags', 'is_hidden')) {
                $table->dropColumn('is_hidden');
            }
        });
    }
    
    /**
     * Check if an index exists on a table (PostgreSQL compatible)
     */
    private function hasIndex($table, $indexName): bool
    {
        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();
        
        $result = $connection->select(
            "SELECT COUNT(*) as count 
             FROM pg_indexes 
             WHERE schemaname = ? 
             AND tablename = ? 
             AND indexname = ?",
            [$connection->getConfig('schema') ?? 'public', $table, $indexName]
        );
        
        return $result[0]->count > 0;
    }
};

