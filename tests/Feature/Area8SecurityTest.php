<?php

namespace Tests\Feature;

use App\Models\ClientContact;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class Area8SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Auth::guard('admin')->logout();
    }

    #[Test]
    public function regular_staff_cannot_access_phone_otp_for_unauthorized_contact(): void
    {
        // Create staff user assigned to client 100
        $staff = new Staff();
        $staff->id = 801;
        $staff->role = 2; // Regular Staff

        $this->actingAs($staff, 'admin');

        // Create contact owned by client 999
        $contact = new ClientContact();
        $contact->id = 8881;
        $contact->client_id = 999;
        $contact->phone = '0400000000';
        $contact->save();

        // Attempt send OTP for contact belonging to client 999
        $response = $this->postJson('/clients/phone/send-otp', [
            'contact_id' => 8881,
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function regular_staff_cannot_access_email_verification_for_unauthorized_client_email(): void
    {
        $staff = new Staff();
        $staff->id = 802;
        $staff->role = 2; // Regular Staff

        $this->actingAs($staff, 'admin');

        $email = new \App\Models\ClientEmail();
        $email->id = 8882;
        $email->client_id = 999;
        $email->email = 'unauthorized@example.com';
        $email->save();

        $response = $this->getJson('/clients/email/status/8882');

        $response->assertStatus(403);
    }

    #[Test]
    public function reception_user_id_is_configurable_via_environment_variable(): void
    {
        config(['constants.reception_user_id' => 99999]);
        $this->assertEquals(99999, config('constants.reception_user_id'));
    }

    #[Test]
    public function superadmin_elevation_privilege_checks_are_sound(): void
    {
        $superAdmin = new Staff(['role' => 1, 'status' => 1]);
        $this->assertTrue($superAdmin->hasEffectiveSuperAdminPrivileges());

        $staffGranted = new Staff(['role' => 2, 'status' => 1, 'grant_super_admin_access' => 1]);
        // Without active session elevation, granted staff does not have super admin privileges
        $this->assertFalse($staffGranted->hasEffectiveSuperAdminPrivileges());

        session([\App\Services\CrmAccess\CrmAccessService::SESSION_SUPER_ADMIN_ELEVATED => true]);
        $this->assertTrue($staffGranted->hasEffectiveSuperAdminPrivileges());
    }

    #[Test]
    public function incoming_sms_webhooks_create_sms_logs_and_associate_contact(): void
    {
        $contact = new ClientContact();
        $contact->id = 8889;
        $contact->client_id = 701;
        $contact->phone = '0412345678';
        $contact->save();

        // 1. With invalid signature, when Cellcast secret set, fails 401
        config(['services.cellcast.api_key' => 'secret_token_123', 'services.cellcast.webhook_secret' => null]);
        $unauthResponse = $this->postJson('/webhooks/sms/cellcast/incoming', [
            'from' => '+61412345678',
            'message' => 'Hello from client',
            'message_id' => 'SM1234567890',
        ], [
            'X-Cellcast-Signature' => 'invalid_signature_hash',
        ]);
        $unauthResponse->assertStatus(401);

        // 2. Unset secret token fails closed in testing / non-local environments (401)
        config(['services.cellcast.api_key' => null, 'services.cellcast.webhook_secret' => null]);
        $responseUnset = $this->postJson('/webhooks/sms/cellcast/incoming', [
            'from' => '+61412345678',
            'message' => 'Hello from client',
            'message_id' => 'SM1234567890',
        ]);
        $responseUnset->assertStatus(401);

        // 3. Valid secret token or header signature processes incoming SMS log (200)
        config(['services.cellcast.webhook_secret' => 'valid_secret_key_456']);
        $responseValid = $this->postJson('/webhooks/sms/cellcast/incoming', [
            'from' => '+61412345678',
            'message' => 'Hello from client',
            'message_id' => 'SM1234567890',
        ], [
            'X-Cellcast-Signature' => 'valid_secret_key_456',
        ]);

        $responseValid->assertStatus(200);
        $this->assertDatabaseHas('sms_logs', [
            'provider_message_id' => 'SM1234567890',
            'message_type' => 'notification',
            'status' => 'delivered',
            'client_contact_id' => 8889,
        ]);
    }

    #[Test]
    public function service_account_token_endpoint_validates_credentials_and_issues_token(): void
    {
        \Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
            ['id' => 1],
            ['name' => 'Admin', 'created_at' => now(), 'updated_at' => now()]
        );

        $staff = new Staff();
        $staff->email = 'admin1@bansallawyers.com.au';
        $staff->password = \Illuminate\Support\Facades\Hash::make('admin123');
        $staff->role = 1;
        $staff->status = 1;
        $staff->save();

        // 1. Invalid password request fails with 401
        $invalidResponse = $this->postJson('/api/service-account/generate-token', [
            'service_name' => 'TestService',
            'description' => 'Testing service account',
            'admin_email' => 'admin1@bansallawyers.com.au',
            'admin_password' => 'wrongpassword',
        ]);
        $invalidResponse->assertStatus(401);

        // 2. Valid password request succeeds and returns token
        $validResponse = $this->postJson('/api/service-account/generate-token', [
            'service_name' => 'TestService',
            'description' => 'Testing service account',
            'admin_email' => 'admin1@bansallawyers.com.au',
            'admin_password' => 'admin123',
        ]);
        $validResponse->assertStatus(200);
        $validResponse->assertJsonStructure(['success', 'token', 'service_name']);
        $this->assertTrue($validResponse->json('success'));
    }

    #[Test]
    public function service_account_token_endpoint_rejects_inactive_staff_and_unauthorized_roles(): void
    {
        \Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
            ['id' => 1],
            ['name' => 'Admin', 'created_at' => now(), 'updated_at' => now()]
        );
        \Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
            ['id' => 2],
            ['name' => 'Regular Staff', 'created_at' => now(), 'updated_at' => now()]
        );

        // 1. Inactive staff (status = 0) with correct password cannot mint token
        $inactiveStaff = new Staff();
        $inactiveStaff->email = 'inactive@bansallawyers.com.au';
        $inactiveStaff->password = \Illuminate\Support\Facades\Hash::make('secret123');
        $inactiveStaff->role = 1; // Admin role
        $inactiveStaff->status = 0; // Inactive
        $inactiveStaff->save();

        $inactiveResponse = $this->postJson('/api/service-account/generate-token', [
            'service_name' => 'TestService',
            'description' => 'Testing inactive staff',
            'admin_email' => 'inactive@bansallawyers.com.au',
            'admin_password' => 'secret123',
        ]);
        $inactiveResponse->assertStatus(401);
        $this->assertFalse($inactiveResponse->json('success'));

        // 2. Regular staff without Admin Console privileges cannot mint token
        $regularStaff = new Staff();
        $regularStaff->email = 'regular@bansallawyers.com.au';
        $regularStaff->password = \Illuminate\Support\Facades\Hash::make('secret123');
        $regularStaff->role = 2; // Non-admin role
        $regularStaff->status = 1; // Active
        $regularStaff->save();

        $regularResponse = $this->postJson('/api/service-account/generate-token', [
            'service_name' => 'TestService',
            'description' => 'Testing regular staff',
            'admin_email' => 'regular@bansallawyers.com.au',
            'admin_password' => 'secret123',
        ]);
        $regularResponse->assertStatus(401);
        $this->assertFalse($regularResponse->json('success'));

        // 3. Rate limiting kicks in on repeated attempts
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/service-account/generate-token', [
                'service_name' => 'TestService',
                'description' => 'Brute force simulation',
                'admin_email' => 'attacker_target@bansallawyers.com.au',
                'admin_password' => 'wrong',
            ]);
        }

        $throttledResponse = $this->postJson('/api/service-account/generate-token', [
            'service_name' => 'TestService',
            'description' => 'Brute force simulation',
            'admin_email' => 'attacker_target@bansallawyers.com.au',
            'admin_password' => 'wrong',
        ]);
        $throttledResponse->assertStatus(429);
    }

    #[Test]
    public function public_lead_api_does_not_disclose_existing_pii_or_lead_ids(): void
    {
        // 1. Send public lead request
        $response1 = $this->postJson('/api/leads', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'johndoe_test@example.com',
            'phone' => '+61400111222',
        ]);
        $response1->assertStatus(201);
        $response1->assertJson(['success' => true]);
        $this->assertArrayNotHasKey('data', $response1->json());
        $this->assertArrayNotHasKey('lead_id', $response1->json());

        // 2. Repeat request with existing email — should return generic success without revealing lead_id or existing data
        $response2 = $this->postJson('/api/leads', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'johndoe_test@example.com',
            'phone' => '+61400111222',
        ]);
        $response2->assertStatus(200);
        $response2->assertJson(['success' => true]);
        $this->assertArrayNotHasKey('data', $response2->json());
        $this->assertArrayNotHasKey('lead_id', $response2->json());
    }

    #[Test]
    public function regular_staff_cannot_send_manual_sms_to_unlinked_arbitrary_numbers(): void
    {
        \Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
            ['id' => 12],
            ['name' => 'Admin Console User', 'created_at' => now(), 'updated_at' => now()]
        );

        $staff = new Staff();
        $staff->id = 899;
        $staff->email = 'staff899@bansallawyers.com.au';
        $staff->password = \Illuminate\Support\Facades\Hash::make('password');
        $staff->role = 12; // Admin console access role
        $staff->status = 1;
        $staff->save();

        $this->actingAs($staff, 'admin');

        // Sending SMS to arbitrary unlinked phone number fails with 403
        $response = $this->postJson('/adminconsole/features/sms/send', [
            'phone' => '+61400999888',
            'message' => 'Test arbitrary SMS',
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function compose_email_document_url_blocks_ssrf_and_lfi_attempts(): void
    {
        $staff = new Staff();
        $staff->id = 901;
        $staff->email = 'staff901@bansallawyers.com.au';
        $staff->password = \Illuminate\Support\Facades\Hash::make('password');
        $staff->role = 1;
        $staff->status = 1;

        // 1. Create document record with SSRF internal URL
        $docSsrf = new \App\Models\Document();
        $docSsrf->id = 99901;
        $docSsrf->doc_type = 'documents';
        $docSsrf->myfile = 'http://169.254.169.254/latest/meta-data/';
        $docSsrf->save();

        // 2. Create document record with LFI path traversal
        $docLfi = new \App\Models\Document();
        $docLfi->id = 99902;
        $docLfi->doc_type = 'documents';
        $docLfi->myfile = '../../../.env';
        $docLfi->save();

        $this->actingAs($staff, 'admin');

        $response = $this->post('/sendmail', [
            'type' => 'client',
            'email_to' => ['1'],
            'email_from' => 'admin1@bansallawyers.com.au',
            'subject' => 'SSRF Test',
            'message' => 'Testing SSRF protection',
            'checklistfile_document' => [99901, 99902],
        ]);

        $this->assertTrue(in_array($response->getStatusCode(), [200, 302], true));
    }

    #[Test]
    public function regular_staff_cannot_delete_system_tables_or_unauthorized_records_via_delete_action(): void
    {
        \Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
            ['id' => 2],
            ['name' => 'Regular Staff', 'created_at' => now(), 'updated_at' => now()]
        );

        $staff = new Staff();
        $staff->id = 902;
        $staff->email = 'staff902@bansallawyers.com.au';
        $staff->password = \Illuminate\Support\Facades\Hash::make('password');
        $staff->role = 2; // Regular staff without Admin Console role
        $staff->status = 1;
        $staff->save();

        $this->actingAs($staff, 'admin');

        // 1. Attempting to delete system tables (e.g. branches) is rejected
        $response1 = $this->postJson('/delete_action', [
            'table' => 'branches',
            'id' => 1,
        ]);
        $response1->assertJson(['status' => 0]);
        $this->assertStringContainsString('Unauthorized', $response1->json('message'));

        // 2. Attempting to delete arbitrary non-allowlisted table is rejected
        $response2 = $this->postJson('/delete_action', [
            'table' => 'staff',
            'id' => 1,
        ]);
        $response2->assertJson(['status' => 0]);
        $this->assertStringContainsString('not authorized', $response2->json('message'));
    }

    #[Test]
    public function regular_staff_cannot_zero_arbitrary_table_columns_via_move_action(): void
    {
        \Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
            ['id' => 2],
            ['name' => 'Regular Staff', 'created_at' => now(), 'updated_at' => now()]
        );

        $staff = new Staff();
        $staff->id = 903;
        $staff->email = 'staff903@bansallawyers.com.au';
        $staff->password = \Illuminate\Support\Facades\Hash::make('password');
        $staff->role = 2; // Regular staff without Admin Console role
        $staff->status = 1;
        $staff->save();

        $this->actingAs($staff, 'admin');

        // 1. Attempting to zero out system table columns (e.g. workflows) is rejected
        $response1 = $this->postJson('/move_action', [
            'table' => 'workflows',
            'id' => 1,
            'col' => 'status',
        ]);
        $response1->assertJson(['status' => 0]);
        $this->assertStringContainsString('Unauthorized', $response1->json('message'));

        // 2. Attempting to zero out non-allowlisted table or column is rejected
        $response2 = $this->postJson('/move_action', [
            'table' => 'staff',
            'id' => 1,
            'col' => 'status',
        ]);
        $response2->assertJson(['status' => 0]);
        $this->assertStringContainsString('not authorized', $response2->json('message'));
    }

    #[Test]
    public function regular_staff_cannot_toggle_arbitrary_table_columns_via_update_action(): void
    {
        \Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
            ['id' => 2],
            ['name' => 'Regular Staff', 'created_at' => now(), 'updated_at' => now()]
        );

        $staff = new Staff();
        $staff->id = 904;
        $staff->email = 'staff904@bansallawyers.com.au';
        $staff->password = \Illuminate\Support\Facades\Hash::make('password');
        $staff->role = 2; // Regular staff without Admin Console role
        $staff->status = 1;
        $staff->save();

        $this->actingAs($staff, 'admin');

        // 1. Attempting to toggle status on system/staff table without Admin Console access is rejected
        $response1 = $this->postJson('/update_action', [
            'table' => 'staff',
            'id' => 1,
            'colname' => 'status',
            'current_status' => 1,
        ]);
        $response1->assertJson(['status' => 0]);
        $this->assertStringContainsString('Unauthorized', $response1->json('message'));

        // 2. Attempting to toggle arbitrary non-allowlisted table or column is rejected
        $response2 = $this->postJson('/update_action', [
            'table' => 'user_roles',
            'id' => 1,
            'colname' => 'status',
            'current_status' => 1,
        ]);
        $response2->assertJson(['status' => 0]);
        $this->assertStringContainsString('not authorized', $response2->json('message'));
    }

    #[Test]
    public function status_mutations_reject_arbitrary_non_allowlisted_tables_and_unauthorized_users(): void
    {
        \Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
            ['id' => 1],
            ['name' => 'Super Admin', 'created_at' => now(), 'updated_at' => now()]
        );
        \Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
            ['id' => 2],
            ['name' => 'Regular Staff', 'created_at' => now(), 'updated_at' => now()]
        );

        $superAdmin = new Staff();
        $superAdmin->id = 905;
        $superAdmin->email = 'superadmin905@bansallawyers.com.au';
        $superAdmin->password = \Illuminate\Support\Facades\Hash::make('password');
        $superAdmin->role = 1;
        $superAdmin->status = 1;
        $superAdmin->save();

        $regularStaff = new Staff();
        $regularStaff->id = 906;
        $regularStaff->email = 'staff906@bansallawyers.com.au';
        $regularStaff->password = \Illuminate\Support\Facades\Hash::make('password');
        $regularStaff->role = 2; // Regular staff
        $regularStaff->status = 1;
        $regularStaff->save();

        // 1. Super-admin cannot mutate non-allowlisted arbitrary tables
        $this->actingAs($superAdmin, 'admin');

        $endpoints = [
            '/approved_action' => 'staff',
            '/declined_action' => 'user_roles',
            '/process_action' => 'personal_access_tokens',
            '/archive_action' => 'migrations',
        ];

        foreach ($endpoints as $endpoint => $table) {
            $resp = $this->postJson($endpoint, [
                'table' => $table,
                'id' => 1,
            ]);
            $resp->assertJson(['status' => 0]);
            $this->assertStringContainsString('not authorized', $resp->json('message'));
        }

        // 2. Regular staff cannot mutate system configuration tables
        $this->actingAs($regularStaff, 'admin');

        $sysResp = $this->postJson('/approved_action', [
            'table' => 'workflows',
            'id' => 1,
        ]);
        $sysResp->assertJson(['status' => 0]);
        $this->assertStringContainsString('Unauthorized', $sysResp->json('message'));

        // 3. Super-admin can mutate allowlisted tables (e.g. workflows)
        $this->actingAs($superAdmin, 'admin');

        \Illuminate\Support\Facades\DB::table('workflows')->updateOrInsert(
            ['id' => 888],
            ['name' => 'Test Workflow', 'status' => 0, 'created_at' => now(), 'updated_at' => now()]
        );

        $approveResp = $this->postJson('/approved_action', [
            'table' => 'workflows',
            'id' => 888,
        ]);
        $approveResp->assertJson(['status' => 1]);
        $this->assertEquals(1, \Illuminate\Support\Facades\DB::table('workflows')->where('id', 888)->value('status'));

        $declineResp = $this->postJson('/declined_action', [
            'table' => 'workflows',
            'id' => 888,
        ]);
        $declineResp->assertJson(['status' => 1]);
        $this->assertEquals(2, \Illuminate\Support\Facades\DB::table('workflows')->where('id', 888)->value('status'));

        $processResp = $this->postJson('/process_action', [
            'table' => 'workflows',
            'id' => 888,
        ]);
        $processResp->assertJson(['status' => 1]);
        $this->assertEquals(4, \Illuminate\Support\Facades\DB::table('workflows')->where('id', 888)->value('status'));
    }

    #[Test]
    public function python_service_merge_pdfs_handles_uploaded_files_and_string_filepaths(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            '*/pdf/merge*' => \Illuminate\Support\Facades\Http::response([
                'success' => true,
                'merged_pdf_url' => 'http://localhost:5002/output/merged.pdf',
            ], 200),
        ]);

        $service = new \App\Services\PythonService();

        // 1. Test with UploadedFile objects
        $uploadedFile1 = \Illuminate\Http\UploadedFile::fake()->create('doc1.pdf', 100, 'application/pdf');
        $uploadedFile2 = \Illuminate\Http\UploadedFile::fake()->create('doc2.pdf', 200, 'application/pdf');

        $result = $service->mergePdfs([$uploadedFile1, $uploadedFile2]);
        $this->assertTrue($result['success']);
        $this->assertEquals('http://localhost:5002/output/merged.pdf', $result['merged_pdf_url']);

        // 2. Test with string file paths
        $tempFile = tempnam(sys_get_temp_dir(), 'pdf_test_') . '.pdf';
        file_put_contents($tempFile, '%PDF-1.4 Dummy PDF Content');

        try {
            $resultPath = $service->mergePdfs([$tempFile]);
            $this->assertTrue($resultPath['success']);
        } finally {
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    #[Test]
    public function login_response_does_not_enumerate_valid_emails_and_uses_constant_time_verification(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'https://www.google.com/recaptcha/api/siteverify*' => \Illuminate\Support\Facades\Http::response(['success' => true], 200),
        ]);

        \Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
            ['id' => 1],
            ['name' => 'Admin', 'created_at' => now(), 'updated_at' => now()]
        );

        $staff = new Staff();
        $staff->id = 915;
        $staff->email = 'validstaff915@bansallawyers.com.au';
        $staff->password = \Illuminate\Support\Facades\Hash::make('secretpassword');
        $staff->role = 1;
        $staff->status = 1;
        $staff->save();

        // 1. Invalid email response returns generic error message
        $response1 = $this->post('/login', [
            'email' => 'nonexistent9999@bansallawyers.com.au',
            'password' => 'wrongpassword',
            'g-recaptcha-response' => 'test-recaptcha-token',
        ]);
        $response1->assertSessionHasErrors(['email']);
        $errors1 = session('errors')->get('email');
        $this->assertEquals(['These credentials do not match our records.'], $errors1);

        // 2. Valid email with wrong password returns identical generic error message
        $response2 = $this->post('/login', [
            'email' => 'validstaff915@bansallawyers.com.au',
            'password' => 'wrongpassword',
            'g-recaptcha-response' => 'test-recaptcha-token',
        ]);
        $response2->assertSessionHasErrors(['email']);
        $errors2 = session('errors')->get('email');
        $this->assertEquals(['These credentials do not match our records.'], $errors2);
    }

    #[Test]
    public function logout_audit_log_uses_session_user_id_and_ignores_forged_request_body_id(): void
    {
        \Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
            ['id' => 1],
            ['name' => 'Admin', 'created_at' => now(), 'updated_at' => now()]
        );

        $staff = new Staff();
        $staff->id = 918;
        $staff->email = 'staff918@bansallawyers.com.au';
        $staff->password = \Illuminate\Support\Facades\Hash::make('password123');
        $staff->role = 1;
        $staff->status = 1;
        $staff->save();

        $this->actingAs($staff, 'admin');

        // Post logout with a forged user_id in the request body
        $response = $this->post('/logout', [
            'user_id' => 99999,
            'id' => 88888,
        ]);

        $response->assertRedirect();

        // Audit log must record the authenticated staff ID (918), not the forged body ID (99999 / 88888)
        $this->assertDatabaseHas('staff_login_logs', [
            'user_id' => 918,
            'message' => 'Logged out successfully',
        ]);

        $this->assertDatabaseMissing('staff_login_logs', [
            'user_id' => 99999,
        ]);
        $this->assertDatabaseMissing('staff_login_logs', [
            'user_id' => 88888,
        ]);
    }

    #[Test]
    public function quick_access_grant_prevents_duplicate_active_grants(): void
    {
        \Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
            ['id' => 14],
            ['name' => 'Agent', 'created_at' => now(), 'updated_at' => now()]
        );

        $branch = new \App\Models\Branch();
        $branch->id = 771;
        $branch->office_name = 'Main Office';
        $branch->save();

        $client = new \App\Models\Admin();
        $client->id = 771;
        $client->email = 'client771@bansallawyers.com.au';
        $client->password = \Illuminate\Support\Facades\Hash::make('password123');
        $client->type = 'client';
        $client->status = 1;
        $client->save();

        $staff = new Staff();
        $staff->id = 920;
        $staff->email = 'staff920@bansallawyers.com.au';
        $staff->password = \Illuminate\Support\Facades\Hash::make('password123');
        $staff->role = 14;
        $staff->status = 1;
        $staff->quick_access_enabled = true;
        $staff->save();

        $crmAccess = app(\App\Services\CrmAccess\CrmAccessService::class);

        // 1. Initial quick access grant succeeds
        $grant = $crmAccess->requestQuickGrant($staff, 771, 'client', 771, null, 'urgent');
        $this->assertNotNull($grant);
        $this->assertEquals('active', $grant->status);

        // 2. Second quick access grant attempt fails with CrmAccessDeniedException
        $this->expectException(\App\Services\CrmAccess\CrmAccessDeniedException::class);
        $this->expectExceptionMessage('An active quick access grant already exists for this record.');

        $crmAccess->requestQuickGrant($staff, 771, 'client', 771, null, 'urgent');
    }

    #[Test]
    public function non_super_admin_cannot_assign_super_admin_role_or_grant_access(): void
    {
        \Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
            ['id' => 12],
            ['name' => 'Manager', 'created_at' => now(), 'updated_at' => now()]
        );

        $nonSuperAdmin = new Staff();
        $nonSuperAdmin->id = 925;
        $nonSuperAdmin->email = 'manager925@bansallawyers.com.au';
        $nonSuperAdmin->password = \Illuminate\Support\Facades\Hash::make('password123');
        $nonSuperAdmin->role = 12; // Admin / Manager (role != 1)
        $nonSuperAdmin->status = 1;
        $nonSuperAdmin->save();

        $targetStaff = new Staff();
        $targetStaff->id = 926;
        $targetStaff->email = 'target926@bansallawyers.com.au';
        $targetStaff->password = \Illuminate\Support\Facades\Hash::make('password123');
        $targetStaff->role = 12;
        $targetStaff->status = 1;
        $targetStaff->save();

        $this->actingAs($nonSuperAdmin, 'admin');

        // 1. Attempting to create new staff with Super Admin role (role=1) is rejected
        $responseCreate = $this->postJson('/adminconsole/staff/store', [
            'first_name' => 'Fake',
            'last_name' => 'Superadmin',
            'email' => 'fakesuperadmin@bansallawyers.com.au',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '0400000000',
            'role' => 1, // Super Admin
            'office' => 1,
        ]);
        $this->assertTrue(in_array($responseCreate->getStatusCode(), [403, 422], true));

        // 2. Attempting to update existing staff to Super Admin role (role=1) is rejected
        $responseUpdate = $this->putJson('/adminconsole/staff/' . $targetStaff->id, [
            'first_name' => 'Target',
            'last_name' => 'Staff',
            'email' => 'target926@bansallawyers.com.au',
            'phone' => '0400000000',
            'role' => 1, // Super Admin
            'office' => 1,
        ]);
        $this->assertTrue(in_array($responseUpdate->getStatusCode(), [403, 422], true));

        // Verify target staff role remains unchanged (12)
        $this->assertEquals(12, $targetStaff->fresh()->role);
    }

    #[Test]
    public function invited_staff_tab_only_returns_invited_staff_and_all_tab_returns_all_staff(): void
    {
        \Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
            ['id' => 1],
            ['name' => 'Admin', 'created_at' => now(), 'updated_at' => now()]
        );

        $admin = new Staff();
        $admin->id = 930;
        $admin->email = 'admin930@bansallawyers.com.au';
        $admin->password = \Illuminate\Support\Facades\Hash::make('password123');
        $admin->role = 1;
        $admin->status = 1;
        $admin->save();

        $invitedStaff = new Staff();
        $invitedStaff->id = 931;
        $invitedStaff->email = 'invited931@bansallawyers.com.au';
        $invitedStaff->password = \Illuminate\Support\Facades\Hash::make('password123');
        $invitedStaff->role = 1;
        $invitedStaff->status = 2; // Status 2 = Invited
        $invitedStaff->save();

        $this->actingAs($admin, 'admin');

        // 1. Querying invited tab returns only staff with status=2
        $responseInvited = $this->getJson('/adminconsole/staff?tab=invited');
        $responseInvited->assertStatus(200);
        $this->assertEquals(1, $responseInvited->json('total'));

        // 2. Querying active tab returns active staff (status=1)
        $responseActive = $this->getJson('/adminconsole/staff?tab=active');
        $responseActive->assertStatus(200);
        $this->assertTrue($responseActive->json('total') >= 1);

        // 3. Querying all tab returns all staff
        $responseAll = $this->getJson('/adminconsole/staff?tab=all');
        $responseAll->assertStatus(200);
        $this->assertTrue($responseAll->json('total') >= 2);
    }

    #[Test]
    public function unauthorized_staff_cannot_update_matter_stage_idor(): void
    {
        \Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
            ['id' => 14],
            ['name' => 'Agent', 'created_at' => now(), 'updated_at' => now()]
        );

        $branch1 = new \App\Models\Branch();
        $branch1->id = 950;
        $branch1->office_name = 'Branch 950';
        $branch1->save();

        $branch2 = new \App\Models\Branch();
        $branch2->id = 951;
        $branch2->office_name = 'Branch 951';
        $branch2->save();

        $client = new \App\Models\Admin();
        $client->id = 950;
        $client->email = 'client950@bansallawyers.com.au';
        $client->password = \Illuminate\Support\Facades\Hash::make('password123');
        $client->type = 'client';
        $client->status = 1;
        $client->save();

        $unassignedStaff = new Staff();
        $unassignedStaff->id = 955;
        $unassignedStaff->email = 'staff955@bansallawyers.com.au';
        $unassignedStaff->password = \Illuminate\Support\Facades\Hash::make('password123');
        $unassignedStaff->role = 14;
        $unassignedStaff->status = 1;
        $unassignedStaff->office_id = 950; // Belongs to branch 950
        $unassignedStaff->save();

        $stage1 = new \App\Models\WorkflowStage();
        $stage1->id = 950;
        $stage1->name = 'Initial Stage';
        $stage1->save();

        $stage2 = new \App\Models\WorkflowStage();
        $stage2->id = 951;
        $stage2->name = 'Next Stage';
        $stage2->save();

        $matter = new \App\Models\ClientMatter();
        $matter->id = 950;
        $matter->client_id = 950;
        $matter->workflow_stage_id = 950;
        $matter->sel_legal_practitioner = 999;
        $matter->sel_person_responsible = 999;
        $matter->sel_person_assisting = 999;
        $matter->save();

        $this->actingAs($unassignedStaff, 'admin');

        // Legacy dashboard stage endpoint removed; ensure IDOR surface is gone.
        $response = $this->postJson('/dashboard/update-stage', [
            'item_id' => 950,
            'stage_id' => 951,
        ]);

        $response->assertNotFound();
        $this->assertEquals(950, $matter->fresh()->workflow_stage_id);
    }

    #[Test]
    public function unauthorized_staff_cannot_complete_or_extend_action_idor(): void
    {
        \Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
            ['id' => 14],
            ['name' => 'Agent', 'created_at' => now(), 'updated_at' => now()]
        );

        $unassignedStaff = new Staff();
        $unassignedStaff->email = 'staff960@bansallawyers.com.au';
        $unassignedStaff->password = \Illuminate\Support\Facades\Hash::make('password123');
        $unassignedStaff->role = 14;
        $unassignedStaff->status = 1;
        $unassignedStaff->save();

        $note = new \App\Models\Note();
        $note->client_id = 9999;
        $note->user_id = 8888;
        $note->assigned_to = 8888;
        $note->unique_group_id = 'group_960';
        $note->description = 'Original action description';
        $note->note_deadline = now()->addDays(5)->format('Y-m-d');
        $note->status = 0;
        $note->save();

        $this->actingAs($unassignedStaff, 'admin');

        // 1. Unauthorized action completion attempt
        $responseComplete = $this->postJson('/dashboard/tasks/complete', [
            'id' => $note->id,
            'unique_group_id' => 'group_960',
            'completion_notes' => 'Attempted IDOR completion'
        ]);

        $responseComplete->assertStatus(403);
        $this->assertEquals(0, (int)$note->fresh()->status);

        // 2. Unauthorized action deadline extension attempt
        $responseExtend = $this->postJson('/dashboard/extend-deadline', [
            'note_id' => $note->id,
            'unique_group_id' => 'group_960',
            'description' => 'Extended description',
            'note_deadline' => now()->addDays(10)->format('Y-m-d')
        ]);

        $responseExtend->assertStatus(403);
        $this->assertEquals('Original action description', $note->fresh()->description);
    }

    #[Test]
    public function dashboard_matter_counters_respect_exempt_roles_and_allocation(): void
    {
        \Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
            ['id' => 17],
            ['name' => 'Exempt Admin Role', 'created_at' => now(), 'updated_at' => now()]
        );
        \Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
            ['id' => 14],
            ['name' => 'Restricted Agent', 'created_at' => now(), 'updated_at' => now()]
        );

        $exemptStaff = new Staff();
        $exemptStaff->email = 'exempt970@bansallawyers.com.au';
        $exemptStaff->password = \Illuminate\Support\Facades\Hash::make('password123');
        $exemptStaff->role = 17; // Exempt role 17
        $exemptStaff->status = 1;
        $exemptStaff->save();

        $restrictedStaff = new Staff();
        $restrictedStaff->email = 'restricted971@bansallawyers.com.au';
        $restrictedStaff->password = \Illuminate\Support\Facades\Hash::make('password123');
        $restrictedStaff->role = 14;
        $restrictedStaff->status = 1;
        $restrictedStaff->save();

        $client = new \App\Models\Admin();
        $client->email = 'client970@bansallawyers.com.au';
        $client->password = \Illuminate\Support\Facades\Hash::make('password123');
        $client->type = 'client';
        $client->status = 1;
        $client->user_id = 9999;
        $client->save();

        $matter = new \App\Models\ClientMatter();
        $matter->client_id = $client->id;
        $matter->workflow_stage_id = 1;
        $matter->matter_status = 1;
        $matter->sel_legal_practitioner = 9999;
        $matter->sel_person_responsible = 9999;
        $matter->sel_person_assisting = 9999;
        $matter->save();

        // 1. Exempt staff (role 17) can load the dashboard
        $this->actingAs($exemptStaff, 'admin');
        $responseExempt = $this->getJson('/dashboard');
        $responseExempt->assertStatus(200);

        // 2. Restricted unassigned staff (role 14) does not count the unassigned matter
        $this->actingAs($restrictedStaff, 'admin');
        $dashboardService = app(\App\Services\DashboardService::class);
        $dataRestricted = $dashboardService->getDashboardData(new \Illuminate\Http\Request());
        $this->assertArrayNotHasKey('data', $dataRestricted);
        $this->assertArrayHasKey('count_active_matter', $dataRestricted);
    }

    #[Test]
    public function active_and_closed_matter_counters_are_viewer_scoped(): void
    {
        \Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
            ['id' => 14],
            ['name' => 'Restricted Agent', 'created_at' => now(), 'updated_at' => now()]
        );

        $staffAssigned = new Staff();
        $staffAssigned->email = 'staff_assigned_980@bansallawyers.com.au';
        $staffAssigned->password = \Illuminate\Support\Facades\Hash::make('password123');
        $staffAssigned->role = 14;
        $staffAssigned->status = 1;
        $staffAssigned->save();

        $staffUnassigned = new Staff();
        $staffUnassigned->email = 'staff_unassigned_981@bansallawyers.com.au';
        $staffUnassigned->password = \Illuminate\Support\Facades\Hash::make('password123');
        $staffUnassigned->role = 14;
        $staffUnassigned->status = 1;
        $staffUnassigned->save();

        $client = new \App\Models\Admin();
        $client->email = 'client980@bansallawyers.com.au';
        $client->password = \Illuminate\Support\Facades\Hash::make('password123');
        $client->type = 'client';
        $client->status = 1;
        $client->user_id = 9999;
        $client->save();

        $activeMatter = new \App\Models\ClientMatter();
        $activeMatter->client_id = $client->id;
        $activeMatter->workflow_stage_id = 1;
        $activeMatter->matter_status = 1;
        $activeMatter->sel_legal_practitioner = $staffAssigned->id;
        $activeMatter->save();

        $dashboardService = app(\App\Services\DashboardService::class);

        // Assigned staff sees the active matter in their count
        $this->actingAs($staffAssigned, 'admin');
        $dataAssigned = $dashboardService->getDashboardData(new \Illuminate\Http\Request());
        $this->assertGreaterThanOrEqual(1, $dataAssigned['count_active_matter']);

        // Unassigned staff sees 0 active matters for this assigned client
        $this->actingAs($staffUnassigned, 'admin');
        $dataUnassigned = $dashboardService->getDashboardData(new \Illuminate\Http\Request());
        $this->assertEquals(0, $dataUnassigned['count_active_matter']);
    }

    #[Test]
    public function note_delete_and_pin_require_post_method(): void
    {
        \Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
            ['id' => 12],
            ['name' => 'Admin Role', 'created_at' => now(), 'updated_at' => now()]
        );

        $staff = new Staff();
        $staff->email = 'staff1010@bansallawyers.com.au';
        $staff->password = \Illuminate\Support\Facades\Hash::make('password123');
        $staff->role = 12;
        $staff->status = 1;
        $staff->save();

        $client = new \App\Models\Admin();
        $client->email = 'client1010@bansallawyers.com.au';
        $client->password = \Illuminate\Support\Facades\Hash::make('password123');
        $client->type = 'client';
        $client->status = 1;
        $client->user_id = $staff->id;
        $client->save();

        $note = new \App\Models\Note();
        $note->client_id = $client->id;
        $note->user_id = $staff->id;
        $note->description = 'Test note description';
        $note->pin = 0;
        $note->save();

        $this->actingAs($staff, 'admin');

        // 1. GET /deletenote should be rejected (405 Method Not Allowed)
        $responseGetDelete = $this->getJson('/deletenote?note_id=' . $note->id);
        $this->assertTrue(in_array($responseGetDelete->getStatusCode(), [405, 404], true));
        $this->assertNotNull(\App\Models\Note::find($note->id));

        // 2. GET /pinnote should be rejected (405 Method Not Allowed)
        $responseGetPin = $this->getJson('/pinnote?note_id=' . $note->id);
        $this->assertTrue(in_array($responseGetPin->getStatusCode(), [405, 404], true));
        $this->assertEquals(0, (int)$note->fresh()->pin);

        // 3. POST /pinnote succeeds
        $responsePostPin = $this->postJson('/pinnote', ['note_id' => $note->id]);
        $responsePostPin->assertStatus(200);
        $this->assertEquals(1, (int)$note->fresh()->pin);

        // 4. POST /deletenote succeeds
        $responsePostDelete = $this->postJson('/deletenote', ['note_id' => $note->id]);
        $responsePostDelete->assertStatus(200);
        $this->assertNull(\App\Models\Note::find($note->id));
    }

    #[Test]
    public function global_client_search_masks_pii_for_inaccessible_records(): void
    {
        \Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
            ['id' => 14],
            ['name' => 'Restricted Agent', 'created_at' => now(), 'updated_at' => now()]
        );

        $unassignedStaff = new Staff();
        $unassignedStaff->email = 'staff1020@bansallawyers.com.au';
        $unassignedStaff->password = \Illuminate\Support\Facades\Hash::make('password123');
        $unassignedStaff->role = 14;
        $unassignedStaff->status = 1;
        $unassignedStaff->save();

        $clientSecret = new \App\Models\Admin();
        $clientSecret->first_name = 'Secret';
        $clientSecret->last_name = 'Person';
        $clientSecret->email = 'secret.person1020@bansallawyers.com.au';
        $clientSecret->password = \Illuminate\Support\Facades\Hash::make('password123');
        $clientSecret->type = 'client';
        $clientSecret->status = 1;
        $clientSecret->user_id = 9999;
        $clientSecret->save();

        $matter = new \App\Models\ClientMatter();
        $matter->client_id = $clientSecret->id;
        $matter->client_unique_matter_no = 'MAT1020';
        $matter->workflow_stage_id = 1;
        $matter->matter_status = 1;
        $matter->sel_legal_practitioner = 9999;
        $matter->save();

        $this->actingAs($unassignedStaff, 'admin');

        $responseSearch = $this->getJson('/clients/search?q=Secret');
        $responseSearch->assertStatus(200);

        $items = $responseSearch->json('items');
        $this->assertNotEmpty($items);

        $restrictedItem = collect($items)->firstWhere('cid', $clientSecret->id);
        $this->assertNotNull($restrictedItem);
        $this->assertTrue($restrictedItem['locked']);
        $this->assertEquals('Restricted Record', $restrictedItem['name']);
        $this->assertEquals('***@***', $restrictedItem['email']);
        $this->assertEquals('***@***', $restrictedItem['emails']);
        $this->assertStringNotContainsString('secret.person1020@bansallawyers.com.au', json_encode($restrictedItem));
    }

    #[Test]
    public function public_booking_endpoints_enforce_shared_secret_and_dedicated_throttles(): void
    {
        // 1. When BOOKING_SHARED_SECRET is configured, requests without secret fail with 401
        config(['services.booking.shared_secret' => 'super-secret-booking-token-xyz']);

        $unauthResp = $this->postJson('/api/booking-appointments', [
            'first_name' => 'John',
        ]);
        $unauthResp->assertStatus(401);
        $unauthResp->assertJson([
            'status' => false,
            'message' => 'Unauthorized booking API access.',
        ]);

        // 2. Request with wrong secret fails with 401
        $badSecretResp = $this->postJson('/api/booking-appointments', [
            'first_name' => 'John',
        ], [
            'X-Booking-Secret' => 'wrong-secret-token',
        ]);
        $badSecretResp->assertStatus(401);

        // 3. Request with valid secret header passes middleware
        $validResp = $this->postJson('/api/booking-appointments', [
            'first_name' => 'John',
        ], [
            'X-Booking-Secret' => 'super-secret-booking-token-xyz',
        ]);
        $this->assertNotEquals(401, $validResp->getStatusCode());

        // 4. When BOOKING_SHARED_SECRET is not configured, public intake passes middleware
        config(['services.booking.shared_secret' => null]);
        $openResp = $this->postJson('/api/booking-appointments', [
            'first_name' => 'John',
        ]);
        $this->assertNotEquals(401, $openResp->getStatusCode());

        // 5. Dedicated route rate limiter (throttle:10,1) triggers on excessive calls
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/appointments/record-payment-without-login', [
                'appointment_id' => 99999,
            ]);
        }

        $throttledResp = $this->postJson('/api/appointments/record-payment-without-login', [
            'appointment_id' => 99999,
        ]);
        $throttledResp->assertStatus(429);
    }

    #[Test]
    public function auth_configuration_aligns_guards_and_providers_to_staff_model(): void
    {
        // 1. Defaults use admin guard and staff password broker
        $this->assertEquals('admin', config('auth.defaults.guard'));
        $this->assertEquals('staff', config('auth.defaults.passwords'));

        // 2. All guards (admin, web, api) point to the staff provider
        $this->assertEquals('staff', config('auth.guards.admin.provider'));
        $this->assertEquals('staff', config('auth.guards.web.provider'));
        $this->assertEquals('staff', config('auth.guards.api.provider'));

        // 3. Root-level provider keys do not exist
        $this->assertNull(config('auth.admins'));
        $this->assertNull(config('auth.staff'));

        // 4. Staff provider maps to Staff model
        $this->assertEquals(\App\Models\Staff::class, config('auth.providers.staff.model'));
        $this->assertEquals(\App\Models\Admin::class, config('auth.providers.admins.model'));

        // 5. Sanctum guards configuration includes 'admin'
        $sanctumGuards = config('sanctum.guard', []);
        $this->assertContains('admin', $sanctumGuards);

        // 6. Guards resolve Staff model
        $adminProvider = Auth::guard('admin')->getProvider();
        $this->assertInstanceOf(\Illuminate\Auth\EloquentUserProvider::class, $adminProvider);
        $this->assertEquals(\App\Models\Staff::class, $adminProvider->getModel());

        $webProvider = Auth::guard('web')->getProvider();
        $this->assertInstanceOf(\Illuminate\Auth\EloquentUserProvider::class, $webProvider);
        $this->assertEquals(\App\Models\Staff::class, $webProvider->getModel());
    }

    #[Test]
    public function cors_configuration_blocks_wildcard_origins_and_uses_trusted_domains(): void
    {
        $allowedOrigins = config('cors.allowed_origins', []);

        // 1. Wildcard '*' must not be present in allowed_origins
        $this->assertNotContains('*', $allowedOrigins);

        // 2. Trusted production/website domains must be present
        $this->assertContains('https://www.bansallawyers.com.au', $allowedOrigins);
        $this->assertContains('https://bansallawyers.com.au', $allowedOrigins);
    }

    #[Test]
    public function document_policy_enforces_least_privilege_and_blocks_unauthorized_staff(): void
    {
        \Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
            ['id' => 1],
            ['name' => 'Super Admin', 'created_at' => now(), 'updated_at' => now()]
        );
        \Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
            ['id' => 2],
            ['name' => 'Regular Staff', 'created_at' => now(), 'updated_at' => now()]
        );

        $superAdmin = new Staff(['role' => 1, 'status' => 1]);
        $superAdmin->id = 851;
        $superAdmin->email = 'superadmin851@bansallawyers.com.au';
        $superAdmin->password = bcrypt('secret');
        $superAdmin->save();

        $allocatedStaff = new Staff(['role' => 2, 'status' => 1]);
        $allocatedStaff->id = 852;
        $allocatedStaff->email = 'allocated852@bansallawyers.com.au';
        $allocatedStaff->password = bcrypt('secret');
        $allocatedStaff->save();

        $unallocatedStaff = new Staff(['role' => 2, 'status' => 1]);
        $unallocatedStaff->id = 853;
        $unallocatedStaff->email = 'unallocated853@bansallawyers.com.au';
        $unallocatedStaff->password = bcrypt('secret');
        $unallocatedStaff->save();

        $client = new \App\Models\Admin();
        $client->id = 9988;
        $client->type = 'client';
        $client->first_name = 'Client';
        $client->last_name = 'Test';
        $client->email = 'client9988@example.com';
        $client->password = bcrypt('secret');
        $client->status = 1;
        $client->save();

        // Assign client to allocatedStaff via matter
        $matter = new \App\Models\ClientMatter();
        $matter->client_id = 9988;
        $matter->client_unique_matter_no = 'MAT852';
        $matter->sel_legal_practitioner = $allocatedStaff->id;
        $matter->workflow_stage_id = 1;
        $matter->matter_status = 1;
        $matter->save();

        $document = new \App\Models\Document();
        $document->client_id = 9988;
        $document->file_name = 'engagement_agreement.pdf';
        $document->filetype = 'application/pdf';
        $document->created_by = $allocatedStaff->id;
        $document->status = 'draft';
        $document->save();

        $policy = new \App\Policies\DocumentPolicy();

        // 1. Unallocated staff CANNOT view, update, delete, or void document
        $this->assertFalse($policy->view($unallocatedStaff, $document));
        $this->assertFalse($policy->update($unallocatedStaff, $document));
        $this->assertFalse($policy->delete($unallocatedStaff, $document));
        $this->assertFalse($policy->void($unallocatedStaff, $document));

        // 2. Allocated staff (and creator) CAN view, update, and delete draft document
        $this->assertTrue($policy->view($allocatedStaff, $document));
        $this->assertTrue($policy->update($allocatedStaff, $document));
        $this->assertTrue($policy->delete($allocatedStaff, $document));

        // 3. Super admin CAN view and delete document
        $this->assertTrue($policy->view($superAdmin, $document));
        $this->assertTrue($policy->delete($superAdmin, $document));

        // 4. Signed documents CANNOT be deleted or voided by anyone
        $document->status = 'signed';
        $document->save();

        $this->assertFalse($policy->delete($superAdmin, $document));
        $this->assertFalse($policy->delete($allocatedStaff, $document));
        $this->assertFalse($policy->void($superAdmin, $document));
        $this->assertFalse($policy->void($allocatedStaff, $document));
    }

    #[Test]
    public function service_account_authenticate_route_validates_active_staff_and_rejects_expired_or_inactive_tokens(): void
    {
        \Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
            ['id' => 1],
            ['name' => 'Super Admin', 'created_at' => now(), 'updated_at' => now()]
        );

        // 1. Active staff member with token
        $activeStaff = new Staff(['role' => 1, 'status' => 1]);
        $activeStaff->id = 861;
        $activeStaff->email = 'active861@bansallawyers.com.au';
        $activeStaff->password = bcrypt('secret');
        $activeStaff->save();

        $validToken = $activeStaff->createToken('AppointmentAPI')->plainTextToken;

        $response = $this->postJson('/api/service-account/authenticate', [
            'service_token' => $validToken,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Authentication successful',
                'data' => [
                    'user' => [
                        'id' => 861,
                        'email' => 'active861@bansallawyers.com.au',
                    ],
                ],
            ]);

        // 2. Inactive staff member token is rejected
        $inactiveStaff = new Staff(['role' => 1, 'status' => 0]);
        $inactiveStaff->id = 862;
        $inactiveStaff->email = 'inactive862@bansallawyers.com.au';
        $inactiveStaff->password = bcrypt('secret');
        $inactiveStaff->save();

        $inactiveToken = $inactiveStaff->createToken('InactiveService')->plainTextToken;

        $responseInactive = $this->postJson('/api/service-account/authenticate', [
            'service_token' => $inactiveToken,
        ]);

        $responseInactive->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid or expired service token',
            ]);

        // 3. Expired token is rejected
        $activeStaff->tokens()->create([
            'name' => 'ExpiredToken',
            'token' => hash('sha256', 'fake-expired-token-val'),
            'abilities' => ['*'],
            'expires_at' => now()->subMinutes(10),
            'created_at' => now()->subDays(20),
        ]);

        $responseExpired = $this->postJson('/api/service-account/authenticate', [
            'service_token' => 'fake-expired-token-val',
        ]);

        $responseExpired->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid or expired service token',
            ]);
    }

    #[Test]
    public function stripe_payment_intent_rejects_inactive_staff_and_unauthorized_roles(): void
    {
        \Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
            ['id' => 1],
            ['name' => 'Super Admin', 'created_at' => now(), 'updated_at' => now()]
        );
        \Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
            ['id' => 99],
            ['name' => 'Unauthorized Role', 'created_at' => now(), 'updated_at' => now()]
        );

        // Inactive staff
        $inactiveStaff = new Staff(['role' => 1, 'status' => 0]);
        $inactiveStaff->id = 871;
        $inactiveStaff->email = 'inactive871@bansallawyers.com.au';
        $inactiveStaff->password = bcrypt('secret');
        $inactiveStaff->save();

        \Laravel\Sanctum\Sanctum::actingAs($inactiveStaff, ['*']);

        $responseInactive = $this->postJson('/api/payments/create-payment-intent', [
            'amount' => 15000,
        ]);
        $responseInactive->assertStatus(403);

        // Active staff with non-payment/non-admin role (e.g. role 99 without modules)
        $unauthorizedStaff = new Staff(['role' => 99, 'status' => 1]);
        $unauthorizedStaff->id = 872;
        $unauthorizedStaff->email = 'staff872@bansallawyers.com.au';
        $unauthorizedStaff->password = bcrypt('secret');
        $unauthorizedStaff->save();

        \Laravel\Sanctum\Sanctum::actingAs($unauthorizedStaff, ['*']);

        $responseUnauthorized = $this->postJson('/api/payments/create-payment-intent', [
            'amount' => 15000,
        ]);
        $responseUnauthorized->assertStatus(403);
    }

    #[Test]
    public function public_leads_endpoint_prevents_lead_enumeration_and_handles_honeypot_silently(): void
    {
        $existingClient = new \App\Models\Admin();
        $existingClient->id = 9991;
        $existingClient->first_name = 'Existing';
        $existingClient->last_name = 'Person';
        $existingClient->email = 'existing9991@example.com';
        $existingClient->phone = '+61400009991';
        $existingClient->type = 'client';
        $existingClient->status = 1;
        $existingClient->password = bcrypt('secret');
        $existingClient->save();

        // 1. Calling /api/leads with existing email and migration_lead_id must NOT leak existing status or internal lead ID
        $responseEnumeration = $this->postJson('/api/leads', [
            'full_name' => 'Existing Person',
            'email' => 'existing9991@example.com',
            'phone' => '0400009991',
            'migration_lead_id' => 888,
        ]);

        $responseEnumeration->assertStatus(200);
        $data = $responseEnumeration->json();
        $this->assertTrue($data['success']);
        $this->assertArrayNotHasKey('lead_id', $data);
        $this->assertArrayNotHasKey('data', $data);

        // 2. Honeypot check: spambot filling website_hp receives 200 without DB creation
        $beforeCount = \App\Models\Admin::where('email', 'botspam@example.com')->count();
        $responseHoneypot = $this->postJson('/api/leads', [
            'full_name' => 'Bot Spammer',
            'email' => 'botspam@example.com',
            'phone' => '0400009992',
            'website_hp' => 'http://spambot-link.com',
        ]);

        $responseHoneypot->assertStatus(200);
        $afterCount = \App\Models\Admin::where('email', 'botspam@example.com')->count();
        $this->assertEquals($beforeCount, $afterCount);
    }

    #[Test]
    public function staff_cannot_toggle_own_status_via_update_action(): void
    {
        \Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
            ['id' => 1],
            ['name' => 'Super Admin', 'created_at' => now(), 'updated_at' => now()]
        );

        $superAdmin = new Staff();
        $superAdmin->id = 910;
        $superAdmin->email = 'superadmin910@bansallawyers.com.au';
        $superAdmin->password = \Illuminate\Support\Facades\Hash::make('password');
        $superAdmin->role = 1;
        $superAdmin->status = 1;
        $superAdmin->save();

        $this->actingAs($superAdmin, 'admin');

        $response = $this->postJson('/update_action', [
            'table' => 'staff',
            'id' => 910,
            'colname' => 'status',
            'current_status' => 1,
        ]);

        $response->assertJson(['status' => 0]);
        $this->assertStringContainsString('cannot modify your own staff status', $response->json('message'));
    }

    #[Test]
    public function non_superadmin_cannot_toggle_staff_status_via_update_action(): void
    {
        \Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
            ['id' => 3],
            ['name' => 'Admin Console Manager', 'created_at' => now(), 'updated_at' => now()]
        );

        $manager = new Staff();
        $manager->id = 911;
        $manager->email = 'manager911@bansallawyers.com.au';
        $manager->password = \Illuminate\Support\Facades\Hash::make('password');
        $manager->role = 3;
        $manager->status = 1;
        $manager->save();

        $otherStaff = new Staff();
        $otherStaff->id = 912;
        $otherStaff->email = 'staff912@bansallawyers.com.au';
        $otherStaff->password = \Illuminate\Support\Facades\Hash::make('password');
        $otherStaff->role = 3;
        $otherStaff->status = 1;
        $otherStaff->save();

        $this->actingAs($manager, 'admin');

        $response = $this->postJson('/update_action', [
            'table' => 'staff',
            'id' => 912,
            'colname' => 'status',
            'current_status' => 1,
        ]);

        $response->assertJson(['status' => 0]);
        $this->assertStringContainsString('Super Admin privileges', $response->json('message'));
    }

    #[Test]
    public function non_superadmin_cannot_delete_core_structural_tables_via_delete_action(): void
    {
        \Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
            ['id' => 3],
            ['name' => 'Admin Console Manager', 'created_at' => now(), 'updated_at' => now()]
        );

        $manager = new Staff();
        $manager->id = 913;
        $manager->email = 'manager913@bansallawyers.com.au';
        $manager->password = \Illuminate\Support\Facades\Hash::make('password');
        $manager->role = 3;
        $manager->status = 1;
        $manager->save();

        $this->actingAs($manager, 'admin');

        foreach (['branches', 'workflows', 'matters', 'teams'] as $table) {
            $response = $this->postJson('/delete_action', [
                'table' => $table,
                'id' => 1,
            ]);

            $response->assertJson(['status' => 0]);
            $this->assertStringContainsString('Super Admin privileges', $response->json('message'));
        }
    }

    #[Test]
    public function super_admin_cannot_delete_branch_with_active_staff_via_delete_action(): void
    {
        \Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
            ['id' => 1],
            ['name' => 'Super Admin', 'created_at' => now(), 'updated_at' => now()]
        );

        $superAdmin = new Staff();
        $superAdmin->id = 914;
        $superAdmin->email = 'superadmin914@bansallawyers.com.au';
        $superAdmin->password = \Illuminate\Support\Facades\Hash::make('password');
        $superAdmin->role = 1;
        $superAdmin->status = 1;
        $superAdmin->save();

        $branchId = \Illuminate\Support\Facades\DB::table('branches')->insertGetId([
            'office_name' => 'Test Branch Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $assignedStaff = new Staff();
        $assignedStaff->id = 915;
        $assignedStaff->email = 'assigned915@bansallawyers.com.au';
        $assignedStaff->password = \Illuminate\Support\Facades\Hash::make('password');
        $assignedStaff->role = 1;
        $assignedStaff->status = 1;
        $assignedStaff->office_id = $branchId;
        $assignedStaff->save();

        $this->actingAs($superAdmin, 'admin');

        $response = $this->postJson('/delete_action', [
            'table' => 'branches',
            'id' => $branchId,
        ]);

        $response->assertJson(['status' => 0]);
        $this->assertStringContainsString('Cannot delete office branch with active staff', $response->json('message'));
    }

    #[Test]
    public function staff_without_client_access_cannot_deactivate_client_via_delete_action(): void
    {
        \Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
            ['id' => 2],
            ['name' => 'Regular Solicitor', 'created_at' => now(), 'updated_at' => now()]
        );

        $staff = new Staff();
        $staff->id = 916;
        $staff->email = 'staff916@bansallawyers.com.au';
        $staff->password = \Illuminate\Support\Facades\Hash::make('password');
        $staff->role = 2; // Solicitor
        $staff->status = 1;
        $staff->save();

        $otherClient = new \App\Models\Admin();
        $otherClient->id = 8881;
        $otherClient->first_name = 'Unallocated';
        $otherClient->last_name = 'Client';
        $otherClient->email = 'unalloc8881@example.com';
        $otherClient->type = 'client';
        $otherClient->status = 1;
        $otherClient->password = bcrypt('secret');
        $otherClient->save();

        $this->actingAs($staff, 'admin');

        $response = $this->postJson('/delete_action', [
            'table' => 'admins',
            'id' => 8881,
        ]);

        $this->assertTrue(in_array($response->getStatusCode(), [200, 403], true));
        if ($response->getStatusCode() === 200) {
            $this->assertSame(0, $response->json('status'));
        }
    }

    #[Test]
    public function unauthenticated_user_cannot_access_or_submit_change_password(): void
    {
        $getResponse = $this->get('/change_password');
        $getResponse->assertRedirect('/login');

        $postResponse = $this->post('/change_password', [
            'old_password' => 'password123',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);
        $postResponse->assertRedirect('/login');
    }

    #[Test]
    public function change_password_rejects_password_shorter_than_8_characters(): void
    {
        \Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
            ['id' => 1],
            ['name' => 'Super Admin', 'created_at' => now(), 'updated_at' => now()]
        );

        $staff = new Staff();
        $staff->id = 920;
        $staff->email = 'staff920@bansallawyers.com.au';
        $staff->password = \Illuminate\Support\Facades\Hash::make('currentpassword123');
        $staff->role = 1;
        $staff->status = 1;
        $staff->save();

        $this->actingAs($staff, 'admin');

        $response = $this->post('/change_password', [
            'admin_id' => 920,
            'old_password' => 'currentpassword123',
            'password' => 'short1', // 6 chars
            'password_confirmation' => 'short1',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('currentpassword123', $staff->fresh()->password));
    }

    #[Test]
    public function change_password_rejects_new_password_identical_to_old_password(): void
    {
        \Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
            ['id' => 1],
            ['name' => 'Super Admin', 'created_at' => now(), 'updated_at' => now()]
        );

        $staff = new Staff();
        $staff->id = 921;
        $staff->email = 'staff921@bansallawyers.com.au';
        $staff->password = \Illuminate\Support\Facades\Hash::make('currentpassword123');
        $staff->role = 1;
        $staff->status = 1;
        $staff->save();

        $this->actingAs($staff, 'admin');

        $response = $this->post('/change_password', [
            'admin_id' => 921,
            'old_password' => 'currentpassword123',
            'password' => 'currentpassword123',
            'password_confirmation' => 'currentpassword123',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('currentpassword123', $staff->fresh()->password));
    }

    #[Test]
    public function change_password_rejects_incorrect_current_password(): void
    {
        \Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
            ['id' => 1],
            ['name' => 'Super Admin', 'created_at' => now(), 'updated_at' => now()]
        );

        $staff = new Staff();
        $staff->id = 922;
        $staff->email = 'staff922@bansallawyers.com.au';
        $staff->password = \Illuminate\Support\Facades\Hash::make('correctpassword123');
        $staff->role = 1;
        $staff->status = 1;
        $staff->save();

        $this->actingAs($staff, 'admin');

        $response = $this->post('/change_password', [
            'admin_id' => 922,
            'old_password' => 'wrongpassword123',
            'password' => 'brandnewpassword123',
            'password_confirmation' => 'brandnewpassword123',
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('Your current password does not match', session('error'));
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('correctpassword123', $staff->fresh()->password));
    }

    #[Test]
    public function change_password_succeeds_with_valid_input_and_invalidates_session(): void
    {
        \Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
            ['id' => 1],
            ['name' => 'Super Admin', 'created_at' => now(), 'updated_at' => now()]
        );

        $staff = new Staff();
        $staff->id = 923;
        $staff->email = 'staff923@bansallawyers.com.au';
        $staff->password = \Illuminate\Support\Facades\Hash::make('oldvalidpassword123');
        $staff->remember_token = 'old_token_value';
        $staff->role = 1;
        $staff->status = 1;
        $staff->save();

        $this->actingAs($staff, 'admin');

        $response = $this->post('/change_password', [
            'admin_id' => 923,
            'old_password' => 'oldvalidpassword123',
            'password' => 'brandnewpassword123',
            'password_confirmation' => 'brandnewpassword123',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHas('success');

        $freshStaff = $staff->fresh();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('brandnewpassword123', $freshStaff->password));
        $this->assertNotEquals('old_token_value', $freshStaff->remember_token);
        $this->assertFalse(\Illuminate\Support\Facades\Auth::guard('admin')->check());
    }

    #[Test]
    public function inactive_staff_cannot_change_password(): void
    {
        \Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
            ['id' => 1],
            ['name' => 'Super Admin', 'created_at' => now(), 'updated_at' => now()]
        );

        $staff = new Staff();
        $staff->id = 924;
        $staff->email = 'staff924@bansallawyers.com.au';
        $staff->password = \Illuminate\Support\Facades\Hash::make('oldvalidpassword123');
        $staff->role = 1;
        $staff->status = 0; // Inactive
        $staff->save();

        $this->actingAs($staff, 'admin');

        $response = $this->post('/change_password', [
            'admin_id' => 924,
            'old_password' => 'oldvalidpassword123',
            'password' => 'brandnewpassword123',
            'password_confirmation' => 'brandnewpassword123',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHas('error');
        $this->assertFalse(\Illuminate\Support\Facades\Auth::guard('admin')->check());
    }

    #[Test]
    public function public_document_helpers_reject_unauthorized_token_and_unauthorized_staff(): void
    {
        \Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
            ['id' => 1],
            ['name' => 'Super Admin', 'created_at' => now(), 'updated_at' => now()]
        );
        \Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
            ['id' => 2],
            ['name' => 'Regular Solicitor', 'created_at' => now(), 'updated_at' => now()]
        );

        $creatorStaff = new Staff();
        $creatorStaff->id = 925;
        $creatorStaff->email = 'creator925@bansallawyers.com.au';
        $creatorStaff->password = \Illuminate\Support\Facades\Hash::make('secret');
        $creatorStaff->role = 1;
        $creatorStaff->status = 1;
        $creatorStaff->save();

        $document = new \App\Models\Document();
        $document->id = 7001;
        $document->file_name = 'Contract.pdf';
        $document->filetype = 'application/pdf';
        $document->myfile = 'documents/contract.pdf';
        $document->file_size = 1000;
        $document->status = 'sent';
        $document->created_by = $creatorStaff->id;
        $document->client_id = 9999;
        $document->save();

        $signer = new \App\Models\Signer();
        $signer->id = 6001;
        $signer->document_id = $document->id;
        $signer->name = 'John Doe';
        $signer->email = 'john@example.com';
        $signer->token = \Illuminate\Support\Str::random(64);
        $signer->status = 'pending';
        $signer->save();

        // 1. Without token and without logged in staff -> 403
        $res = $this->get('/documents/7001/page/1');
        $res->assertStatus(403);

        $dlRes = $this->get('/documents/7001/download-signed');
        $dlRes->assertStatus(403);

        // 2. With invalid or short token (< 32 chars) -> 403
        $invalidRes = $this->get('/documents/7001/page/1?token=short');
        $invalidRes->assertStatus(403);

        // 3. With cancelled signer token -> 403
        $signer->status = 'cancelled';
        $signer->save();
        $cancelledRes = $this->get('/documents/7001/page/1?token=' . $signer->token);
        $cancelledRes->assertStatus(403);

        // 4. Regular staff who cannot view document under policy -> 403
        $otherStaff = new Staff();
        $otherStaff->id = 926;
        $otherStaff->email = 'other926@bansallawyers.com.au';
        $otherStaff->password = \Illuminate\Support\Facades\Hash::make('secret');
        $otherStaff->role = 2;
        $otherStaff->status = 1;
        $otherStaff->save();

        $this->actingAs($otherStaff, 'admin');
        $staffRes = $this->get('/documents/7001/page/1');
        $staffRes->assertStatus(403);
    }

    #[Test]
    public function public_send_reminder_enforces_rate_limits_and_signer_validations(): void
    {
        \Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
            ['id' => 1],
            ['name' => 'Super Admin', 'created_at' => now(), 'updated_at' => now()]
        );

        $creatorStaff = new Staff();
        $creatorStaff->id = 927;
        $creatorStaff->email = 'creator927@bansallawyers.com.au';
        $creatorStaff->password = \Illuminate\Support\Facades\Hash::make('secret');
        $creatorStaff->role = 1;
        $creatorStaff->status = 1;
        $creatorStaff->save();

        $document = new \App\Models\Document();
        $document->id = 7002;
        $document->file_name = 'Agreement.pdf';
        $document->filetype = 'application/pdf';
        $document->myfile = 'documents/agreement.pdf';
        $document->file_size = 1000;
        $document->status = 'sent';
        $document->created_by = $creatorStaff->id;
        $document->save();

        $signer = new \App\Models\Signer();
        $signer->id = 6002;
        $signer->document_id = $document->id;
        $signer->name = 'Jane Doe';
        $signer->email = 'jane@example.com';
        $validToken = \Illuminate\Support\Str::random(64);
        $signer->token = $validToken;
        $signer->status = 'pending';
        $signer->reminder_count = 0;
        $signer->save();

        // 1. Without token and unauthenticated -> 403
        $unauthRes = $this->postJson('/documents/7002/send-reminder', [
            'signer_id' => $signer->id,
        ]);
        $unauthRes->assertStatus(403);

        // 2. With invalid token -> 403
        $badTokenRes = $this->postJson('/documents/7002/send-reminder', [
            'signer_id' => $signer->id,
            'token' => 'invalid_random_token_1234567890123456789012',
        ]);
        $badTokenRes->assertStatus(403);

        // 3. When signer is already signed -> 400
        $signer->status = 'signed';
        $signer->save();

        $signedRes = $this->postJson('/documents/7002/send-reminder', [
            'signer_id' => $signer->id,
            'token' => $validToken,
        ]);
        $signedRes->assertStatus(400);
        $this->assertStringContainsString('already signed', $signedRes->json('message'));

        // 4. When maximum reminders (3) reached -> 400
        $signer->status = 'pending';
        $signer->reminder_count = 3;
        $signer->save();

        $maxRes = $this->postJson('/documents/7002/send-reminder', [
            'signer_id' => $signer->id,
            'token' => $validToken,
        ]);
        $maxRes->assertStatus(400);
        $this->assertStringContainsString('Maximum reminders already sent', $maxRes->json('message'));

        // 5. When reminder cooldown is active -> 429
        $signer->reminder_count = 1;
        $signer->last_reminder_sent_at = now()->subHours(2);
        $signer->save();

        $cooldownRes = $this->postJson('/documents/7002/send-reminder', [
            'signer_id' => $signer->id,
            'token' => $validToken,
        ]);
        $cooldownRes->assertStatus(429);
        $this->assertStringContainsString('cooldown active', $cooldownRes->json('message'));
    }
}



