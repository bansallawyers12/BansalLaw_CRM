<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Per-matter workflows: Add workflow_id to stages, matter_id to workflows,
     * workflow_id to client_matters and matters. Create General workflow and migrate existing data.
     */
    public function up(): void
    {
        // 0. Create workflows table if it doesn't exist
        if (!Schema::hasTable('workflows')) {
            Schema::create('workflows', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->unsignedBigInteger('matter_id')->nullable();
                $table->timestamps();
            });
        } elseif (!Schema::hasColumn('workflows', 'matter_id')) {
            Schema::table('workflows', function (Blueprint $table) {
                $table->unsignedBigInteger('matter_id')->nullable()->after('name');
            });
        }

        // 2. Add workflow_id to workflow_stages (only if table exists)
        if (Schema::hasTable('workflow_stages') && !Schema::hasColumn('workflow_stages', 'workflow_id')) {
            Schema::table('workflow_stages', function (Blueprint $table) {
                $table->unsignedBigInteger('workflow_id')->nullable()->after('name');
            });
        }

        // 3. Add workflow_id to client_matters
        if (Schema::hasTable('client_matters') && !Schema::hasColumn('client_matters', 'workflow_id')) {
            Schema::table('client_matters', function (Blueprint $table) {
                $table->unsignedBigInteger('workflow_id')->nullable()->after('workflow_stage_id');
            });
        }

        // 4. Add workflow_id to matters
        if (Schema::hasTable('matters') && !Schema::hasColumn('matters', 'workflow_id')) {
            Schema::table('matters', function (Blueprint $table) {
                $table->unsignedBigInteger('workflow_id')->nullable()->after('nick_name');
            });
        }

        // 5. Create General workflow if workflows table is empty or has no General
        $generalWorkflow = DB::table('workflows')->where('name', 'General')->first();
        if (!$generalWorkflow) {
            $generalId = DB::table('workflows')->insertGetId([
                'name' => 'General',
                'status' => 1,
                'matter_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $generalId = $generalWorkflow->id;
        }

        // 6. Assign all existing workflow_stages to General workflow
        if (Schema::hasTable('workflow_stages') && Schema::hasColumn('workflow_stages', 'workflow_id')) {
            DB::table('workflow_stages')->whereNull('workflow_id')->update(['workflow_id' => $generalId]);
        }

        // 7. Assign all existing client_matters to General workflow
        if (Schema::hasTable('client_matters') && Schema::hasColumn('client_matters', 'workflow_id')) {
            DB::table('client_matters')->whereNull('workflow_id')->update(['workflow_id' => $generalId]);
        }

    }

    public function down(): void
    {
        Schema::table('workflow_stages', function (Blueprint $table) {
            $table->dropColumn('workflow_id');
        });
        Schema::table('client_matters', function (Blueprint $table) {
            $table->dropColumn('workflow_id');
        });
        Schema::table('matters', function (Blueprint $table) {
            $table->dropColumn('workflow_id');
        });
        Schema::table('workflows', function (Blueprint $table) {
            $table->dropColumn('matter_id');
        });
    }
};
