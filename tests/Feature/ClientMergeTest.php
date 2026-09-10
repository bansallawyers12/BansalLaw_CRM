<?php

namespace Tests\Feature;

use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientMergeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_merge_records_migrates_all_client_data_and_backfills_personal_details(): void
    {
        $staff = Staff::factory()->create([
            'status' => 1,
            'role' => 1,
            'grant_super_admin_access' => 1
        ]);
        $this->actingAs($staff, 'admin');

        // Create Source Client (fromId)
        $fromId = DB::table('admins')->insertGetId([
            'type' => 'client',
            'first_name' => 'SourceFirst',
            'last_name' => 'SourceLast',
            'email' => 'source@example.com',
            'password' => bcrypt('secret'),
            'phone' => '111111111',
            'country_code' => '+61',
            'dob' => '1990-01-01',
            'gender' => 'Male',
            'marital_status' => 'Single',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create Survivor Client (toId) with blank DOB and gender
        $toId = DB::table('admins')->insertGetId([
            'type' => 'client',
            'first_name' => 'TargetFirst',
            'last_name' => 'TargetLast',
            'email' => 'target@example.com',
            'password' => bcrypt('secret'),
            'phone' => '222222222',
            'country_code' => '+61',
            'dob' => null,
            'gender' => null,
            'marital_status' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert dummy data into core and edge tables referencing $fromId
        DB::table('client_matters')->insert([
            'client_id' => $fromId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('account_client_receipts')->insert([
            'client_id' => $fromId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('account_all_invoice_receipts')->insert([
            'client_id' => $fromId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $noteId = DB::table('notes')->insertGetId([
            'client_id' => $fromId,
            'type' => 'client',
            'title' => 'Source Note',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('activities_logs')->insert([
            'client_id' => $fromId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('documents')->insert([
            'client_id' => $fromId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (Schema::hasTable('note_attachments')) {
            DB::table('note_attachments')->insert([
                'note_id' => $noteId,
                'client_id' => $fromId,
                'original_name' => 'attach.pdf',
                'stored_path' => 'notes/attach.pdf',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasColumn('documents', 'lead_id')) {
            DB::table('documents')->insert([
                'lead_id' => $fromId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('companies')) {
            DB::table('companies')->insert([
                'admin_id' => $fromId,
                'company_name' => 'Source Co',
                'contact_person_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Call merge_records route
        $response = $this->postJson(route('client.merge_records'), [
            'merge_from' => $fromId,
            'merge_into' => $toId,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
            'message' => 'You have successfully merged records.',
        ]);

        // 1. Verify Source is soft-deleted
        $fromRecord = DB::table('admins')->where('id', $fromId)->first();
        $this->assertNotNull($fromRecord->is_deleted);

        // 2. Verify Survivor backfilled personal details from source
        $toRecord = DB::table('admins')->where('id', $toId)->first();
        $this->assertEquals('1990-01-01', $toRecord->dob);
        $this->assertEquals('Male', $toRecord->gender);
        $this->assertEquals('Single', $toRecord->marital_status);

        // 3. Verify records in all tables migrated to $toId
        $this->assertEquals(0, DB::table('client_matters')->where('client_id', $fromId)->count());
        $this->assertEquals(1, DB::table('client_matters')->where('client_id', $toId)->count());

        $this->assertEquals(0, DB::table('account_client_receipts')->where('client_id', $fromId)->count());
        $this->assertEquals(1, DB::table('account_client_receipts')->where('client_id', $toId)->count());

        $this->assertEquals(0, DB::table('account_all_invoice_receipts')->where('client_id', $fromId)->count());
        $this->assertEquals(1, DB::table('account_all_invoice_receipts')->where('client_id', $toId)->count());

        $this->assertEquals(0, DB::table('notes')->where('client_id', $fromId)->count());
        $this->assertEquals(1, DB::table('notes')->where('client_id', $toId)->count());

        $this->assertEquals(0, DB::table('activities_logs')->where('client_id', $fromId)->count());
        $this->assertEquals(1, DB::table('activities_logs')->where('client_id', $toId)->count());

        $this->assertEquals(0, DB::table('documents')->where('client_id', $fromId)->count());
        $this->assertGreaterThanOrEqual(1, DB::table('documents')->where('client_id', $toId)->count());

        if (Schema::hasTable('note_attachments')) {
            $this->assertEquals(0, DB::table('note_attachments')->where('client_id', $fromId)->count());
            $this->assertEquals(1, DB::table('note_attachments')->where('client_id', $toId)->count());
        }

        if (Schema::hasColumn('documents', 'lead_id')) {
            $this->assertEquals(0, DB::table('documents')->where('lead_id', $fromId)->count());
            $this->assertEquals(1, DB::table('documents')->where('lead_id', $toId)->count());
        }

        if (Schema::hasTable('companies')) {
            $this->assertEquals(0, DB::table('companies')->where('admin_id', $fromId)->count());
            $this->assertEquals(1, DB::table('companies')->where('admin_id', $toId)->count());
        }
    }

    #[Test]
    public function test_merge_records_resolves_company_admin_id_unique_collision(): void
    {
        if (! Schema::hasTable('companies')) {
            $this->markTestSkipped('companies table not present');
        }

        $staff = Staff::factory()->create([
            'status' => 1,
            'role' => 1,
            'grant_super_admin_access' => 1,
        ]);
        $this->actingAs($staff, 'admin');

        $fromId = DB::table('admins')->insertGetId([
            'type' => 'client',
            'first_name' => 'FromCo',
            'last_name' => 'A',
            'email' => 'fromco@example.com',
            'password' => bcrypt('secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $toId = DB::table('admins')->insertGetId([
            'type' => 'client',
            'first_name' => 'ToCo',
            'last_name' => 'B',
            'email' => 'toco@example.com',
            'password' => bcrypt('secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('companies')->insert([
            'admin_id' => $fromId,
            'company_name' => 'From Name',
            'trading_name' => 'From Trading',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('companies')->insert([
            'admin_id' => $toId,
            'company_name' => 'To Name',
            'trading_name' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson(route('client.merge_records'), [
            'merge_from' => $fromId,
            'merge_into' => $toId,
        ]);

        $response->assertOk()->assertJson(['status' => true]);
        $this->assertEquals(0, DB::table('companies')->where('admin_id', $fromId)->count());
        $this->assertEquals(1, DB::table('companies')->where('admin_id', $toId)->count());
        $survivor = DB::table('companies')->where('admin_id', $toId)->first();
        $this->assertEquals('To Name', $survivor->company_name);
        $this->assertEquals('From Trading', $survivor->trading_name);
    }
}
