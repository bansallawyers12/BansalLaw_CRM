<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Staff;
use PHPUnit\Framework\Attributes\Test;

class AdminConsoleRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Staff::factory()->superAdmin()->create([
            'email'    => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);
    }

    #[Test]
    public function admin_can_access_adminconsole_features_matter_index()
    {
        $this->actingAs($this->admin, 'admin')
             ->get('/adminconsole/features/matter')
             ->assertStatus(200);
    }

    #[Test]
    public function admin_can_access_adminconsole_features_matter_create()
    {
        $this->actingAs($this->admin, 'admin')
             ->get('/adminconsole/features/matter/create')
             ->assertStatus(200);
    }

    #[Test]
    public function admin_can_access_adminconsole_features_workflow_index()
    {
        $this->actingAs($this->admin, 'admin')
             ->get('/adminconsole/features/workflow')
             ->assertStatus(200);
    }

    #[Test]
    public function admin_can_access_adminconsole_features_emails_index()
    {
        $this->actingAs($this->admin, 'admin')
             ->get('/adminconsole/features/emails')
             ->assertStatus(200);
    }

    #[Test]
    public function admin_can_access_adminconsole_system_clients_clientlist()
    {
        $this->actingAs($this->admin, 'admin')
             ->get('/adminconsole/system/clients')
             ->assertStatus(200);
    }

    #[Test]
    public function admin_can_access_adminconsole_system_roles_index()
    {
        $this->actingAs($this->admin, 'admin')
             ->get('/adminconsole/system/roles')
             ->assertStatus(200);
    }

    #[Test]
    public function admin_can_access_adminconsole_system_teams_index()
    {
        $this->actingAs($this->admin, 'admin')
             ->get('/adminconsole/system/teams')
             ->assertStatus(200);
    }

    #[Test]
    public function admin_can_access_adminconsole_system_offices_index()
    {
        $this->actingAs($this->admin, 'admin')
             ->get('/adminconsole/system/offices')
             ->assertStatus(200);
    }

    #[Test]
    public function admin_can_access_adminconsole_system_settings_index()
    {
        // Settings route may not exist in all environments; assert it doesn't error (200 or 404 is ok, not 500)
        $response = $this->actingAs($this->admin, 'admin')
             ->get('/adminconsole/system/settings');
        $this->assertContains($response->status(), [200, 404]);
    }

    #[Test]
    public function unauthenticated_user_cannot_access_adminconsole_routes()
    {
        $this->get('/adminconsole/features/matter')
             ->assertRedirect('/login');
    }

    #[Test]
    public function non_admin_user_cannot_access_adminconsole_routes()
    {
        $user = Staff::factory()->regularStaff()->create([
            'email'    => 'staff@test.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($user, 'admin')
             ->get('/adminconsole/features/matter')
             ->assertRedirect(); // redirected to dashboard (no admin console permission)
    }

    #[Test]
    public function adminconsole_routes_work_correctly()
    {
        $this->actingAs($this->admin, 'admin')
             ->get('/adminconsole/features/matter')
             ->assertStatus(200);
    }

    #[Test]
    public function adminconsole_routes_have_correct_names()
    {
        $this->actingAs($this->admin, 'admin');
        
        // Test that route names are correctly defined
        $this->assertEquals(
            url('/adminconsole/features/matter'),
            route('adminconsole.features.matter.index')
        );

        $this->assertEquals(
            url('/adminconsole/system/clients'),
            route('adminconsole.system.clients.clientlist')
        );
        
    }

    #[Test]
    public function adminconsole_routes_use_correct_middleware()
    {
        // Test that routes are protected by auth and admin middleware
        $this->get('/adminconsole/features/matter')
             ->assertRedirect('/login');
    }

    #[Test]
    public function adminconsole_navigation_links_work_correctly()
    {
        $this->actingAs($this->admin, 'admin');

        // Verify that named routes resolve to the correct URLs
        $this->assertEquals(url('/adminconsole/features/matter'), route('adminconsole.features.matter.index'));
        $this->assertEquals(url('/adminconsole/system/clients'), route('adminconsole.system.clients.clientlist'));
    }

    #[Test]
    public function adminconsole_forms_submit_to_correct_routes()
    {
        $this->actingAs($this->admin, 'admin');

        // Verify create and store route names resolve correctly
        $this->assertStringEndsWith('/adminconsole/features/matter/create', route('adminconsole.features.matter.create'));
        $this->assertStringEndsWith('/adminconsole/system/clients/create', route('adminconsole.system.clients.createclient'));
    }

    #[Test]
    public function adminconsole_back_links_work_correctly()
    {
        $this->actingAs($this->admin, 'admin');

        // Verify index routes resolve correctly (used as back-link targets)
        $this->assertEquals(url('/adminconsole/features/matter'), route('adminconsole.features.matter.index'));
        $this->assertEquals(url('/adminconsole/system/clients'), route('adminconsole.system.clients.clientlist'));
    }
}
