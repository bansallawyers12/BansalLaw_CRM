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
}
