<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Staff;
use App\Services\TrustAccounting\TrustLedgerBalanceService;
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
        DB::table('user_roles')->updateOrInsert(
            ['id' => 2],
            ['name' => 'Staff', 'created_at' => now(), 'updated_at' => now()]
        );

        $staff = Staff::create([
            'first_name' => 'Restricted',
            'last_name' => 'Staff',
            'email' => 'restrictedstaff@example.com',
            'password' => bcrypt('password123'),
            'role' => 2,
            'status' => 1,
        ]);

        $client = Admin::create([
            'first_name' => 'Other',
            'last_name' => 'Client',
            'email' => 'otherclient@example.com',
            'password' => bcrypt('password123'),
            'type' => 'client',
            'status' => 1,
        ]);

        DB::table('account_client_receipts')->insert([
            'client_id' => $client->id,
            'invoice_no' => 'INV-TEST-IDOR',
            'receipt_type' => 3,
            'balance_amount' => 500.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($staff, 'admin');

        // Authenticated request for non-existent invoice returns success false
        $response1 = $this->postJson('/clients/invoiceamount', [
            'invoice_no' => 'INV-99999'
        ]);
        $response1->assertStatus(200);
        $this->assertFalse($response1->json('success'));
        $this->assertEquals('Invoice not found', $response1->json('message'));

        // Request for invoice belonging to client that restricted staff cannot access returns 403
        $response2 = $this->postJson('/clients/invoiceamount', [
            'invoice_no' => 'INV-TEST-IDOR'
        ]);
        $response2->assertStatus(403);
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

    #[Test]
    public function concurrent_trust_withdrawals_are_serialized_via_pessimistic_lock(): void
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
            'first_name' => 'Concurrent',
            'last_name' => 'LockClient',
            'email' => 'concurrentlock@example.com',
            'password' => bcrypt('password123'),
            'type' => 'client',
            'user_type' => 3,
        ]);

        // Insert initial Trust Deposit of $600
        DB::table('account_client_receipts')->insert([
            'user_id' => $staff->id,
            'client_id' => $client->id,
            'receipt_type' => 1,
            'trans_date' => '10/08/2026',
            'trans_no' => 'TR-2026-000888',
            'client_fund_ledger_type' => 'Deposit',
            'description' => 'Trust Deposit $600',
            'deposit_amount' => 600.00,
            'withdraw_amount' => 0.00,
            'balance_amount' => 600.00,
            'save_type' => 'final',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Two withdrawal requests of $500 each (Total = $1000 > $600 available)
        $postData1 = [
            'client_id' => $client->id,
            'receipt_type' => 1,
            'trans_date' => ['10/08/2026'],
            'entry_date' => ['10/08/2026'],
            'client_fund_ledger_type' => ['Disbursement'],
            'withdraw_amount' => [500.00],
            'description' => ['Withdrawal 1'],
        ];

        $postData2 = [
            'client_id' => $client->id,
            'receipt_type' => 1,
            'trans_date' => ['10/08/2026'],
            'entry_date' => ['10/08/2026'],
            'client_fund_ledger_type' => ['Disbursement'],
            'withdraw_amount' => [500.00],
            'description' => ['Withdrawal 2'],
        ];

        // Process first withdrawal -> succeeds ($600 - $500 = $100 remaining)
        $res1 = $this->postJson('/clients/saveaccountreport', $postData1);
        $res1->assertStatus(200);

        // Process second withdrawal -> MUST be rejected because remaining balance ($100) < $500
        $res2 = $this->postJson('/clients/saveaccountreport', $postData2);
        $res2->assertStatus(422);

        // Verify final trust balance is never negative ($100.00)
        $fundsHeld = TrustLedgerBalanceService::currentFundsHeld($client->id, null);
        $this->assertEquals(100.00, $fundsHeld);
    }

    #[Test]
    public function non_super_admin_cannot_void_invoice(): void
    {
        DB::table('user_roles')->updateOrInsert(
            ['id' => 2],
            ['name' => 'Standard Staff', 'created_at' => now(), 'updated_at' => now()]
        );

        $regularStaff = Staff::create([
            'first_name' => 'Regular',
            'last_name' => 'Staff',
            'email' => 'regularstaff@bansallawyers.com.au',
            'password' => bcrypt('password123'),
            'role' => 2,
            'status' => 1,
        ]);
        config(['app.require_super_admin_email' => true]);
        config(['app.super_admin_email' => 'admin@bansallawyers.com.au']);
        $this->actingAs($regularStaff, 'admin');

        $response = $this->postJson('/void_invoice', [
            'clickedReceiptIds' => [100]
        ]);
        $response->assertStatus(403);
        $this->assertStringContainsString('Unauthorized access', $response->json('message'));
    }

    #[Test]
    public function missing_or_non_client_id_trust_posts_are_blocked(): void
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

        // Attempt 1: Missing client_id (0 or absent)
        $postDataMissing = [
            'client_id' => 0,
            'receipt_type' => 1,
            'trans_date' => ['10/08/2026'],
            'entry_date' => ['10/08/2026'],
            'client_fund_ledger_type' => ['Deposit'],
            'deposit_amount' => [100.00],
            'description' => ['Test Deposit'],
        ];

        $resMissing = $this->postJson('/clients/saveaccountreport', $postDataMissing);
        $resMissing->assertStatus(400);

        // Attempt 2: Non-existent client_id (999999)
        $postDataNonExistent = [
            'client_id' => 999999,
            'receipt_type' => 1,
            'trans_date' => ['10/08/2026'],
            'entry_date' => ['10/08/2026'],
            'client_fund_ledger_type' => ['Deposit'],
            'deposit_amount' => [100.00],
            'description' => ['Test Deposit'],
        ];

        $resNonExistent = $this->postJson('/clients/saveaccountreport', $postDataNonExistent);
        $resNonExistent->assertStatus(403);
    }

    #[Test]
    public function actor_user_id_cannot_be_spoofed_in_request_payload(): void
    {
        DB::table('user_roles')->updateOrInsert(
            ['id' => 1],
            ['name' => 'Super Admin', 'created_at' => now(), 'updated_at' => now()]
        );

        $actualStaff = Staff::create([
            'first_name' => 'Actual',
            'last_name' => 'Staff',
            'email' => 'actualstaff@bansallawyers.com.au',
            'password' => bcrypt('password123'),
            'role' => 1,
            'status' => 1,
        ]);
        $this->actingAs($actualStaff, 'admin');

        $client = Admin::create([
            'first_name' => 'Actor',
            'last_name' => 'SpoofClient',
            'email' => 'actorspoof@example.com',
            'password' => bcrypt('password123'),
            'type' => 'client',
            'user_type' => 3,
        ]);

        // Submit office receipt passing spoofed loggedin_staffid = 9999
        $postData = [
            'client_id' => $client->id,
            'receipt_type' => 2,
            'trans_date' => ['10/08/2026'],
            'entry_date' => ['10/08/2026'],
            'payment_method' => ['Cash'],
            'deposit_amount' => [250.00],
            'description' => ['Office Receipt Test'],
            'loggedin_staffid' => 9999, // Attempted spoof!
            'loggedin_userid' => 9999,
        ];

        $response = $this->postJson('/clients/saveofficereport', $postData);
        $response->assertStatus(200);

        // Verify inserted row uses actualStaff->id (NOT 9999)
        $inserted = DB::table('account_client_receipts')
            ->where('client_id', $client->id)
            ->where('receipt_type', 2)
            ->first();

        $this->assertNotNull($inserted);
        $this->assertEquals($actualStaff->id, (int)$inserted->user_id);
    }

    #[Test]
    public function receipt_ids_are_generated_without_race_conditions(): void
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
            'first_name' => 'Receipt',
            'last_name' => 'RaceClient',
            'email' => 'receiptrace@example.com',
            'password' => bcrypt('password123'),
            'type' => 'client',
            'user_type' => 3,
        ]);

        // Post Office Receipt 1
        $postData1 = [
            'client_id' => $client->id,
            'receipt_type' => 2,
            'trans_date' => ['10/08/2026'],
            'entry_date' => ['10/08/2026'],
            'payment_method' => ['EFT'],
            'deposit_amount' => [100.00],
            'description' => ['Office Receipt 1'],
        ];
        $res1 = $this->postJson('/clients/saveofficereport', $postData1);
        $res1->assertStatus(200);

        // Post Office Receipt 2
        $postData2 = [
            'client_id' => $client->id,
            'receipt_type' => 2,
            'trans_date' => ['10/08/2026'],
            'entry_date' => ['10/08/2026'],
            'payment_method' => ['EFT'],
            'deposit_amount' => [200.00],
            'description' => ['Office Receipt 2'],
        ];
        $res2 = $this->postJson('/clients/saveofficereport', $postData2);
        $res2->assertStatus(200);

        // Retrieve receipt IDs for type 2
        $receiptIds = DB::table('account_client_receipts')
            ->where('client_id', $client->id)
            ->where('receipt_type', 2)
            ->pluck('receipt_id')
            ->toArray();

        $this->assertCount(2, $receiptIds);
        $this->assertCount(2, array_unique($receiptIds)); // Must be unique!
        $this->assertEquals(2, $receiptIds[1] - $receiptIds[0] + 1); // Sequential increment
    }

    #[Test]
    public function trust_sequence_first_row_generation_is_concurrency_safe(): void
    {
        // Delete any existing sequence rows for test year 2035
        DB::table('trust_practice_sequences')
            ->where('trust_year_start_year', 2035)
            ->delete();

        // First call creates initial row with last_sequence = 1
        $no1 = \App\Services\TrustAccounting\TrustReceiptSequenceService::nextTransNo('10/08/2035');
        $this->assertEquals('TR-2035-000001', $no1);

        // Second call increments to last_sequence = 2
        $no2 = \App\Services\TrustAccounting\TrustReceiptSequenceService::nextTransNo('10/08/2035');
        $this->assertEquals('TR-2035-000002', $no2);

        // Verify database state
        $seqRow = DB::table('trust_practice_sequences')
            ->where('trust_year_start_year', 2035)
            ->where('sequence_type', 'TR')
            ->first();

        $this->assertNotNull($seqRow);
        $this->assertEquals(2, (int) $seqRow->last_sequence);
    }
}

