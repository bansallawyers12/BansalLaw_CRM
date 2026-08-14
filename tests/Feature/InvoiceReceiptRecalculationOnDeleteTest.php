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
}
