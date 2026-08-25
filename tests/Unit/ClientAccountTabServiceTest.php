<?php

namespace Tests\Unit;

use App\Models\AccountClientReceipt;
use App\Models\Admin;
use App\Models\ClientLegalForm;
use App\Models\Staff;
use App\Services\ClientAccountTabService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientAccountTabServiceTest extends TestCase
{
    use DatabaseTransactions;

    private ClientAccountTabService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ClientAccountTabService::class);
    }

    #[Test]
    public function build_excludes_voided_fee_transfers_from_trust_balance(): void
    {
        $client = Admin::create([
            'first_name' => 'Trust',
            'last_name' => 'Client',
            'email' => 'trust_client_' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'type' => 'client',
            'status' => 1,
        ]);

        $this->insertTrustRow($client->id, null, 100, 0, 0);
        $this->insertTrustRow($client->id, null, 0, 40, 1);

        $data = $this->service->build((int) $client->id, null);

        $this->assertSame(100.0, $data['trustBalance']);
        $this->assertCount(2, $data['trustRows']);
        $this->assertTrue($data['trustRows'][1]->is_voided_for_balance);
        $this->assertNull($data['trustRows'][1]->running_balance);
        $this->assertSame(100.0, $data['trustRows'][0]->running_balance);
    }

    #[Test]
    public function build_computes_running_balance_in_order_and_scopes_by_matter(): void
    {
        $client = Admin::create([
            'first_name' => 'Matter',
            'last_name' => 'Scoped',
            'email' => 'matter_client_' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'type' => 'client',
            'status' => 1,
        ]);

        $this->insertTrustRow($client->id, 10, 200, 0, 0);
        $this->insertTrustRow($client->id, 10, 0, 50, 0);
        $this->insertTrustRow($client->id, 20, 999, 0, 0);

        $data = $this->service->build((int) $client->id, 10);

        $this->assertSame(150.0, $data['trustBalance']);
        $this->assertCount(2, $data['trustRows']);
        $this->assertSame(200.0, $data['trustRows'][0]->running_balance);
        $this->assertSame(150.0, $data['trustRows'][1]->running_balance);
    }

    #[Test]
    public function build_sums_outstanding_from_latest_invoice_row_per_receipt_id(): void
    {
        $client = Admin::create([
            'first_name' => 'Invoice',
            'last_name' => 'Client',
            'email' => 'invoice_client_' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'type' => 'client',
            'status' => 1,
        ]);

        $this->insertInvoiceRow($client->id, null, 9001, 500, 500, 0);
        $this->insertInvoiceRow($client->id, null, 9001, 500, 250, 2);
        $this->insertInvoiceRow($client->id, null, 9002, 300, 300, 0);

        $data = $this->service->build((int) $client->id, null);

        $this->assertSame(550.0, $data['outstandingBalance']);
        $this->assertCount(2, $data['invoiceRows']);
    }

    #[Test]
    public function build_loads_latest_generated_costs_disclosure_for_matter(): void
    {
        $client = Admin::create([
            'first_name' => 'Disclosure',
            'last_name' => 'Client',
            'email' => 'disclosure_client_' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'type' => 'client',
            'status' => 1,
        ]);

        $staff = Staff::create([
            'first_name' => 'Test',
            'last_name' => 'Staff',
            'email' => 'disclosure_staff_' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'status' => 1,
        ]);

        $this->insertLegalForm($client->id, 10, $staff->id, [
            'form_type' => 'short_costs_disclosure',
            'estimated_total' => 1100,
            'estimated_legal_fees' => 1000,
            'form_date' => '2026-01-01',
        ]);
        $this->insertLegalForm($client->id, 10, $staff->id, [
            'form_type' => 'cost_agreement',
            'estimated_total' => 2200,
            'estimated_legal_fees' => 2000,
            'gst_amount' => 200,
            'form_date' => '2026-02-01',
        ]);
        $this->insertLegalForm($client->id, 20, $staff->id, [
            'form_type' => 'short_costs_disclosure',
            'estimated_total' => 9999,
            'estimated_legal_fees' => 9000,
            'form_date' => '2026-03-01',
        ]);
        $this->insertLegalForm($client->id, 10, $staff->id, [
            'form_type' => 'short_costs_disclosure',
            'is_uploaded' => true,
            'estimated_total' => 5000,
            'estimated_legal_fees' => 4500,
            'form_date' => '2026-04-01',
        ]);

        $data = $this->service->build((int) $client->id, 10);

        $this->assertNotNull($data['costsDisclosure']);
        $this->assertSame('cost_agreement', $data['costsDisclosure']['formType']);
        $this->assertSame(2200.0, $data['costsDisclosure']['estimatedTotal']);
        $this->assertSame(2200.0, $data['costsDisclosure']['professionalFeesInclGst']);
    }

    #[Test]
    public function build_computes_invoiced_total_and_exceeds_disclosure_flag(): void
    {
        $client = Admin::create([
            'first_name' => 'Variance',
            'last_name' => 'Client',
            'email' => 'variance_client_' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'type' => 'client',
            'status' => 1,
        ]);

        $staff = Staff::create([
            'first_name' => 'Var',
            'last_name' => 'Staff',
            'email' => 'variance_staff_' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'status' => 1,
        ]);

        $this->insertLegalForm($client->id, null, $staff->id, [
            'form_type' => 'short_costs_disclosure',
            'estimated_total' => 1000,
            'estimated_legal_fees' => 900,
            'gst_amount' => 100,
        ]);

        $this->insertInvoiceRow($client->id, null, 9101, 700, 700, 0, 0);
        $this->insertInvoiceRow($client->id, null, 9102, 400, 400, 0, 0);
        $this->insertInvoiceRow($client->id, null, 9103, 200, 200, 0, 1);

        $data = $this->service->build((int) $client->id, null);

        $this->assertSame(1100.0, $data['invoicedTotal']);
        $this->assertTrue($data['exceedsDisclosure']);
    }

    private function insertTrustRow(int $clientId, ?int $matterId, float $deposit, float $withdraw, int $voidFeeTransfer): void
    {
        DB::table('account_client_receipts')->insert([
            'client_id' => $clientId,
            'client_matter_id' => $matterId,
            'receipt_type' => AccountClientReceipt::RECEIPT_TYPE_TRUST_LEDGER,
            'client_fund_ledger_type' => 'Deposit',
            'deposit_amount' => $deposit,
            'withdraw_amount' => $withdraw,
            'void_fee_transfer' => $voidFeeTransfer,
            'trans_date' => '01/01/2026',
            'entry_date' => '01/01/2026',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertInvoiceRow(
        int $clientId,
        ?int $matterId,
        int $receiptId,
        float $withdrawAmount,
        float $balanceAmount,
        int $invoiceStatus,
        int $voidInvoice = 0
    ): void {
        DB::table('account_client_receipts')->insert([
            'client_id' => $clientId,
            'client_matter_id' => $matterId,
            'receipt_id' => $receiptId,
            'receipt_type' => 3,
            'withdraw_amount' => $withdrawAmount,
            'balance_amount' => $balanceAmount,
            'invoice_status' => $invoiceStatus,
            'void_invoice' => $voidInvoice,
            'trans_no' => 'INV-' . $receiptId,
            'trans_date' => '01/01/2026',
            'entry_date' => '01/01/2026',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function insertLegalForm(int $clientId, ?int $matterId, int $createdBy, array $overrides = []): void
    {
        ClientLegalForm::create(array_merge([
            'client_id' => $clientId,
            'client_matter_id' => $matterId,
            'created_by' => $createdBy,
            'form_type' => 'short_costs_disclosure',
            'is_uploaded' => false,
            'estimated_legal_fees' => 0,
            'estimated_disbursements' => 0,
            'estimated_barrister_fees' => 0,
            'gst_amount' => 0,
            'estimated_total' => 0,
            'fixed_fee_amount' => 0,
            'retainer_amount' => 0,
            'form_date' => '2026-01-15',
        ], $overrides));
    }
}
