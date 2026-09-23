<?php

namespace Tests\Feature;

use App\Http\Middleware\SetAdminGuardFromSanctumUser;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\HasApiTokens;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SessionAndSanctumConfigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\DB::table('user_roles')->insertOrIgnore([
            ['id' => 1, 'name' => 'Admin', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    #[Test]
    public function session_config_defaults_encrypt_to_false_and_is_configurable(): void
    {
        $sessionConfig = require config_path('session.php');

        $this->assertArrayHasKey('encrypt', $sessionConfig);
        $this->assertIsBool($sessionConfig['encrypt']);
        $this->assertFalse($sessionConfig['encrypt']);

        Config::set('session.encrypt', true);
        $this->assertTrue(config('session.encrypt'));
    }

    #[Test]
    public function session_config_defaults_domain_to_null(): void
    {
        $sessionConfig = require config_path('session.php');

        $this->assertArrayHasKey('domain', $sessionConfig);
        $this->assertNull($sessionConfig['domain'], 'Session domain should default to null instead of empty string');
    }

    #[Test]
    public function session_config_defaults_secure_to_null_for_https_autodetection(): void
    {
        $sessionConfig = require config_path('session.php');

        $this->assertArrayHasKey('secure', $sessionConfig);
        $this->assertNull(
            $sessionConfig['secure'],
            'Session secure should default to null to allow Laravel/Symfony to auto-detect HTTPS while allowing explicit overrides'
        );
    }

    #[Test]
    public function sanctum_config_guards_align_with_staff_provider(): void
    {
        $sanctumConfig = require config_path('sanctum.php');

        $this->assertArrayHasKey('guard', $sanctumConfig);
        $this->assertContains('admin', $sanctumConfig['guard'], 'Sanctum guard list must contain admin');
        $this->assertContains('web', $sanctumConfig['guard'], 'Sanctum guard list must contain web');

        $adminProvider = config('auth.guards.admin.provider');
        $webProvider = config('auth.guards.web.provider');

        $this->assertEquals('staff', $adminProvider, 'Admin guard provider must be staff');
        $this->assertEquals('staff', $webProvider, 'Web guard provider must be staff');

        $staffModel = config("auth.providers.{$adminProvider}.model");
        $this->assertEquals(Staff::class, $staffModel);

        $uses = class_uses_recursive($staffModel);
        $this->assertContains(
            HasApiTokens::class,
            $uses,
            'Staff model must use Laravel\Sanctum\HasApiTokens trait'
        );
    }

    #[Test]
    public function sanctum_config_expiration_defaults_to_seven_days_and_is_numeric(): void
    {
        $sanctumConfig = require config_path('sanctum.php');

        $this->assertArrayHasKey('expiration', $sanctumConfig);
        $this->assertIsInt($sanctumConfig['expiration']);
        $this->assertEquals(10080, $sanctumConfig['expiration'], 'Default Sanctum token expiration must be 10080 minutes (7 days)');
    }

    #[Test]
    public function sanctum_token_issued_to_staff_mirrors_onto_admin_guard(): void
    {
        $staff = Staff::create([
            'first_name' => 'Config',
            'last_name' => 'Tester',
            'email' => 'sanctum_test_' . uniqid() . '@example.com',
            'password' => bcrypt('Secret123!'),
            'status' => 1,
        ]);

        $token = $staff->createToken('TestServiceToken')->plainTextToken;
        $this->assertNotEmpty($token);

        // Simulate Sanctum authenticating request with user
        $request = Request::create('/api/test', 'GET');
        $request->setUserResolver(fn () => $staff);

        $middleware = new SetAdminGuardFromSanctumUser();
        $response = $middleware->handle($request, function ($req) {
            return response()->json(['admin_id' => Auth::guard('admin')->id()]);
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($staff->id, Auth::guard('admin')->id());
    }

    #[Test]
    public function session_config_supports_expire_on_close_and_encryption(): void
    {
        $sessionConfig = require config_path('session.php');

        $this->assertArrayHasKey('expire_on_close', $sessionConfig);
        $this->assertFalse($sessionConfig['expire_on_close']);

        Config::set('session.expire_on_close', true);
        $this->assertTrue(config('session.expire_on_close'));

        Config::set('session.encrypt', true);
        $this->assertTrue(config('session.encrypt'));
    }

    #[Test]
    public function service_account_token_generation_sets_expires_at_timestamp(): void
    {
        $staff = Staff::create([
            'first_name' => 'Service',
            'last_name' => 'Tester',
            'email' => 'service_token_' . uniqid() . '@example.com',
            'password' => bcrypt('Secret123!'),
            'role' => 1,
            'status' => 1,
        ]);

        $response = $this->postJson('/api/service-account/generate-token', [
            'service_name' => 'TestService',
            'description' => 'Test token expiration',
            'admin_email' => $staff->email,
            'admin_password' => 'Secret123!',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['token', 'expires_at']);

        $tokenRecord = \Laravel\Sanctum\PersonalAccessToken::where('tokenable_id', $staff->id)
            ->where('name', 'TestService')
            ->first();

        $this->assertNotNull($tokenRecord);
        $this->assertNotNull($tokenRecord->expires_at);
        $this->assertTrue($tokenRecord->expires_at->isFuture());
    }
}
