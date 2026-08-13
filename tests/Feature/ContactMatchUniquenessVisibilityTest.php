<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContactMatchUniquenessVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Auth::guard('admin')->logout();

        \Illuminate\Support\Facades\DB::table('user_roles')->insertOrIgnore([
            ['id' => 1, 'name' => 'Admin', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Staff', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    #[Test]
    public function unauthenticated_user_cannot_probe_client_existence(): void
    {
        Admin::create([
            'first_name' => 'Secret',
            'last_name' => 'Client',
            'email' => 'secret@example.com',
            'phone' => '0400111222',
            'password' => bcrypt('password'),
            'type' => 'client',
        ]);

        // Endpoints require authentication; unauthenticated requests fail/redirect
        $response = $this->get('/checkclientexist?type=email&vl=secret@example.com');
        $this->assertTrue($response->isRedirect() || $response->status() === 401);

        $response = $this->post('/check-email', ['email' => 'secret@example.com']);
        $this->assertTrue($response->isRedirect() || $response->status() === 401);

        $response = $this->post('/check.phone', ['phone' => '0400111222']);
        $this->assertTrue($response->isRedirect() || $response->status() === 401);

        $response = $this->get('/api/search-contact-person?q=secret@example.com');
        $this->assertTrue($response->isRedirect() || $response->status() === 401);
    }

    #[Test]
    public function restricted_staff_cannot_probe_unassigned_client_existence_or_pii(): void
    {
        $staff1 = Staff::create([
            'first_name' => 'Staff1',
            'last_name' => 'User',
            'email' => 'staff1@example.com',
            'password' => bcrypt('password'),
            'role' => 2, // Restricted staff
        ]);

        $client = Admin::create([
            'first_name' => 'Private',
            'last_name' => 'Client',
            'email' => 'private.client@example.com',
            'phone' => '0499888777',
            'password' => bcrypt('password'),
            'type' => 'client',
            'assignee_id' => 99999, // Assigned to different staff
        ]);

        $this->actingAs($staff1, 'admin');

        // checkclientexist returns 0
        $response = $this->get('/checkclientexist?type=email&vl=private.client@example.com');
        $response->assertSee('0');

        // checkEmail returns available
        $response = $this->post('/check-email', ['email' => 'private.client@example.com']);
        $response->assertJson(['status' => 'available']);

        // checkContact returns available
        $response = $this->post('/check.phone', ['phone' => '0499888777']);
        $response->assertJson(['status' => 'available']);

        // searchContactPerson returns empty array
        $response = $this->get('/api/search-contact-person?q=private.client@example.com');
        $response->assertJson(['results' => []]);

        // checkContactMatch returns found: false
        $response = $this->get('/leads/check-contact-match?email=private.client@example.com');
        $response->assertJson(['found' => false, 'person' => null]);
    }

    #[Test]
    public function admin_or_assigned_staff_can_match_accessible_client(): void
    {
        $admin = Staff::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 1, // Admin role
        ]);

        $client = Admin::create([
            'first_name' => 'Accessible',
            'last_name' => 'Client',
            'email' => 'accessible@example.com',
            'phone' => '0411222333',
            'password' => bcrypt('password'),
            'type' => 'client',
        ]);

        $this->actingAs($admin, 'admin');

        // checkclientexist returns 1
        $response = $this->get('/checkclientexist?type=email&vl=accessible@example.com');
        $response->assertSee('1');

        // checkEmail returns exists
        $response = $this->post('/check-email', ['email' => 'accessible@example.com']);
        $response->assertJson(['status' => 'exists']);

        // searchContactPerson returns candidate details
        $response = $this->get('/api/search-contact-person?q=accessible@example.com');
        $response->assertJsonFragment(['email' => 'accessible@example.com']);

        // checkContactMatch returns found: true
        $response = $this->get('/leads/check-contact-match?email=accessible@example.com');
        $response->assertJsonFragment(['email' => 'accessible@example.com']);
    }
}
