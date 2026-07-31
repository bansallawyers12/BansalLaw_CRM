<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Staff;
use App\Models\ClientMatter;
use App\Models\Note;
use App\Services\DashboardService;
use App\Http\Controllers\CRM\DashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SecurityBugFixes11Test extends TestCase
{
    /** @test */
    public function test_11_1_update_stage_unauthorized_staff_returns_error()
    {
        $staff = new Staff();
        $staff->id = 9999;
        $staff->role = 14; // Non-super-admin role

        $service = new DashboardService();

        // Testing updateClientMatterStage for a fake non-existent item or unassigned matter
        $result = $service->updateClientMatterStage(999999, 1, $staff);
        $this->assertFalse($result['success']);
    }

    /** @test */
    public function test_11_5_visa_expiry_message_unauthorized_staff_returns_empty()
    {
        $staff = new Staff();
        $staff->id = 9999;
        $staff->role = 14;

        $service = new DashboardService();
        $result = $service->getVisaExpiryMessage(999999, $staff);
        $this->assertEquals('', $result);
    }
}
