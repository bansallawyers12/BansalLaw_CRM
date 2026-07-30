<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Staff;
use App\Services\TrustAccounting\TrustReceiptSequenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TrustAccountingSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Auth::guard('admin')->logout();
    }

    #[Test]
    public function it_generates_unique_trust_sequence_numbers_without_races(): void
    {
        $transNo1 = TrustReceiptSequenceService::nextTransNo('30/07/2026');
        $transNo2 = TrustReceiptSequenceService::nextTransNo('30/07/2026');

        $this->assertStringStartsWith('TR-2026-000001', $transNo1);
        $this->assertStringStartsWith('TR-2026-000002', $transNo2);
    }

    #[Test]
    public function get_invoice_amount_requires_crm_record_access(): void
    {
        $staff = new Staff();
        $staff->id = 100;
        $staff->email = 'staff@example.com';
        $staff->role = 2;
        
        $this->actingAs($staff, 'admin');

        // Authenticated request for non-existent invoice returns success false
        $response = $this->postJson('/clients/invoiceamount', [
            'invoice_no' => 'INV-99999'
        ]);
        
        $response->assertStatus(200);
        $this->assertFalse($response->json('success'));
        $this->assertEquals('Invoice not found', $response->json('message'));
    }

    #[Test]
    public function void_invoice_requires_financial_super_admin_privileges(): void
    {
        config(['app.require_super_admin_email' => true]);
        config(['app.super_admin_email' => 'admin@bansallawyers.com.au']);

        $staff = new Staff();
        $staff->id = 101;
        $staff->email = 'regularstaff@example.com';
        $staff->role = 2; // non super admin (role 2)

        $this->actingAs($staff, 'admin');

        // Regular staff request to void invoice is blocked with 403
        $response = $this->postJson('/void_invoice', [
            'clickedReceiptIds' => [1]
        ]);

        $response->assertStatus(403);
        $this->assertFalse($response->json('status'));
        $this->assertStringContainsString('Unauthorized access', $response->json('message'));
    }
}
