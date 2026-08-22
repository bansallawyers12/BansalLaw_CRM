<?php

namespace Tests\Feature;

use App\Models\AccountAllInvoiceReceipt;
use App\Models\AccountClientReceipt;
use App\Models\Admin;
use App\Models\Staff;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoiceReceiptRecalculationOnDeleteTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('user_roles')->updateOrInsert(
            ['id' => 1],
            ['name' => 'Super Admin', 'created_at' => now(), 'updated_at' => now()]
        );
        DB::table('user_roles')->updateOrInsert(
            ['id' => 2],
            ['name' => 'Staff', 'created_at' => now(), 'updated_at' => now()]
        );
    }

    #[Test]
    public function hard_delete_office_receipt_recalculates_invoice_status_and_balance(): void
    {
        $superAdmin = Staff::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'superadmin_test_' . uniqid() . '@bansallawyers.com.au',
            'password' => bcrypt('password123'),
            'role' => 1,
            'status' => 1,
        ]);
        $this->actingAs($superAdmin, 'admin');

        $client = Admin::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'client_test_' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'type' => 'client',
            'user_type' => 3,
        ]);

        $invoiceNo = 'INV-TEST-' . rand(10000, 99999);

        // 1. Create invoice in account_client_receipts and account_all_invoice_receipts
        $invoiceReceiptId = 99101;
        DB::table('account_client_receipts')->insert([
            'user_id' => $superAdmin->id,
            'client_id' => $client->id,
            'receipt_id' => $invoiceReceiptId,
            'receipt_type' => 3,
            'trans_date' => '10/08/2026',
            'entry_date' => '10/08/2026',
            'trans_no' => $invoiceNo,
            'invoice_no' => $invoiceNo,
            'description' => 'Legal Services Invoice',
            'withdraw_amount' => 1000.00,
            'balance_amount' => 1000.00,
            'partial_paid_amount' => 0.00,
            'invoice_status' => 0, // Unpaid
            'save_type' => 'final',
            'validate_receipt' => 0,
            'void_invoice' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        AccountAllInvoiceReceipt::insert([
            'user_id' => $superAdmin->id,
            'client_id' => $client->id,
            'receipt_id' => $invoiceReceiptId,
            'receipt_type' => 3,
            'trans_date' => '10/08/2026',
            'entry_date' => '10/08/2026',
            'trans_no' => $invoiceNo,
            'invoice_no' => $invoiceNo,
            'description' => 'Legal Services Line Item',
            'withdraw_amount' => 1000.00,
            'invoice_status' => 0,
            'save_type' => 'final',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Create two office receipts against this invoice
        $officeReceipt1Id = DB::table('account_client_receipts')->insertGetId([
            'user_id' => $superAdmin->id,
            'client_id' => $client->id,
            'receipt_id' => 1001,
            'receipt_type' => 2,
            'trans_date' => '10/08/2026',
            'entry_date' => '10/08/2026',
            'trans_no' => 'OFF-1001',
            'invoice_no' => $invoiceNo,
            'description' => 'Office Payment Part 1',
            'deposit_amount' => 400.00,
            'save_type' => 'final',
            'validate_receipt' => 0,
            'void_invoice' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $officeReceipt2Id = DB::table('account_client_receipts')->insertGetId([
            'user_id' => $superAdmin->id,
            'client_id' => $client->id,
            'receipt_id' => 1002,
            'receipt_type' => 2,
            'trans_date' => '11/08/2026',
            'entry_date' => '11/08/2026',
            'trans_no' => 'OFF-1002',
            'invoice_no' => $invoiceNo,
            'description' => 'Office Payment Part 2',
            'deposit_amount' => 600.00,
            'save_type' => 'final',
            'validate_receipt' => 0,
            'void_invoice' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Recalculate initial state with both receipts present -> should be Paid (1)
        app(\App\Http\Controllers\CRM\ClientAccountsController::class)
            ->recalculateInvoiceStatusAndBalance((int) $client->id, $invoiceNo);

        $invoice = DB::table('account_client_receipts')
            ->where('receipt_type', 3)
            ->where('invoice_no', $invoiceNo)
            ->first();

        $this->assertEquals(1, (int) $invoice->invoice_status, 'Invoice should be marked Paid (1)');
        $this->assertEquals(0.00, (float) $invoice->balance_amount, 'Invoice balance should be 0.00');
        $this->assertEquals(1000.00, (float) $invoice->partial_paid_amount, 'Partial paid amount should be 1000.00');

        // 3. Hard-delete Office Receipt 2 ($600) via /delete_receipt
        $deleteResponse = $this->postJson('/delete_receipt', [
            'receiptId' => $officeReceipt2Id,
            'receipt_type' => 2,
        ]);

        $deleteResponse->assertStatus(200);
        $deleteResponse->assertJson(['status' => true, 'message' => 'Receipt deleted successfully.']);

        // Check receipt 2 is gone
        $this->assertNull(DB::table('account_client_receipts')->where('id', $officeReceipt2Id)->first());

        // 4. Verify invoice status has been recalculated to Partial (2)
        $invoiceAfterDelete2 = DB::table('account_client_receipts')
            ->where('receipt_type', 3)
            ->where('invoice_no', $invoiceNo)
            ->first();

        $this->assertEquals(2, (int) $invoiceAfterDelete2->invoice_status, 'Invoice should now be Partial (2)');
        $this->assertEquals(600.00, (float) $invoiceAfterDelete2->balance_amount, 'Balance should now be 600.00');
        $this->assertEquals(400.00, (float) $invoiceAfterDelete2->partial_paid_amount, 'Partial paid should be 400.00');

        $lineItemAfterDelete2 = AccountAllInvoiceReceipt::where('receipt_type', 3)
            ->where('invoice_no', $invoiceNo)
            ->first();
        $this->assertEquals(2, (int) $lineItemAfterDelete2->invoice_status, 'Line item status should be Partial (2)');

        // 5. Hard-delete Office Receipt 1 ($400) via /delete_receipt
        $deleteResponse1 = $this->postJson('/delete_receipt', [
            'receiptId' => $officeReceipt1Id,
            'receipt_type' => 2,
        ]);

        $deleteResponse1->assertStatus(200);
        $deleteResponse1->assertJson(['status' => true]);

        // Check receipt 1 is gone
        $this->assertNull(DB::table('account_client_receipts')->where('id', $officeReceipt1Id)->first());

        // 6. Verify invoice status has been recalculated to Unpaid (0)
        $invoiceAfterDelete1 = DB::table('account_client_receipts')
            ->where('receipt_type', 3)
            ->where('invoice_no', $invoiceNo)
            ->first();

        $this->assertEquals(0, (int) $invoiceAfterDelete1->invoice_status, 'Invoice should now be Unpaid (0)');
        $this->assertEquals(1000.00, (float) $invoiceAfterDelete1->balance_amount, 'Balance should now be 1000.00');
        $this->assertEquals(0.00, (float) $invoiceAfterDelete1->partial_paid_amount, 'Partial paid should be 0.00');

        $lineItemAfterDelete1 = AccountAllInvoiceReceipt::where('receipt_type', 3)
            ->where('invoice_no', $invoiceNo)
            ->first();
        $this->assertEquals(0, (int) $lineItemAfterDelete1->invoice_status, 'Line item status should be Unpaid (0)');
    }

    #[Test]
    public function unallocated_receipt_deletion_succeeds_without_error(): void
    {
        $superAdmin = Staff::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'superadmin_test_' . uniqid() . '@bansallawyers.com.au',
            'password' => bcrypt('password123'),
            'role' => 1,
            'status' => 1,
        ]);
        $this->actingAs($superAdmin, 'admin');

        $client = Admin::create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'client_test_' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'type' => 'client',
            'user_type' => 3,
        ]);

        // Unallocated office receipt (no invoice_no)
        $receiptId = DB::table('account_client_receipts')->insertGetId([
            'user_id' => $superAdmin->id,
            'client_id' => $client->id,
            'receipt_id' => 1003,
            'receipt_type' => 2,
            'trans_date' => '12/08/2026',
            'entry_date' => '12/08/2026',
            'trans_no' => 'OFF-1003',
            'invoice_no' => null,
            'description' => 'Unallocated Office Payment',
            'deposit_amount' => 500.00,
            'save_type' => 'final',
            'validate_receipt' => 0,
            'void_invoice' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/delete_receipt', [
            'receiptId' => $receiptId,
            'receipt_type' => 2,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => true, 'message' => 'Receipt deleted successfully.']);
        $this->assertNull(DB::table('account_client_receipts')->where('id', $receiptId)->first());
    }

    #[Test]
    public function voided_invoice_status_remains_voided_on_receipt_deletion(): void
    {
        $superAdmin = Staff::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'superadmin_test_' . uniqid() . '@bansallawyers.com.au',
            'password' => bcrypt('password123'),
            'role' => 1,
            'status' => 1,
        ]);
        $this->actingAs($superAdmin, 'admin');

        $client = Admin::create([
            'first_name' => 'Void',
            'last_name' => 'Client',
            'email' => 'client_void_' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'type' => 'client',
            'user_type' => 3,
        ]);

        $invoiceNo = 'INV-VOID-' . rand(10000, 99999);

        // Voided invoice (invoice_status = 3, void_invoice = 1)
        DB::table('account_client_receipts')->insert([
            'user_id' => $superAdmin->id,
            'client_id' => $client->id,
            'receipt_id' => 99201,
            'receipt_type' => 3,
            'trans_date' => '10/08/2026',
            'entry_date' => '10/08/2026',
            'trans_no' => $invoiceNo,
            'invoice_no' => $invoiceNo,
            'description' => 'Voided Invoice',
            'withdraw_amount' => 500.00,
            'balance_amount' => 0.00,
            'partial_paid_amount' => 0.00,
            'invoice_status' => 3, // Voided
            'save_type' => 'final',
            'validate_receipt' => 0,
            'void_invoice' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $receiptId = DB::table('account_client_receipts')->insertGetId([
            'user_id' => $superAdmin->id,
            'client_id' => $client->id,
            'receipt_id' => 1004,
            'receipt_type' => 2,
            'trans_date' => '10/08/2026',
            'entry_date' => '10/08/2026',
            'trans_no' => 'OFF-1004',
            'invoice_no' => $invoiceNo,
            'description' => 'Office Receipt for Voided Invoice',
            'deposit_amount' => 200.00,
            'save_type' => 'final',
            'validate_receipt' => 0,
            'void_invoice' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/delete_receipt', [
            'receiptId' => $receiptId,
            'receipt_type' => 2,
        ]);

        $response->assertStatus(200);

        $invoice = DB::table('account_client_receipts')
            ->where('receipt_type', 3)
            ->where('invoice_no', $invoiceNo)
            ->first();

        $this->assertEquals(3, (int) $invoice->invoice_status, 'Voided invoice must retain invoice_status = 3');
    }

    #[Test]
    public function regular_staff_without_super_admin_cannot_delete_receipt(): void
    {
        $staff = Staff::create([
            'first_name' => 'Regular',
            'last_name' => 'Staff',
            'email' => 'regularstaff_' . uniqid() . '@bansallawyers.com.au',
            'password' => bcrypt('password123'),
            'role' => 2, // Regular Staff
            'status' => 1,
        ]);
        $this->actingAs($staff, 'admin');

        $response = $this->postJson('/delete_receipt', [
            'receiptId' => 1,
            'receipt_type' => 2,
        ]);

        $response->assertJson(['status' => false, 'message' => 'Unauthorized access.']);
    }

    #[Test]
    public function void_invoice_stores_correct_withdraw_amount_before_void_per_line_item(): void
    {
        $superAdmin = Staff::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'superadmin_test_' . uniqid() . '@bansallawyers.com.au',
            'password' => bcrypt('password123'),
            'role' => 1,
            'status' => 1,
        ]);
        config(['app.require_super_admin_email' => false]);
        $this->actingAs($superAdmin, 'admin');

        $client = Admin::create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'client_void_test_' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'type' => 'client',
            'user_type' => 3,
        ]);

        $invoiceNo = 'INV-MULTI-' . rand(10000, 99999);
        $receiptId = 88801;

        // Create main invoice summary row in account_client_receipts
        $parentReceiptDbId = DB::table('account_client_receipts')->insertGetId([
            'user_id' => $superAdmin->id,
            'client_id' => $client->id,
            'receipt_id' => $receiptId,
            'receipt_type' => 3,
            'trans_date' => '19/08/2026',
            'entry_date' => '19/08/2026',
            'trans_no' => $invoiceNo,
            'invoice_no' => $invoiceNo,
            'description' => 'Multi Item Legal Invoice',
            'withdraw_amount' => 750.00,
            'balance_amount' => 750.00,
            'partial_paid_amount' => 0.00,
            'invoice_status' => 0,
            'save_type' => 'final',
            'validate_receipt' => 0,
            'void_invoice' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create multiple line items in account_all_invoice_receipts with different withdraw_amount
        $item1Id = AccountAllInvoiceReceipt::insertGetId([
            'user_id' => $superAdmin->id,
            'client_id' => $client->id,
            'receipt_id' => $receiptId,
            'receipt_type' => 3,
            'trans_date' => '19/08/2026',
            'entry_date' => '19/08/2026',
            'trans_no' => $invoiceNo,
            'invoice_no' => $invoiceNo,
            'description' => 'Item 1 - Professional Fee',
            'withdraw_amount' => 500.00,
            'balance_amount' => 500.00,
            'invoice_status' => 0,
            'save_type' => 'final',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $item2Id = AccountAllInvoiceReceipt::insertGetId([
            'user_id' => $superAdmin->id,
            'client_id' => $client->id,
            'receipt_id' => $receiptId,
            'receipt_type' => 3,
            'trans_date' => '19/08/2026',
            'entry_date' => '19/08/2026',
            'trans_no' => $invoiceNo,
            'invoice_no' => $invoiceNo,
            'description' => 'Item 2 - Search Fees',
            'withdraw_amount' => 250.00,
            'balance_amount' => 250.00,
            'invoice_status' => 0,
            'save_type' => 'final',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Call void_invoice
        $response = $this->postJson('/void_invoice', [
            'clickedReceiptIds' => [$receiptId],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => true]);

        // Check account_client_receipts
        $parentRow = DB::table('account_client_receipts')->where('id', $parentReceiptDbId)->first();
        $this->assertEquals(1, (int) $parentRow->void_invoice);
        $this->assertEquals(3, (int) $parentRow->invoice_status);
        $this->assertEquals(750.00, (float) $parentRow->withdraw_amount_before_void);
        $this->assertEquals(0.00, (float) $parentRow->withdraw_amount);
        $this->assertEquals(0.00, (float) $parentRow->balance_amount);

        // Check individual line items in account_all_invoice_receipts
        $line1 = AccountAllInvoiceReceipt::find($item1Id);
        $this->assertEquals(3, (int) $line1->invoice_status);
        $this->assertEquals(500.00, (float) $line1->withdraw_amount_before_void, 'Item 1 should store its own withdraw amount before void');
        $this->assertEquals(0.00, (float) $line1->withdraw_amount);

        $line2 = AccountAllInvoiceReceipt::find($item2Id);
        $this->assertEquals(3, (int) $line2->invoice_status);
        $this->assertEquals(250.00, (float) $line2->withdraw_amount_before_void, 'Item 2 should store its own withdraw amount before void');
        $this->assertEquals(0.00, (float) $line2->withdraw_amount);
    }

    #[Test]
    public function void_invoice_unallocates_linked_office_receipts(): void
    {
        $superAdmin = Staff::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'superadmin_test_' . uniqid() . '@bansallawyers.com.au',
            'password' => bcrypt('password123'),
            'role' => 1,
            'status' => 1,
        ]);
        config(['app.require_super_admin_email' => false]);
        $this->actingAs($superAdmin, 'admin');

        $client = Admin::create([
            'first_name' => 'Bob',
            'last_name' => 'Builder',
            'email' => 'client_unalloc_test_' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'type' => 'client',
            'user_type' => 3,
        ]);

        $invoiceNo = 'INV-UNALLOC-' . rand(10000, 99999);
        $receiptId = 77701;

        // 1. Create invoice in account_client_receipts
        $invoiceDbId = DB::table('account_client_receipts')->insertGetId([
            'user_id' => $superAdmin->id,
            'client_id' => $client->id,
            'receipt_id' => $receiptId,
            'receipt_type' => 3,
            'trans_date' => '19/08/2026',
            'entry_date' => '19/08/2026',
            'trans_no' => $invoiceNo,
            'invoice_no' => $invoiceNo,
            'description' => 'Invoice with linked office receipt',
            'withdraw_amount' => 500.00,
            'balance_amount' => 100.00,
            'partial_paid_amount' => 400.00,
            'invoice_status' => 2, // Partial
            'save_type' => 'final',
            'validate_receipt' => 0,
            'void_invoice' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create line item in account_all_invoice_receipts
        AccountAllInvoiceReceipt::insertGetId([
            'user_id' => $superAdmin->id,
            'client_id' => $client->id,
            'receipt_id' => $receiptId,
            'receipt_type' => 3,
            'trans_date' => '19/08/2026',
            'entry_date' => '19/08/2026',
            'trans_no' => $invoiceNo,
            'invoice_no' => $invoiceNo,
            'description' => 'Legal Services',
            'withdraw_amount' => 500.00,
            'balance_amount' => 100.00,
            'invoice_status' => 2,
            'save_type' => 'final',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Create office receipt (receipt_type = 2) allocated to this invoice
        $officeReceiptDbId = DB::table('account_client_receipts')->insertGetId([
            'user_id' => $superAdmin->id,
            'client_id' => $client->id,
            'receipt_id' => 9001,
            'receipt_type' => 2,
            'trans_date' => '19/08/2026',
            'entry_date' => '19/08/2026',
            'trans_no' => 'OFF-9001',
            'invoice_no' => $invoiceNo,
            'description' => 'Payment for invoice ' . $invoiceNo,
            'deposit_amount' => 300.00,
            'save_type' => 'final',
            'validate_receipt' => 0,
            'void_invoice' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Create journal receipt (receipt_type = 4) allocated to this invoice
        $journalReceiptDbId = DB::table('account_client_receipts')->insertGetId([
            'user_id' => $superAdmin->id,
            'client_id' => $client->id,
            'receipt_id' => 9002,
            'receipt_type' => 4,
            'trans_date' => '19/08/2026',
            'entry_date' => '19/08/2026',
            'trans_no' => 'JRN-9002',
            'invoice_no' => $invoiceNo,
            'description' => 'Journal payment for invoice ' . $invoiceNo,
            'deposit_amount' => 100.00,
            'save_type' => 'final',
            'validate_receipt' => 0,
            'void_invoice' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Call void_invoice for this invoice
        $response = $this->postJson('/void_invoice', [
            'clickedReceiptIds' => [$receiptId],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => true]);

        // 4. Verify invoice is voided
        $invoiceAfterVoid = DB::table('account_client_receipts')->where('id', $invoiceDbId)->first();
        $this->assertEquals(1, (int) $invoiceAfterVoid->void_invoice);
        $this->assertEquals(3, (int) $invoiceAfterVoid->invoice_status);
        $this->assertEquals(500.00, (float) $invoiceAfterVoid->withdraw_amount_before_void);
        $this->assertEquals(0.00, (float) $invoiceAfterVoid->withdraw_amount);
        $this->assertEquals(0.00, (float) $invoiceAfterVoid->balance_amount);
        $this->assertEquals(0.00, (float) $invoiceAfterVoid->partial_paid_amount);

        // 5. Verify office receipt is now UNALLOCATED (invoice_no set to null)
        $officeReceiptAfterVoid = DB::table('account_client_receipts')->where('id', $officeReceiptDbId)->first();
        $this->assertNull($officeReceiptAfterVoid->invoice_no, 'Office receipt invoice_no should be null after invoice void');
        $this->assertEquals(300.00, (float) $officeReceiptAfterVoid->deposit_amount, 'Office receipt deposit amount must remain intact');

        // 6. Verify journal receipt is now UNALLOCATED (invoice_no set to null)
        $journalReceiptAfterVoid = DB::table('account_client_receipts')->where('id', $journalReceiptDbId)->first();
        $this->assertNull($journalReceiptAfterVoid->invoice_no, 'Journal receipt invoice_no should be null after invoice void');
        $this->assertEquals(100.00, (float) $journalReceiptAfterVoid->deposit_amount, 'Journal receipt deposit amount must remain intact');
    }

    #[Test]
    public function void_fee_transfer_is_excluded_from_invoice_payment_totals(): void
    {
        $superAdmin = Staff::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'superadmin_test_' . uniqid() . '@bansallawyers.com.au',
            'password' => bcrypt('password123'),
            'role' => 1,
            'status' => 1,
        ]);
        config(['app.require_super_admin_email' => false]);
        $this->actingAs($superAdmin, 'admin');

        $client = Admin::create([
            'first_name' => 'Alice',
            'last_name' => 'Wonder',
            'email' => 'client_voided_ft_' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'type' => 'client',
            'user_type' => 3,
        ]);

        $invoiceNo = 'INV-VOIDFT-' . rand(10000, 99999);
        $receiptId = 66601;

        DB::table('account_client_receipts')->insertGetId([
            'user_id' => $superAdmin->id,
            'client_id' => $client->id,
            'receipt_id' => $receiptId,
            'receipt_type' => 3,
            'trans_date' => '19/08/2026',
            'entry_date' => '19/08/2026',
            'trans_no' => $invoiceNo,
            'invoice_no' => $invoiceNo,
            'description' => 'Legal Invoice',
            'withdraw_amount' => 1000.00,
            'balance_amount' => 1000.00,
            'partial_paid_amount' => 0.00,
            'invoice_status' => 0,
            'save_type' => 'final',
            'validate_receipt' => 0,
            'void_invoice' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        AccountAllInvoiceReceipt::insertGetId([
            'user_id' => $superAdmin->id,
            'client_id' => $client->id,
            'receipt_id' => $receiptId,
            'receipt_type' => 3,
            'trans_date' => '19/08/2026',
            'entry_date' => '19/08/2026',
            'trans_no' => $invoiceNo,
            'invoice_no' => $invoiceNo,
            'description' => 'Legal Fees',
            'withdraw_amount' => 1000.00,
            'balance_amount' => 1000.00,
            'invoice_status' => 0,
            'save_type' => 'final',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('account_client_receipts')->insert([
            'user_id' => $superAdmin->id,
            'client_id' => $client->id,
            'receipt_id' => 9101,
            'receipt_type' => 2,
            'trans_date' => '19/08/2026',
            'entry_date' => '19/08/2026',
            'trans_no' => 'OFF-9101',
            'invoice_no' => $invoiceNo,
            'description' => 'Active Office Receipt',
            'deposit_amount' => 200.00,
            'save_type' => 'final',
            'validate_receipt' => 0,
            'void_invoice' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('account_client_receipts')->insert([
            'user_id' => $superAdmin->id,
            'client_id' => $client->id,
            'receipt_id' => 9102,
            'receipt_type' => 1,
            'client_fund_ledger_type' => 'Fee Transfer',
            'trans_date' => '19/08/2026',
            'entry_date' => '19/08/2026',
            'trans_no' => 'FT-9102',
            'invoice_no' => $invoiceNo,
            'description' => 'Voided Fee Transfer',
            'deposit_amount' => 0.00,
            'withdraw_amount' => 500.00,
            'balance_amount' => null,
            'void_fee_transfer' => 1,
            'save_type' => 'final',
            'validate_receipt' => 0,
            'void_invoice' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('account_client_receipts')->insert([
            'user_id' => $superAdmin->id,
            'client_id' => $client->id,
            'receipt_id' => 9103,
            'receipt_type' => 1,
            'client_fund_ledger_type' => 'Fee Transfer',
            'trans_date' => '19/08/2026',
            'entry_date' => '19/08/2026',
            'trans_no' => 'FT-9103',
            'invoice_no' => $invoiceNo,
            'description' => 'Active Fee Transfer',
            'deposit_amount' => 0.00,
            'withdraw_amount' => 300.00,
            'balance_amount' => 300.00,
            'void_fee_transfer' => 0,
            'save_type' => 'final',
            'validate_receipt' => 0,
            'void_invoice' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(\App\Http\Controllers\CRM\ClientAccountsController::class)
            ->recalculateInvoiceStatusAndBalance((int) $client->id, $invoiceNo);

        $invoice = DB::table('account_client_receipts')
            ->where('receipt_type', 3)
            ->where('invoice_no', $invoiceNo)
            ->first();

        $this->assertEquals(500.00, (float) $invoice->partial_paid_amount, 'Total paid should exclude void_fee_transfer fee transfer');
        $this->assertEquals(500.00, (float) $invoice->balance_amount, 'Balance should be $1000 - $500 = $500');
        $this->assertEquals(2, (int) $invoice->invoice_status, 'Status should be Partial (2)');
    }
}
