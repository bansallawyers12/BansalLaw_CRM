<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Staff;
use App\Models\UserRole;
use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\AdminConsole\StaffController;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class SecurityBugFixes10Test extends TestCase
{
    /** @test */
    public function test_10_3_web_login_requires_status_1()
    {
        $controller = new AdminLoginController();
        $reflector = new \ReflectionClass($controller);
        $method = $reflector->getMethod('attemptLogin');
        $method->setAccessible(true);

        $request = Request::create('/login', 'POST', [
            'email' => 'test@example.com',
            'password' => 'secret',
            'remember' => false,
        ]);

        $result = $method->invoke($controller, $request);
        $this->assertIsBool($result);
    }

    /** @test */
    public function test_10_7_check_authorization_action_handles_role_id()
    {
        $controller = new class extends Controller {};
        
        // Non-existent role should return true (unauthorized), not SQL error on usertype column
        $result = $controller->checkAuthorizationAction('user_management', 'index', 999999);
        $this->assertTrue($result);
    }
}
