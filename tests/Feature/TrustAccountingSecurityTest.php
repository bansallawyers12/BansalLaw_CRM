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

    #[Test]
    public function fee_transfer_does_not_create_phantom_trust_deposit_rows(): void
    {
        DB::table('user_roles')->updateOrInsert(
            ['id' => 1],
            ['name' => 'Super Admin', 'created_at' => now(), 'updated_at' => now()]
        );

        $staff = Staff::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin1@bansallawyers.com.au',
            'password' => bcrypt('password123'),
            'role' => 1,
            'status' => 1,
        ]);
        $this->actingAs($staff, 'admin');

        $client = Admin::create([
            'first_name' => 'Test',
            'last_name' => 'Client',
            'email' => 'testclient@example.com',
            'password' => bcrypt('password123'),
            'type' => 'client',
            'user_type' => 3,
        ]);

        // Insert initial Trust Deposit of $1000
        DB::table('account_client_receipts')->insert([
            'user_id' => $staff->id,
            'client_id' => $client->id,
            'receipt_type' => 1,
            'trans_date' => '10/08/2026',
            'trans_no' => 'TR-2026-000001',
            'client_fund_ledger_type' => 'Deposit',
            'description' => 'Initial Trust Deposit',
            'deposit_amount' => 1000.00,
            'withdraw_amount' => 0.00,
            'balance_amount' => 1000.00,
            'save_type' => 'final',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert Invoice of $400
        DB::table('account_client_receipts')->insert([
            'user_id' => $staff->id,
            'client_id' => $client->id,
            'receipt_type' => 3,
            'invoice_no' => 'INV-TEST-001',
            'trans_date' => '10/08/2026',
            'trans_no' => 'INV-2026-000001',
            'description' => 'Legal Fee Invoice',
            'deposit_amount' => 0.00,
            'withdraw_amount' => 400.00,
            'balance_amount' => 400.00,
            'partial_paid_amount' => 0.00,
            'invoice_status' => 0,
            'save_type' => 'final',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Ensure trust_withdrawal_authority_types has ID 1
        DB::table('trust_withdrawal_authority_types')->updateOrInsert(
            ['id' => 1],
            ['label' => 'Written client authority', 'sort_order' => 10, 'is_active' => true]
        );

        // Submit Fee Transfer of $400 for INV-TEST-001
        $postData = [
            'client_id' => $client->id,
            'receipt_type' => 1,
            'trans_date' => ['10/08/2026'],
            'entry_date' => ['10/08/2026'],
            'client_fund_ledger_type' => ['Fee Transfer'],
            'invoice_no' => ['INV-TEST-001'],
            'withdraw_amount' => [400.00],
            'description' => ['Fee Transfer for INV-TEST-001'],
            'trust_withdrawal_authority_type_id' => 1,
            'trust_authority_notes' => 'Client written approval for fee transfer',
        ];

        $response = $this->postJson('/clients/saveaccountreport', $postData);
        $response->assertStatus(200);

        // Verify only 1 Deposit exists (the original $1000 deposit), NO phantom/residual deposit created
        $deposits = DB::table('account_client_receipts')
            ->where('client_id', $client->id)
            ->where('client_fund_ledger_type', 'Deposit')
            ->get();
        $this->assertCount(1, $deposits);
        $this->assertEquals(1000.00, (float)$deposits->first()->deposit_amount);

        // Verify Fee Transfer row created as Withdrawal of $400
        $feeTransfers = DB::table('account_client_receipts')
            ->where('client_id', $client->id)
            ->where('client_fund_ledger_type', 'Fee Transfer')
            ->get();
        $this->assertCount(1, $feeTransfers);
        $this->assertEquals(400.00, (float)$feeTransfers->first()->withdraw_amount);

        // Verify invoice balance updated to 0 and status to 1 (Paid)
        $invoice = DB::table('account_client_receipts')
            ->where('client_id', $client->id)
            ->where('receipt_type', 3)
            ->where('invoice_no', 'INV-TEST-001')
            ->first();
        $this->assertEquals(1, $invoice->invoice_status);
        $this->assertEquals(0.00, (float)$invoice->balance_amount);
        $this->assertEquals(400.00, (float)$invoice->partial_paid_amount);
    }

    #[Test]
    public function void_invoice_does_not_void_unrelated_fee_transfers(): void
    {
        DB::table('user_roles')->updateOrInsert(
            ['id' => 1],
            ['name' => 'Super Admin', 'created_at' => now(), 'updated_at' => now()]
        );

        $staff = Staff::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'admin@bansallawyers.com.au',
            'password' => bcrypt('password123'),
            'role' => 1,
            'status' => 1,
        ]);
        config(['app.require_super_admin_email' => true]);
        config(['app.super_admin_email' => 'admin@bansallawyers.com.au']);
        $this->actingAs($staff, 'admin');

        $client = Admin::create([
            'first_name' => 'Unrelated',
            'last_name' => 'Client',
            'email' => 'unrelatedclient@example.com',
            'password' => bcrypt('password123'),
            'type' => 'client',
            'user_type' => 3,
        ]);

        // Create initial deposit of $1000
        DB::table('account_client_receipts')->insert([
            'user_id' => $staff->id,
            'client_id' => $client->id,
            'receipt_type' => 1,
            'trans_date' => '10/08/2026',
            'trans_no' => 'TR-2026-000100',
            'client_fund_ledger_type' => 'Deposit',
            'description' => 'Initial Trust Deposit',
            'deposit_amount' => 1000.00,
            'withdraw_amount' => 0.00,
            'balance_amount' => 1000.00,
            'save_type' => 'final',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Fee Transfer #1 for INV-PAID-001 ($500)
        $ft1Id = DB::table('account_client_receipts')->insertGetId([
            'user_id' => $staff->id,
            'client_id' => $client->id,
            'receipt_id' => 101,
            'receipt_type' => 1,
            'trans_date' => '10/08/2026',
            'invoice_no' => 'INV-PAID-001',
            'trans_no' => 'TR-2026-000101',
            'client_fund_ledger_type' => 'Fee Transfer',
            'description' => 'Fee Transfer for INV-PAID-001',
            'deposit_amount' => 0.00,
            'withdraw_amount' => 500.00,
            'balance_amount' => 500.00,
            'save_type' => 'final',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Invoice #1 (INV-PAID-001, receipt_id = 501)
        DB::table('account_client_receipts')->insert([
            'user_id' => $staff->id,
            'client_id' => $client->id,
            'receipt_id' => 501,
            'receipt_type' => 3,
            'invoice_no' => 'INV-PAID-001',
            'trans_date' => '10/08/2026',
            'trans_no' => 'INV-2026-000501',
            'description' => 'Paid Legal Invoice',
            'deposit_amount' => 0.00,
            'withdraw_amount' => 500.00,
            'balance_amount' => 0.00,
            'partial_paid_amount' => 500.00,
            'invoice_status' => 1,
            'save_type' => 'final',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Unrelated Invoice #2 (INV-UNPAID-002, receipt_id = 502) - same amount ($500), unpaid, no fee transfer linked!
        DB::table('account_client_receipts')->insert([
            'user_id' => $staff->id,
            'client_id' => $client->id,
            'receipt_id' => 502,
            'receipt_type' => 3,
            'invoice_no' => 'INV-UNPAID-002',
            'trans_date' => '10/08/2026',
            'trans_no' => 'INV-2026-000502',
            'description' => 'Unpaid Legal Invoice',
            'deposit_amount' => 0.00,
            'withdraw_amount' => 500.00,
            'balance_amount' => 500.00,
            'partial_paid_amount' => 0.00,
            'invoice_status' => 0,
            'save_type' => 'final',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Call void_invoice for receipt_id = 502 (INV-UNPAID-002)
        $response = $this->postJson('/void_invoice', [
            'clickedReceiptIds' => [502]
        ]);
        $response->assertStatus(200);

        // Verify that Fee Transfer #1 for INV-PAID-001 was NOT voided
        $ft1 = DB::table('account_client_receipts')->where('id', $ft1Id)->first();
        $this->assertEquals(0, (int)($ft1->void_fee_transfer ?? 0));
    }

    #[Test]
    public function cross_matter_fee_transfer_is_blocked(): void
    {
        DB::table('user_roles')->updateOrInsert(
            ['id' => 1],
            ['name' => 'Super Admin', 'created_at' => now(), 'updated_at' => now()]
        );

        $staff = Staff::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin1@bansallawyers.com.au',
            'password' => bcrypt('password123'),
            'role' => 1,
            'status' => 1,
        ]);
        $this->actingAs($staff, 'admin');

        $client = Admin::create([
            'first_name' => 'Matter',
            'last_name' => 'ScopedClient',
            'email' => 'matterscoped@example.com',
            'password' => bcrypt('password123'),
            'type' => 'client',
            'user_type' => 3,
        ]);

        // Insert Trust Deposit of $1000 for Matter 10
        DB::table('account_client_receipts')->insert([
            'user_id' => $staff->id,
            'client_id' => $client->id,
            'client_matter_id' => 10,
            'receipt_type' => 1,
            'trans_date' => '10/08/2026',
            'trans_no' => 'TR-2026-000999',
            'client_fund_ledger_type' => 'Deposit',
            'description' => 'Trust Deposit for Matter 10',
            'deposit_amount' => 1000.00,
            'withdraw_amount' => 0.00,
            'balance_amount' => 1000.00,
            'save_type' => 'final',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert Invoice for Matter 20 ($500) - Matter 20 has $0 trust deposit!
        DB::table('account_client_receipts')->insert([
            'user_id' => $staff->id,
            'client_id' => $client->id,
            'client_matter_id' => 20,
            'receipt_type' => 3,
            'invoice_no' => 'INV-MATTER20-001',
            'trans_date' => '10/08/2026',
            'trans_no' => 'INV-2026-000999',
            'description' => 'Invoice for Matter 20',
            'deposit_amount' => 0.00,
            'withdraw_amount' => 500.00,
            'balance_amount' => 500.00,
            'partial_paid_amount' => 0.00,
            'invoice_status' => 0,
            'save_type' => 'final',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('trust_withdrawal_authority_types')->updateOrInsert(
            ['id' => 1],
            ['label' => 'Written client authority', 'sort_order' => 10, 'is_active' => true]
        );

        // Submit Fee Transfer for INV-MATTER20-001 specifying client_matter_id = 10 (cross-matter request!)
        $postData = [
            'client_id' => $client->id,
            'client_matter_id' => 10,
            'receipt_type' => 1,
            'trans_date' => ['10/08/2026'],
            'entry_date' => ['10/08/2026'],
            'client_fund_ledger_type' => ['Fee Transfer'],
            'invoice_no' => ['INV-MATTER20-001'],
            'withdraw_amount' => [500.00],
            'description' => ['Cross-matter Fee Transfer Attempt'],
            'trust_withdrawal_authority_type_id' => 1,
            'trust_authority_notes' => 'Client written approval for fee transfer',
        ];

        $response = $this->postJson('/clients/saveaccountreport', $postData);
        $response->assertStatus(422);
        $this->assertStringContainsString('Cross-matter fee transfer blocked', $response->json('message'));
    }
}

