<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

        DB::table('notes')->insert([
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
        $this->assertEquals(1, DB::table('documents')->where('client_id', $toId)->count());
    }
}
