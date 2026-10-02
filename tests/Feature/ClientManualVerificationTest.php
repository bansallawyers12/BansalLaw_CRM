<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ClientContact;
use App\Models\ClientEmail;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientManualVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Auth::guard('admin')->logout();

        \Illuminate\Support\Facades\DB::table('user_roles')->insertOrIgnore([
            ['id' => 1, 'name' => 'Admin', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    #[Test]
    public function unauthenticated_request_is_rejected(): void
    {
        $response = $this->postJson('/clients/update-email-verified', [
            'client_id' => 1,
            'manual_email_phone_verified' => 1,
        ]);

        $response->assertUnauthorized();
    }

    #[Test]
    public function validation_fails_when_parameters_missing(): void
    {
        $staff = $this->createStaff();

        $response = $this->actingAs($staff, 'admin')
            ->postJson('/clients/update-email-verified', []);

        $response->assertStatus(422)
            ->assertJson([
                'status' => false,
                'success' => false,
            ]);
    }

    #[Test]
    public function manual_verification_marks_client_emails_and_contacts_verified(): void
    {
        $staff = $this->createStaff();
        $client = $this->createClient();

        $email = ClientEmail::create([
            'client_id' => $client->id,
            'admin_id' => $staff->id,
            'email_type' => 'Personal',
            'email' => 'client@example.com',
            'is_verified' => false,
            'verified_at' => null,
            'verified_by' => null,
        ]);

        $contact = ClientContact::create([
            'client_id' => $client->id,
            'admin_id' => $staff->id,
            'contact_type' => 'Personal',
            'phone' => '412345678',
            'country_code' => '+61',
            'is_verified' => false,
            'verified_at' => null,
            'verified_by' => null,
        ]);

        $response = $this->actingAs($staff, 'admin')
            ->postJson('/clients/update-email-verified', [
                'client_id' => $client->id,
                'manual_email_phone_verified' => 1,
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => true,
                'success' => true,
                'is_verified' => true,
            ]);

        $email->refresh();
        $contact->refresh();

        $this->assertTrue($email->is_verified);
        $this->assertNotNull($email->verified_at);
        $this->assertEquals($staff->id, $email->verified_by);

        $this->assertTrue($contact->is_verified);
        $this->assertNotNull($contact->verified_at);
        $this->assertEquals($staff->id, $contact->verified_by);
    }

    #[Test]
    public function manual_verification_reverts_client_emails_and_contacts_to_unverified(): void
    {
        $staff = $this->createStaff();
        $client = $this->createClient();

        $email = ClientEmail::create([
            'client_id' => $client->id,
            'admin_id' => $staff->id,
            'email_type' => 'Personal',
            'email' => 'client@example.com',
            'is_verified' => true,
            'verified_at' => now(),
            'verified_by' => $staff->id,
        ]);

        $contact = ClientContact::create([
            'client_id' => $client->id,
            'admin_id' => $staff->id,
            'contact_type' => 'Personal',
            'phone' => '412345678',
            'country_code' => '+61',
            'is_verified' => true,
            'verified_at' => now(),
            'verified_by' => $staff->id,
        ]);

        $response = $this->actingAs($staff, 'admin')
            ->postJson('/clients/update-email-verified', [
                'client_id' => $client->id,
                'manual_email_phone_verified' => 0,
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => true,
                'success' => true,
                'is_verified' => false,
            ]);

        $email->refresh();
        $contact->refresh();

        $this->assertFalse($email->is_verified);
        $this->assertNull($email->verified_at);
        $this->assertNull($email->verified_by);

        $this->assertFalse($contact->is_verified);
        $this->assertNull($contact->verified_at);
        $this->assertNull($contact->verified_by);
    }

    #[Test]
    public function route_alias_update_email_verified_works_identically(): void
    {
        $staff = $this->createStaff();
        $client = $this->createClient();

        $email = ClientEmail::create([
            'client_id' => $client->id,
            'admin_id' => $staff->id,
            'email_type' => 'Personal',
            'email' => 'client@example.com',
            'is_verified' => false,
        ]);

        $response = $this->actingAs($staff, 'admin')
            ->postJson('/update-email-verified', [
                'client_id' => $client->id,
                'manual_email_phone_verified' => 1,
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => true,
                'success' => true,
                'is_verified' => true,
            ]);

        $this->assertTrue($email->refresh()->is_verified);
    }

    private function createStaff(): Staff
    {
        return Staff::create([
            'first_name' => 'Verify',
            'last_name' => 'Staff',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => 1,
            'status' => 1,
        ]);
    }

    private function createClient(): Admin
    {
        return Admin::create([
            'first_name' => 'Verify',
            'last_name' => 'Client',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'type' => 'client',
        ]);
    }
}
