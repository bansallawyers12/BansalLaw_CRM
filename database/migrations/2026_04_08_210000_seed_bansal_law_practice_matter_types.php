<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inserts Bansal Law practice-area matter types for dropdowns (client edit, matter list, etc.).
 * Idempotent: skips any row whose title already exists.
 */
return new class extends Migration
{
    private function matterRows(): array
    {
        return [
            ['title' => 'Civil Law', 'nick_name' => 'CIV', 'is_for_company' => false],
            ['title' => 'Criminal Law', 'nick_name' => 'CRM', 'is_for_company' => false],
            ['title' => 'Family Law', 'nick_name' => 'FAM', 'is_for_company' => false],
            ['title' => 'Property & Real Estate', 'nick_name' => 'PROP', 'is_for_company' => false],
            ['title' => 'Corporate & Business Law', 'nick_name' => 'CORP', 'is_for_company' => true],
            ['title' => 'Labour & Employment', 'nick_name' => 'LAB', 'is_for_company' => false],
            ['title' => 'Consumer Law', 'nick_name' => 'CONS', 'is_for_company' => false],
            ['title' => 'Banking & Finance', 'nick_name' => 'BANK', 'is_for_company' => false],
            ['title' => 'Taxation', 'nick_name' => 'TAX', 'is_for_company' => false],
            ['title' => 'Intellectual Property', 'nick_name' => 'IP', 'is_for_company' => false],
            ['title' => 'Constitutional & Writ', 'nick_name' => 'CONST', 'is_for_company' => false],
            ['title' => 'Revenue & Land', 'nick_name' => 'REV', 'is_for_company' => false],
            ['title' => 'Motor Accident (MACT)', 'nick_name' => 'MACT', 'is_for_company' => false],
            ['title' => 'Merits Review', 'nick_name' => 'MERITS', 'is_for_company' => false],
            ['title' => 'Judicial Review', 'nick_name' => 'JR', 'is_for_company' => false],
            ['title' => 'Notice of intention to consider cancellation', 'nick_name' => 'NOICC', 'is_for_company' => false],
            ['title' => 'Immigration matter', 'nick_name' => 'IMM', 'is_for_company' => false],
        ];
    }

    public function up(): void
    {
        if (! Schema::hasTable('matters')) {
            return;
        }

        $workflowId = null;
        if (Schema::hasTable('workflows') && Schema::hasColumn('matters', 'workflow_id')) {
            $workflowId = DB::table('workflows')->where('name', 'General')->value('id');
        }

        foreach ($this->matterRows() as $row) {
            if (DB::table('matters')->where('title', $row['title'])->exists()) {
                continue;
            }

            $insert = [
                'title' => $row['title'],
                'nick_name' => $row['nick_name'],
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('matters', 'status')) {
                $insert['status'] = 1;
            }
            if ($workflowId !== null && Schema::hasColumn('matters', 'workflow_id')) {
                $insert['workflow_id'] = $workflowId;
            }
            if (Schema::hasColumn('matters', 'is_for_company')) {
                $insert['is_for_company'] = (bool) ($row['is_for_company'] ?? false);
            }

            DB::table('matters')->insert($insert);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('matters')) {
            return;
        }

        $titles = array_column($this->matterRows(), 'title');
        DB::table('matters')->whereIn('title', $titles)->delete();
    }
};
