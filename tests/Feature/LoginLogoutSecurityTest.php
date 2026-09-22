<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Models\StaffLoginLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LoginLogoutSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function createStaff(int $id = 919, string $email = 'security-test@bansallawyers.com.au'): Staff
    {
        \Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
            ['id' => 1],
            ['name' => 'Admin', 'created_at' => now(), 'updated_at' => now()]
        );

        $staff = new Staff();
        $staff->id = $id;
        $staff->first_name = 'Security';
        $staff->last_name = 'Tester';
        $staff->email = $email;
        $staff->password = bcrypt('Secret123!');
        $staff->role = 1;
        $staff->status = 1;
        $staff->save();

        return $staff;
    }

    #[Test]
    public function get_logout_when_unauthenticated_redirects_to_login(): void
    {
        $response = $this->get('/logout');
        $response->assertRedirect(route('crm.login'));
    }

    #[Test]
    public function get_logout_when_authenticated_does_not_destroy_session_and_presents_confirmation(): void
    {
        $staff = $this->createStaff(921, 'staff921@bansallawyers.com.au');
        $this->actingAs($staff, 'admin');

        $this->assertTrue(Auth::guard('admin')->check());

        $response = $this->get('/logout');

        // Session must NOT be destroyed by GET request (mitigates CSRF-logout and prefetch attacks)
        $this->assertTrue(Auth::guard('admin')->check());
        $response->assertStatus(200);
        $response->assertViewIs('auth.logout-confirm');
        $response->assertSee('Sign Out of CRM?');
        $response->assertSee($staff->email);
        $response->assertSee(route('crm.logout'));
    }

    #[Test]
    public function get_logout_with_json_request_returns_method_not_allowed_405(): void
    {
        $staff = $this->createStaff(922, 'staff922@bansallawyers.com.au');
        $this->actingAs($staff, 'admin');

        $response = $this->getJson('/logout');
        $response->assertStatus(405);
        $this->assertTrue(Auth::guard('admin')->check());
    }

    #[Test]
    public function post_logout_destroys_session_and_creates_audit_log(): void
    {
        $staff = $this->createStaff(923, 'staff923@bansallawyers.com.au');
        $this->actingAs($staff, 'admin');

        $response = $this->post('/logout');

        $response->assertRedirect(route('crm.login'));
        $this->assertFalse(Auth::guard('admin')->check());

        $this->assertDatabaseHas('staff_login_logs', [
            'user_id' => 923,
            'message' => 'Logged out successfully',
        ]);
    }

    #[Test]
    public function login_view_has_csrf_token_and_does_not_repopulate_password(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);

        // Form action & CSRF
        $response->assertSee(route('crm.login.post'));
        $response->assertSee('name="_token"', false);

        // Autocomplete attributes
        $response->assertSee('autocomplete="email"', false);
        $response->assertSee('autocomplete="current-password"', false);

        // Password input should not have a value attribute repopulating password
        $this->assertStringNotContainsString('value="{{ old(\'password\') }}"', $response->getContent());
        $this->assertStringNotContainsString('name="password" value=', $response->getContent());
    }

    #[Test]
    public function authenticated_login_queues_http_only_cookie_when_remember_me_is_checked(): void
    {
        $staff = $this->createStaff(924, 'remember@bansallawyers.com.au');

        $request = Request::create('/login', 'POST', [
            'email' => 'remember@bansallawyers.com.au',
            'password' => 'Secret123!',
            'remember' => '1',
        ]);

        $controller = new \App\Http\Controllers\Auth\AdminLoginController();
        $controller->authenticated($request, $staff);

        $cookies = Cookie::getQueuedCookies();

        $emailCookie = null;
        foreach ($cookies as $c) {
            if ($c->getName() === 'email') {
                $emailCookie = $c;
                break;
            }
        }

        $this->assertNotNull($emailCookie, 'An email cookie should be queued when remember is checked');
        $this->assertEquals('remember@bansallawyers.com.au', $emailCookie->getValue());
        $this->assertTrue($emailCookie->isHttpOnly(), 'Remember-me email cookie must be HttpOnly');
        $this->assertEquals('lax', $emailCookie->getSameSite(), 'Cookie SameSite should be lax');
    }

    #[Test]
    public function authenticated_login_forgets_email_cookie_when_remember_is_not_checked(): void
    {
        $staff = $this->createStaff(925, 'noremember@bansallawyers.com.au');

        $request = Request::create('/login', 'POST', [
            'email' => 'noremember@bansallawyers.com.au',
            'password' => 'Secret123!',
        ]);

        $controller = new \App\Http\Controllers\Auth\AdminLoginController();
        $controller->authenticated($request, $staff);

        $cookies = Cookie::getQueuedCookies();

        $emailCookie = null;
        foreach ($cookies as $c) {
            if ($c->getName() === 'email') {
                $emailCookie = $c;
                break;
            }
        }

        $this->assertNotNull($emailCookie);
        $this->assertTrue($emailCookie->isCleared() || $emailCookie->getExpiresTime() < time(), 'Email cookie should be expired/cleared');
    }
}
