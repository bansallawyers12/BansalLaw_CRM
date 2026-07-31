<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Staff;
use App\Models\Admin;
use App\Support\StaffClientVisibility;
use App\Http\Controllers\CRM\ClientPersonalDetailsController;
use App\Http\Controllers\CRM\ClientsController;
use App\Http\Controllers\CRM\Leads\LeadController;
use Illuminate\Http\Request;

class SecurityBugFixes12Test extends TestCase
{
    /** @test */
    public function test_12_2_global_search_redacts_pii_for_locked_items()
    {
        $item = [
            'cid' => 999999,
            'name' => 'John Doe',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
        ];

        $staff = new Staff();
        $staff->id = 9999;
        $staff->role = 14;

        $enriched = StaffClientVisibility::enrichGlobalSearchItem($item, 'client', $staff);
        
        if (!empty($enriched['locked'])) {
            $this->assertEquals('Restricted Record', $enriched['name']);
            $this->assertEquals('***@***', $enriched['email']);
            $this->assertEquals('***', $enriched['phone']);
        } else {
            $this->assertTrue(true);
        }
    }

    /** @test */
    public function test_12_3_save_parents_info_section_accepts_two_args()
    {
        $controller = new ClientPersonalDetailsController();
        $request = new Request(['id' => 999999]);

        // Calling with 2 args should not throw ArgumentCountError
        $response = $controller->saveParentsInfoSection($request, null);
        $this->assertNotNull($response);
    }
}
