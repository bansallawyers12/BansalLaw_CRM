<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Staff;
use App\Services\ClientAccountTabService;
use App\Support\InvoiceTimesheetLine;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoiceCreateAndVisibilityTest extends TestCase
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
    public function saveinvoicereport_assigns_single_matter_and_creates_one_activity(): void
    {
        $staff = Staff::create([
            'first_name' => 'Inv',
            'last_name' => 'Staff',
            'email' => 'inv_staff_'.uniqid().'@bansallawyers.com.au',
            'password' => bcrypt('password123'),
            'role' => 1,
            'status' => 1,
        ]);
        $this->actingAs($staff, 'admin');

        $client = Admin::create([
            'first_name' => 'Kabir',
            'last_name' => 'Test',
            'email' => 'inv_client_'.uniqid().'@example.com',
            'password' => bcrypt('password123'),
            'type' => 'client',
            'user_type' => 3,
            'client_id' => 'TEST'.rand(100000, 999999),
        ]);

        $matterId = DB::table('client_matters')->insertGetId([
            'client_id' => $client->id,
            'client_unique_matter_no' => 'MERITS_1',
            'matter_status' => '1',
            'office_id' => 1,
            'workflow_id' => 1,
            'workflow_stage_id' => 1,
            'sel_matter_id' => 1,
            'user_id' => $staff->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/clients/saveinvoicereport', [
            'client_id' => $client->id,
            'client_matter_id' => '', // empty string previously broke PG / left null
            'receipt_type' => 3,
            'function_type' => 'add',
            'save_type' => 'final',
            'trans_date' => ['14/09/2026'],
            'entry_date' => ['14/09/2026'],
            'gst_included' => ['Yes'],
            'payment_type' => ['Professional Fees'],
            'description' => ['Test invoice line'],
            'withdraw_amount' => ['550.00'],
        ]);

        $response->assertOk()->assertJson(['status' => true]);
        $invoiceNo = $response->json('invoice_no');
        $this->assertNotEmpty($invoiceNo);

        $parent = DB::table('account_client_receipts')
            ->where('receipt_type', 3)
            ->where('invoice_no', $invoiceNo)
            ->first();
        $this->assertNotNull($parent);
        $this->assertSame($matterId, (int) $parent->client_matter_id);

        $line = DB::table('account_all_invoice_receipts')
            ->where('receipt_type', 3)
            ->where('invoice_no', $invoiceNo)
            ->first();
        $this->assertNotNull($line);
        $this->assertEqualsWithDelta(500.0, (float) $line->amount_ex_gst, 0.01);
        $this->assertEqualsWithDelta(50.0, (float) $line->line_gst, 0.01);
        $this->assertEqualsWithDelta(550.0, (float) $line->withdraw_amount, 0.01);

        $activityCount = DB::table('activities_logs')
            ->where('client_id', $client->id)
            ->where('subject', 'like', '%'.$invoiceNo.'%')
            ->count();
        $this->assertSame(1, $activityCount);
    }

    #[Test]
    public function saveinvoicereport_stores_timesheet_hours_rate_role_and_gst(): void
    {
        $staff = Staff::create([
            'first_name' => 'Inv',
            'last_name' => 'Timesheet',
            'email' => 'inv_ts_'.uniqid().'@bansallawyers.com.au',
            'password' => bcrypt('password123'),
            'role' => 1,
            'status' => 1,
        ]);
        $this->actingAs($staff, 'admin');

        $client = Admin::create([
            'first_name' => 'Timesheet',
            'last_name' => 'Client',
            'email' => 'inv_ts_client_'.uniqid().'@example.com',
            'password' => bcrypt('password123'),
            'type' => 'client',
            'user_type' => 3,
            'client_id' => 'TEST'.rand(100000, 999999),
        ]);

        DB::table('client_matters')->insertGetId([
            'client_id' => $client->id,
            'client_unique_matter_no' => 'MERITS_1',
            'matter_status' => '1',
            'office_id' => 1,
            'workflow_id' => 1,
            'workflow_stage_id' => 1,
            'sel_matter_id' => 1,
            'user_id' => $staff->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/clients/saveinvoicereport', [
            'client_id' => $client->id,
            'receipt_type' => 3,
            'function_type' => 'add',
            'save_type' => 'draft',
            'trans_date' => ['28 May 2026'],
            'entry_date' => ['14/09/2026'],
            'payment_type' => ['Professional Fees'],
            'description' => ['Finalise and issue LOD'],
            'billing_basis' => ['hourly'],
            'hours' => ['0.8'],
            'rate_ex_gst' => ['500.00'],
            'fee_earner_id' => [$staff->id],
            'fee_earner_role' => ['Solicitor'],
        ]);

        $response->assertOk()->assertJson(['status' => true]);
        $invoiceNo = $response->json('invoice_no');

        $line = DB::table('account_all_invoice_receipts')
            ->where('receipt_type', 3)
            ->where('invoice_no', $invoiceNo)
            ->first();
        $this->assertNotNull($line);
        $this->assertSame('hourly', $line->billing_basis);
        $this->assertEqualsWithDelta(0.8, (float) $line->hours, 0.001);
        $this->assertEqualsWithDelta(500.0, (float) $line->rate_ex_gst, 0.01);
        $this->assertEqualsWithDelta(400.0, (float) $line->amount_ex_gst, 0.01);
        $this->assertEqualsWithDelta(40.0, (float) $line->line_gst, 0.01);
        $this->assertEqualsWithDelta(440.0, (float) $line->withdraw_amount, 0.01);
        $this->assertSame((int) $staff->id, (int) $line->fee_earner_id);
        $this->assertSame('Solicitor', $line->fee_earner_role);

        $parent = DB::table('account_client_receipts')
            ->where('receipt_type', 3)
            ->where('invoice_no', $invoiceNo)
            ->first();
        $this->assertEqualsWithDelta(440.0, (float) $parent->withdraw_amount, 0.01);
    }

    #[Test]
    public function saveinvoicereport_stores_work_date_range_and_parent_uses_start_date(): void
    {
        $staff = Staff::create([
            'first_name' => 'Inv',
            'last_name' => 'Range',
            'email' => 'inv_range_'.uniqid().'@bansallawyers.com.au',
            'password' => bcrypt('password123'),
            'role' => 1,
            'status' => 1,
        ]);
        $this->actingAs($staff, 'admin');

        $client = Admin::create([
            'first_name' => 'Range',
            'last_name' => 'Client',
            'email' => 'inv_range_client_'.uniqid().'@example.com',
            'password' => bcrypt('password123'),
            'type' => 'client',
            'user_type' => 3,
            'client_id' => 'TEST'.rand(100000, 999999),
        ]);

        DB::table('client_matters')->insertGetId([
            'client_id' => $client->id,
            'client_unique_matter_no' => 'MERITS_1',
            'matter_status' => '1',
            'office_id' => 1,
            'workflow_id' => 1,
            'workflow_stage_id' => 1,
            'sel_matter_id' => 1,
            'user_id' => $staff->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $range = '22/06/2026 – 16/07/2026';
        $response = $this->postJson('/clients/saveinvoicereport', [
            'client_id' => $client->id,
            'receipt_type' => 3,
            'function_type' => 'add',
            'save_type' => 'draft',
            'trans_date' => [$range],
            'entry_date' => ['15/09/2026'],
            'payment_type' => ['Professional Fees'],
            'description' => ['Work across a date range'],
            'billing_basis' => ['hourly'],
            'hours' => ['1'],
            'rate_ex_gst' => ['100.00'],
            'fee_earner_role' => ['Solicitor'],
        ]);

        $response->assertOk()->assertJson(['status' => true]);
        $invoiceNo = $response->json('invoice_no');

        $line = DB::table('account_all_invoice_receipts')
            ->where('receipt_type', 3)
            ->where('invoice_no', $invoiceNo)
            ->first();
        $this->assertSame($range, $line->trans_date);

        $parent = DB::table('account_client_receipts')
            ->where('receipt_type', 3)
            ->where('invoice_no', $invoiceNo)
            ->first();
        $this->assertSame('22/06/2026', $parent->trans_date);
    }

    #[Test]
    public function saveinvoicereport_draft_works_with_empty_function_type_and_matter_ref(): void
    {
        $staff = Staff::create([
            'first_name' => 'Inv',
            'last_name' => 'Draft',
            'email' => 'inv_draft_'.uniqid().'@bansallawyers.com.au',
            'password' => bcrypt('password123'),
            'role' => 1,
            'status' => 1,
        ]);
        $this->actingAs($staff, 'admin');

        $client = Admin::create([
            'first_name' => 'Draft',
            'last_name' => 'Client',
            'email' => 'inv_draft_client_'.uniqid().'@example.com',
            'password' => bcrypt('password123'),
            'type' => 'client',
            'user_type' => 3,
            'client_id' => 'TEST'.rand(100000, 999999),
        ]);

        // Multi-matter client: empty matter id must not auto-assign; matter ref must resolve.
        DB::table('client_matters')->insertGetId([
            'client_id' => $client->id,
            'client_unique_matter_no' => 'OTHER_1',
            'matter_status' => '1',
            'office_id' => 1,
            'workflow_id' => 1,
            'workflow_stage_id' => 1,
            'sel_matter_id' => 1,
            'user_id' => $staff->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $matterId = DB::table('client_matters')->insertGetId([
            'client_id' => $client->id,
            'client_unique_matter_no' => 'CIV_1',
            'matter_status' => '1',
            'office_id' => 1,
            'workflow_id' => 1,
            'workflow_stage_id' => 1,
            'sel_matter_id' => 2,
            'user_id' => $staff->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/clients/saveinvoicereport', [
            'client_id' => $client->id,
            'client_matter_id' => 'CIV_1',
            'receipt_type' => 3,
            'function_type' => '', // production hidden field often posts empty
            'save_type' => ['', 'draft'], // FormData append duplicate
            'trans_date' => ['11/06/2026'],
            'entry_date' => ['22/09/2026'],
            'payment_type' => ['Professional Fees'],
            'description' => ['Review of outstanding documents and preparation of next steps'],
            'billing_basis' => ['hourly'],
            'hours' => ['0.65'],
            'rate_ex_gst' => ['400.00'],
            'fee_earner_id' => [$staff->id],
            'fee_earner_role' => ['Solicitor'],
        ]);

        $response->assertOk()->assertJson([
            'status' => true,
            'function_type' => 'add',
        ]);
        $invoiceNo = $response->json('invoice_no');
        $this->assertNotEmpty($invoiceNo);

        $parent = DB::table('account_client_receipts')
            ->where('receipt_type', 3)
            ->where('invoice_no', $invoiceNo)
            ->first();
        $this->assertNotNull($parent);
        $this->assertSame('draft', $parent->save_type);
        $this->assertSame($matterId, (int) $parent->client_matter_id);
        $this->assertEqualsWithDelta(286.0, (float) $parent->withdraw_amount, 0.01);
    }

    #[Test]
    public function billing_tab_shows_null_matter_invoice_for_single_matter_client(): void
    {
        $client = Admin::create([
            'first_name' => 'Solo',
            'last_name' => 'Matter',
            'email' => 'solo_matter_'.uniqid().'@example.com',
            'password' => bcrypt('password123'),
            'type' => 'client',
            'user_type' => 3,
        ]);

        $matterId = DB::table('client_matters')->insertGetId([
            'client_id' => $client->id,
            'client_unique_matter_no' => 'MERITS_1',
            'matter_status' => '1',
            'office_id' => 1,
            'workflow_id' => 1,
            'workflow_stage_id' => 1,
            'sel_matter_id' => 1,
            'user_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('account_client_receipts')->insert([
            'client_id' => $client->id,
            'client_matter_id' => null,
            'receipt_id' => 88007,
            'receipt_type' => 3,
            'invoice_no' => 'INV-007',
            'trans_no' => 'INV-007',
            'withdraw_amount' => 100,
            'balance_amount' => 100,
            'invoice_status' => 0,
            'void_invoice' => 0,
            'save_type' => 'final',
            'trans_date' => '14/09/2026',
            'entry_date' => '14/09/2026',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $data = app(ClientAccountTabService::class)->build((int) $client->id, $matterId);

        $this->assertTrue(
            $data['invoiceRows']->contains(fn ($row) => ($row->invoice_no ?? $row->trans_no) === 'INV-007')
        );
    }

    #[Test]
    public function repair_command_attaches_null_matter_invoice(): void
    {
        $client = Admin::create([
            'first_name' => 'Repair',
            'last_name' => 'Client',
            'email' => 'repair_client_'.uniqid().'@example.com',
            'password' => bcrypt('password123'),
            'type' => 'client',
            'user_type' => 3,
            'client_id' => 'REPR'.rand(100000, 999999),
        ]);

        $matterId = DB::table('client_matters')->insertGetId([
            'client_id' => $client->id,
            'client_unique_matter_no' => 'MERITS_1',
            'matter_status' => '1',
            'office_id' => 1,
            'workflow_id' => 1,
            'workflow_stage_id' => 1,
            'sel_matter_id' => 1,
            'user_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('account_client_receipts')->insert([
            'client_id' => $client->id,
            'client_matter_id' => null,
            'receipt_id' => 88008,
            'receipt_type' => 3,
            'invoice_no' => 'INV-008',
            'trans_no' => 'INV-008',
            'withdraw_amount' => 200,
            'balance_amount' => 200,
            'invoice_status' => 0,
            'void_invoice' => 0,
            'save_type' => 'final',
            'trans_date' => '14/09/2026',
            'entry_date' => '14/09/2026',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('activities_logs')->insert([
            'client_id' => $client->id,
            'created_by' => 1,
            'subject' => 'added invoice.Reference no- INV-008',
            'description' => '',
            'activity_type' => 'financial',
            'task_status' => 0,
            'pin' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('invoices:repair-missing', [
            'invoice_no' => 'INV-008',
            '--client' => $client->client_id,
            '--force' => true,
        ])->assertSuccessful();

        $parent = DB::table('account_client_receipts')
            ->where('invoice_no', 'INV-008')
            ->where('client_id', $client->id)
            ->first();
        $this->assertSame($matterId, (int) $parent->client_matter_id);
    }

    #[Test]
    public function selectable_fee_earners_includes_only_admin_and_solicitor_roles(): void
    {
        $nextId = (int) (DB::table('user_roles')->max('id') ?? 100) + 1;

        $adminRole = DB::table('user_roles')->whereRaw("LOWER(TRIM(name)) = 'admin'")->first();
        if ($adminRole) {
            $adminRoleId = $adminRole->id;
        } else {
            $adminRoleId = $nextId++;
            DB::table('user_roles')->insert([
                'id' => $adminRoleId,
                'name' => 'Admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $solicitorRole = DB::table('user_roles')->whereRaw("LOWER(TRIM(name)) = 'solicitor'")->first();
        if ($solicitorRole) {
            $solicitorRoleId = $solicitorRole->id;
        } else {
            $solicitorRoleId = $nextId++;
            DB::table('user_roles')->insert([
                'id' => $solicitorRoleId,
                'name' => 'Solicitor',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $accountantRole = DB::table('user_roles')->whereRaw("LOWER(TRIM(name)) = 'accountant'")->first();
        if ($accountantRole) {
            $accountantRoleId = $accountantRole->id;
        } else {
            $accountantRoleId = $nextId++;
            DB::table('user_roles')->insert([
                'id' => $accountantRoleId,
                'name' => 'Accountant',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $superAdminRole = DB::table('user_roles')->whereRaw("LOWER(TRIM(name)) = 'super admin'")->first();
        if ($superAdminRole) {
            $superAdminRoleId = $superAdminRole->id;
        } else {
            $superAdminRoleId = $nextId++;
            DB::table('user_roles')->insert([
                'id' => $superAdminRoleId,
                'name' => 'Super Admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $adminStaff = Staff::create([
            'first_name' => 'TestAdmin',
            'last_name' => 'User',
            'email' => 'admin_'.uniqid().'@example.com',
            'password' => bcrypt('password123'),
            'role' => $adminRoleId,
            'status' => 1,
        ]);

        $solicitorStaff = Staff::create([
            'first_name' => 'TestSolicitor',
            'last_name' => 'User',
            'email' => 'solicitor_'.uniqid().'@example.com',
            'password' => bcrypt('password123'),
            'role' => $solicitorRoleId,
            'status' => 1,
        ]);

        $accountantStaff = Staff::create([
            'first_name' => 'TestAccountant',
            'last_name' => 'User',
            'email' => 'accountant_'.uniqid().'@example.com',
            'password' => bcrypt('password123'),
            'role' => $accountantRoleId,
            'status' => 1,
        ]);

        $superAdminStaff = Staff::create([
            'first_name' => 'TestSuperAdmin',
            'last_name' => 'User',
            'email' => 'superadmin_'.uniqid().'@example.com',
            'password' => bcrypt('password123'),
            'role' => $superAdminRoleId,
            'status' => 1,
        ]);

        $inactiveAdminStaff = Staff::create([
            'first_name' => 'InactiveAdmin',
            'last_name' => 'User',
            'email' => 'inactive_admin_'.uniqid().'@example.com',
            'password' => bcrypt('password123'),
            'role' => $adminRoleId,
            'status' => 0,
        ]);

        $selectable = InvoiceTimesheetLine::selectableFeeEarners();
        $selectableIds = $selectable->pluck('id')->all();

        $this->assertContains($adminStaff->id, $selectableIds);
        $this->assertContains($solicitorStaff->id, $selectableIds);
        $this->assertNotContains($accountantStaff->id, $selectableIds);
        $this->assertNotContains($superAdminStaff->id, $selectableIds);
        $this->assertNotContains($inactiveAdminStaff->id, $selectableIds);
    }
}
