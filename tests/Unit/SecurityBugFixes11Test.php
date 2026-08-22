<?php

namespace Tests\Unit;

use Tests\TestCase;

class SecurityBugFixes11Test extends TestCase
{
    /** @test */
    public function test_11_1_legacy_dashboard_update_stage_endpoint_is_removed()
    {
        // Former IDOR surface: DashboardService::updateClientMatterStage + /dashboard/update-stage
        $this->assertFalse(
            method_exists(\App\Services\DashboardService::class, 'updateClientMatterStage')
        );
        $this->assertFalse(
            \Illuminate\Support\Facades\Route::has('dashboard.update-stage')
        );
        $this->assertFalse(
            \Illuminate\Support\Facades\Route::has('dashboard.column-preferences')
        );
    }
}
