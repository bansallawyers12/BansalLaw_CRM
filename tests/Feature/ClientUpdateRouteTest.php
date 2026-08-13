<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientUpdateRouteTest extends TestCase
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
    public function post_clients_edit_accepts_raw_numeric_route_id(): void
    {
        $admin = Staff::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin.update@example.com',
            'password' => bcrypt('password'),
            'role' => 1,
        ]);

        $client = Admin::create([
            'first_name' => 'Test',
            'last_name' => 'Client',
            'email' => 'test.client@example.com',
            'password' => bcrypt('password'),
            'type' => 'client',
        ]);

        $this->actingAs($admin, 'admin');

        // POST /clients/edit/{id} with raw numeric ID in route
        $response = $this->post("/clients/edit/{$client->id}");
        $response->assertStatus(200);
        $response->assertViewIs('crm.clients.edit');
    }

    #[Test]
    public function post_clients_edit_accepts_id_in_request_body(): void
    {
        $admin = Staff::create([
            'first_name' => 'Admin2',
            'last_name' => 'User2',
            'email' => 'admin.update2@example.com',
            'password' => bcrypt('password'),
            'role' => 1,
        ]);

        $client = Admin::create([
            'first_name' => 'Test2',
            'last_name' => 'Client2',
            'email' => 'test.client2@example.com',
            'password' => bcrypt('password'),
            'type' => 'client',
        ]);

        $this->actingAs($admin, 'admin');

        // POST /clients/edit with id in request body
        $response = $this->post('/clients/edit', ['id' => $client->id]);
        $response->assertStatus(200);
        $response->assertViewIs('crm.clients.edit');
    }

    #[Test]
    public function unauthenticated_user_cannot_access_clients_update(): void
    {
        $client = Admin::create([
            'first_name' => 'Secret',
            'last_name' => 'Client',
            'email' => 'secret.update@example.com',
            'password' => bcrypt('password'),
            'type' => 'client',
        ]);

        $response = $this->post("/clients/edit/{$client->id}");
        $this->assertTrue($response->isRedirect() || $response->status() === 401);
    }
}
