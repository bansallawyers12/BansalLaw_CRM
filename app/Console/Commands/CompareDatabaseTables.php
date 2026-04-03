<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CompareDatabaseTables extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:compare-tables 
                            {--mysql-conn=mysql_source : MySQL connection name}
                            {--pgsql-conn=pgsql : PostgreSQL connection name}
                            {--output= : Output file path (optional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compare MySQL and PostgreSQL database tables to identify differences';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $mysqlConn = $this->option('mysql-conn');
        $pgsqlConn = $this->option('pgsql-conn');
        $outputFile = $this->option('output') ?: __DIR__ . '/../../../table_comparison_report.txt';

        $this->info('=== Database Table Comparison ===');
        $this->newLine();

        // Get PostgreSQL tables
        try {
            $this->info("Connecting to PostgreSQL ({$pgsqlConn})...");
            $pgsqlTables = DB::connection($pgsqlConn)
                ->select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name");
            $pgsqlTableNames = array_map(function($t) {
                return $t->table_name;
            }, $pgsqlTables);
            sort($pgsqlTableNames);
            $this->info("✓ Found " . count($pgsqlTableNames) . " tables in PostgreSQL");
        } catch (\Exception $e) {
            $this->error("✗ Error connecting to PostgreSQL: " . $e->getMessage());
            return 1;
        }

        // Get MySQL tables
        try {
            $this->info("Connecting to MySQL ({$mysqlConn})...");
            $mysqlTables = DB::connection($mysqlConn)
                ->select("SHOW TABLES");
            
            // MySQL returns tables with dynamic column name like "Tables_in_database_name"
            $mysqlTableNames = [];
            if (!empty($mysqlTables)) {
                $firstTable = (array)$mysqlTables[0];
                $columnName = array_keys($firstTable)[0];
                $mysqlTableNames = array_map(function($t) use ($columnName) {
                    return ((array)$t)[$columnName];
                }, $mysqlTables);
            }
            sort($mysqlTableNames);
            $this->info("✓ Found " . count($mysqlTableNames) . " tables in MySQL");
        } catch (\Exception $e) {
            $this->error("✗ Error connecting to MySQL: " . $e->getMessage());
            $this->warn("Please check your MYSQL_SOURCE_* environment variables in .env file");
            return 1;
        }

        $this->newLine();

        // Find differences
        $onlyInPostgreSQL = array_diff($pgsqlTableNames, $mysqlTableNames);
        $onlyInMySQL = array_diff($mysqlTableNames, $pgsqlTableNames);
        $commonTables = array_intersect($pgsqlTableNames, $mysqlTableNames);

        // Display results
        $this->info('=== COMPARISON RESULTS ===');
        $this->newLine();

        $this->warn("Tables ONLY in PostgreSQL (" . count($onlyInPostgreSQL) . "):");
        $this->warn("(Should consider removing from PostgreSQL)");
        if (empty($onlyInPostgreSQL)) {
            $this->line("  None");
        } else {
            foreach ($onlyInPostgreSQL as $table) {
                $this->line("  - {$table}");
            }
        }

        $this->newLine();

        $this->info("Tables ONLY in MySQL (" . count($onlyInMySQL) . "):");
        $this->info("(Should add to PostgreSQL)");
        if (empty($onlyInMySQL)) {
            $this->line("  None");
        } else {
            foreach ($onlyInMySQL as $table) {
                $this->line("  - {$table}");
            }
        }

        $this->newLine();
        $this->line("Common Tables (in both databases): " . count($commonTables));

        // Generate report file
        $output = "=== DATABASE TABLE COMPARISON REPORT ===\n\n";
        $output .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";
        $output .= "PostgreSQL Connection: {$pgsqlConn}\n";
        $output .= "MySQL Connection: {$mysqlConn}\n\n";
        $output .= "PostgreSQL Tables: " . count($pgsqlTableNames) . "\n";
        $output .= "MySQL Tables: " . count($mysqlTableNames) . "\n";
        $output .= "Common Tables: " . count($commonTables) . "\n\n";
        
        $output .= "=== TABLES TO REMOVE FROM POSTGRESQL ===\n";
        $output .= "(Tables that exist in PostgreSQL but NOT in MySQL)\n";
        $output .= "Total: " . count($onlyInPostgreSQL) . " tables\n\n";
        if (empty($onlyInPostgreSQL)) {
            $output .= "None\n";
        } else {
            foreach ($onlyInPostgreSQL as $table) {
                $output .= "  - {$table}\n";
            }
        }
        
        $output .= "\n=== TABLES TO ADD TO POSTGRESQL ===\n";
        $output .= "(Tables that exist in MySQL but NOT in PostgreSQL)\n";
        $output .= "Total: " . count($onlyInMySQL) . " tables\n\n";
        if (empty($onlyInMySQL)) {
            $output .= "None\n";
        } else {
            foreach ($onlyInMySQL as $table) {
                $output .= "  - {$table}\n";
            }
        }

        // Add detailed lists
        $output .= "\n\n=== DETAILED LISTS ===\n\n";
        
        $output .= "--- All PostgreSQL Tables ---\n";
        foreach ($pgsqlTableNames as $table) {
            $output .= "  - {$table}\n";
        }
        
        $output .= "\n--- All MySQL Tables ---\n";
        foreach ($mysqlTableNames as $table) {
            $output .= "  - {$table}\n";
        }

        file_put_contents($outputFile, $output);
        $this->newLine();
        $this->info("✓ Report saved to: {$outputFile}");

        return 0;
    }
}
