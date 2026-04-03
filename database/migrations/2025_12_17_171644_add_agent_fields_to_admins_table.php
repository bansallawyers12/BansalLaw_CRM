<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds missing migration agent fields to the admins table
     * (only adds columns that don't already exist)
     */
    public function up(): void
    {
        // Check column existence OUTSIDE the Schema::table closure
        $hasIsMigrationAgent = Schema::hasColumn('admins', 'is_migration_agent');
        $hasMarnNumber = Schema::hasColumn('admins', 'marn_number');
        $hasBusinessFax = Schema::hasColumn('admins', 'business_fax');
        $hasCompanyName = Schema::hasColumn('admins', 'company_name');
        $hasBusinessAddress = Schema::hasColumn('admins', 'business_address');
        $hasBusinessPhone = Schema::hasColumn('admins', 'business_phone');
        $hasBusinessMobile = Schema::hasColumn('admins', 'business_mobile');
        $hasBusinessEmail = Schema::hasColumn('admins', 'business_email');
        $hasTaxNumber = Schema::hasColumn('admins', 'tax_number');
        
        Schema::table('admins', function (Blueprint $table) use ($hasBusinessFax, $hasIsMigrationAgent, $hasMarnNumber, $hasCompanyName, $hasBusinessAddress, $hasBusinessPhone, $hasBusinessMobile, $hasBusinessEmail, $hasTaxNumber) {
            // Add core migration agent fields first
            if (!$hasIsMigrationAgent) {
                $table->tinyInteger('is_migration_agent')->default(0)->nullable()
                    ->comment('Flag to indicate if user is a migration agent');
            }
            
            if (!$hasMarnNumber) {
                $table->string('marn_number')->nullable()
                    ->comment('Migration Agent Registration Number');
            }
            
            // Add business name (company name)
            if (!$hasCompanyName) {
                if ($hasMarnNumber) {
                    $table->string('company_name')->nullable()->after('marn_number')
                        ->comment('Business/Company Name');
                } else {
                    $table->string('company_name')->nullable()
                        ->comment('Business/Company Name');
                }
            }
            
            // Add business-related fields
            // Determine position based on business_fax existence
            if (!$hasBusinessAddress) {
                if ($hasBusinessFax) {
                    $table->text('business_address')->nullable()->after('business_fax');
                } else {
                    $table->text('business_address')->nullable();
                }
            }
            
            if (!$hasBusinessPhone) {
                if ($hasBusinessFax) {
                    $table->string('business_phone')->nullable()->after('business_fax');
                } else {
                    $table->string('business_phone')->nullable();
                }
            }
            
            if (!$hasBusinessMobile) {
                if ($hasBusinessFax) {
                    $table->string('business_mobile')->nullable()->after('business_fax');
                } else {
                    $table->string('business_mobile')->nullable();
                }
            }
            
            if (!$hasBusinessEmail) {
                if ($hasBusinessFax) {
                    $table->string('business_email')->nullable()->after('business_fax');
                } else {
                    $table->string('business_email')->nullable();
                }
            }
            
            if (!$hasTaxNumber) {
                if ($hasBusinessFax) {
                    $table->string('tax_number')->nullable()->after('business_fax');
                } else {
                    $table->string('tax_number')->nullable();
                }
            }
        });
        
        // Re-check column existence after creation for index creation
        $hasIsMigrationAgentAfter = Schema::hasColumn('admins', 'is_migration_agent');
        $hasMarnNumberAfter = Schema::hasColumn('admins', 'marn_number');
        
        // Add indexes separately, outside the Schema::table closure
        // Only create indexes if the columns actually exist
        if ($hasIsMigrationAgentAfter && !$this->indexExists('admins', 'admins_is_migration_agent_index')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->index('is_migration_agent');
            });
        }
        
        if ($hasMarnNumberAfter && !$this->indexExists('admins', 'admins_marn_number_index')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->index('marn_number');
            });
        }
        
        echo "\n✅ Migration completed successfully!\n";
        echo "   - Added missing migration agent fields to admins table\n";
        $addedFields = [];
        if (!$hasIsMigrationAgent) $addedFields[] = 'is_migration_agent';
        if (!$hasMarnNumber) $addedFields[] = 'marn_number';
        if (!$hasCompanyName) $addedFields[] = 'company_name';
        if (!$hasBusinessAddress) $addedFields[] = 'business_address';
        if (!$hasBusinessPhone) $addedFields[] = 'business_phone';
        if (!$hasBusinessMobile) $addedFields[] = 'business_mobile';
        if (!$hasBusinessEmail) $addedFields[] = 'business_email';
        if (!$hasTaxNumber) $addedFields[] = 'tax_number';
        
        if (!empty($addedFields)) {
            echo "   - Created columns: " . implode(', ', $addedFields) . "\n";
        } else {
            echo "   - All columns already exist\n";
        }
        
        if ($hasIsMigrationAgentAfter) {
            if (!$this->indexExists('admins', 'admins_is_migration_agent_index')) {
                echo "   - Index created on is_migration_agent\n";
            } else {
                echo "   - Index already exists on is_migration_agent\n";
            }
        }
        
        if ($hasMarnNumberAfter) {
            if (!$this->indexExists('admins', 'admins_marn_number_index')) {
                echo "   - Index created on marn_number\n";
            } else {
                echo "   - Index already exists on marn_number\n";
            }
        }
        echo "\n";
    }

    /**
     * Check if an index exists (works with both MySQL and PostgreSQL)
     */
    private function indexExists($table, $indexName)
    {
        try {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexesFound = $sm->listTableIndexes($table);
            
            foreach ($indexesFound as $index) {
                if ($index->getName() === $indexName) {
                    return true;
                }
            }
            return false;
        } catch (\Exception $e) {
            // If we can't check, assume it doesn't exist to be safe
            return false;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes first if they exist
        if ($this->indexExists('admins', 'admins_is_migration_agent_index')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->dropIndex('admins_is_migration_agent_index');
            });
        }
        
        if ($this->indexExists('admins', 'admins_marn_number_index')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->dropIndex('admins_marn_number_index');
            });
        }
        
        Schema::table('admins', function (Blueprint $table) {
            // Drop all columns that we added
            $columnsToDrop = [];
            
            if (Schema::hasColumn('admins', 'is_migration_agent')) {
                $columnsToDrop[] = 'is_migration_agent';
            }
            if (Schema::hasColumn('admins', 'marn_number')) {
                $columnsToDrop[] = 'marn_number';
            }
            if (Schema::hasColumn('admins', 'company_name')) {
                $columnsToDrop[] = 'company_name';
            }
            if (Schema::hasColumn('admins', 'business_address')) {
                $columnsToDrop[] = 'business_address';
            }
            if (Schema::hasColumn('admins', 'business_phone')) {
                $columnsToDrop[] = 'business_phone';
            }
            if (Schema::hasColumn('admins', 'business_mobile')) {
                $columnsToDrop[] = 'business_mobile';
            }
            if (Schema::hasColumn('admins', 'business_email')) {
                $columnsToDrop[] = 'business_email';
            }
            if (Schema::hasColumn('admins', 'tax_number')) {
                $columnsToDrop[] = 'tax_number';
            }
            
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
        
        echo "\n✅ Rollback completed - migration agent fields removed\n";
        echo "   - Dropped columns: is_migration_agent, marn_number, company_name, business_address, business_phone, business_mobile, business_email, tax_number\n";
        echo "   - Dropped indexes on is_migration_agent and marn_number\n\n";
    }
};
