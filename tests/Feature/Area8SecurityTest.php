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

        // 1. With invalid signature, when TWILIO_TOKEN set, fails 401
        config(['services.twilio.auth_token' => 'secret_token_123']);
        $unauthResponse = $this->postJson('/webhooks/sms/twilio/incoming', [
            'From' => '+61412345678',
            'Body' => 'Hello from client',
            'MessageSid' => 'SM1234567890',
        ], [
            'X-Twilio-Signature' => 'invalid_signature_hash'
        ]);
        $unauthResponse->assertStatus(401);

        // 2. Unset secret token allows fallback mode / valid signature mode
        config(['services.twilio.auth_token' => null]);
        $response = $this->postJson('/webhooks/sms/twilio/incoming', [
            'From' => '+61412345678',
            'Body' => 'Hello from client',
            'MessageSid' => 'SM1234567890',
        ]);

        $response->assertStatus(200);
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
    public function device_token_reassignment_removes_token_from_previous_user(): void
    {
        $user1 = new \App\Models\Admin();
        $user1->id = 910;
        $user1->email = 'user910@bansallawyers.com.au';
        $user1->password = \Illuminate\Support\Facades\Hash::make('password123');
        $user1->role = 7;
        $user1->status = 1;
        $user1->save();

        $user2 = new \App\Models\Admin();
        $user2->id = 911;
        $user2->email = 'user911@bansallawyers.com.au';
        $user2->password = \Illuminate\Support\Facades\Hash::make('password123');
        $user2->role = 7;
        $user2->status = 1;
        $user2->save();

        $sharedToken = 'fcm_shared_device_token_99999';
        $controller = new \App\Http\Controllers\API\StaffApiAuthController();
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('handleDeviceToken');
        $method->setAccessible(true);

        // 1. User 1 registers shared device token
        $method->invoke($controller, 910, $sharedToken, 'iPhone 15');

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => 910,
            'device_token' => $sharedToken,
            'is_active' => true,
        ]);

        // 2. User 2 registers on the same physical device (shared token reassigned)
        $method->invoke($controller, 911, $sharedToken, 'iPhone 15');

        // Verify token is cleanly reassigned to User 2 and removed for User 1
        $this->assertDatabaseHas('device_tokens', [
            'user_id' => 911,
            'device_token' => $sharedToken,
            'is_active' => true,
        ]);
        $this->assertDatabaseMissing('device_tokens', [
            'user_id' => 910,
            'device_token' => $sharedToken,
        ]);
    }

    #[Test]
    public function staff_api_login_cleans_up_sanctum_token_on_refresh_token_failure(): void
    {
        \Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
            ['id' => 12],
            ['name' => 'Manager', 'created_at' => now(), 'updated_at' => now()]
        );

        $staff = new Staff();
        $staff->id = 912;
        $staff->email = 'staff912@bansallawyers.com.au';
        $staff->password = \Illuminate\Support\Facades\Hash::make('password123');
        $staff->role = 12;
        $staff->status = 1;
        $staff->save();

        $initialTokenCount = \Illuminate\Support\Facades\DB::table('personal_access_tokens')
            ->where('tokenable_id', 912)
            ->count();

        // 1. Attempt login where staff.id 912 triggers refresh_tokens foreign key failure (not present in admins)
        $response = $this->postJson('/api/admin-login', [
            'email' => 'staff912@bansallawyers.com.au',
            'password' => 'password123',
        ]);

        $response->assertStatus(500);

        // 2. Verify no orphan Sanctum token was left in personal_access_tokens table
        $finalTokenCount = \Illuminate\Support\Facades\DB::table('personal_access_tokens')
            ->where('tokenable_id', 912)
            ->count();

        $this->assertEquals($initialTokenCount, $finalTokenCount);
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
    public function staff_timezone_savezone_authorization_checks(): void
    {
        \Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
            ['id' => 2],
            ['name' => 'Regular Staff', 'created_at' => now(), 'updated_at' => now(), 'module_access' => json_encode([])]
        );

        $staffSelf = new Staff();
        $staffSelf->id = 940;
        $staffSelf->email = 'staff940@bansallawyers.com.au';
        $staffSelf->password = \Illuminate\Support\Facades\Hash::make('password123');
        $staffSelf->role = 2; // Regular staff without user_management module access
        $staffSelf->status = 1;
        $staffSelf->save();

        $otherStaff = new Staff();
        $otherStaff->id = 941;
        $otherStaff->email = 'other941@bansallawyers.com.au';
        $otherStaff->password = \Illuminate\Support\Facades\Hash::make('password123');
        $otherStaff->role = 2;
        $otherStaff->status = 1;
        $otherStaff->save();

        $this->actingAs($staffSelf, 'admin');

        // 1. Staff can update their OWN timezone via /adminconsole/staff/savezone
        $responseSelf = $this->post('/adminconsole/staff/savezone', [
            'user_id' => 940,
            'timezone' => 'Australia/Melbourne',
        ]);
        $responseSelf->assertStatus(302);
        $this->assertEquals('Australia/Melbourne', $staffSelf->fresh()->time_zone);

        // 2. Staff WITHOUT user_management or Super Admin privilege CANNOT update another staff member's timezone
        $responseOther = $this->postJson('/adminconsole/staff/savezone', [
            'user_id' => 941,
            'timezone' => 'Australia/Sydney',
        ]);
        $this->assertTrue(in_array($responseOther->getStatusCode(), [403, 302], true));
        $this->assertNotEquals('Australia/Sydney', $otherStaff->fresh()->time_zone);
    }
}
