<?php

namespace Tests\Feature;

use App\Http\Kernel;
use App\Http\Middleware\SetSecureSessionCookies;
use App\Http\Middleware\TrustProxies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustProxies as BaseTrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class TrustProxiesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TrustProxies::flushState();
        parent::tearDown();
    }

    #[Test]
    public function kernel_registers_app_trust_proxies_in_global_middleware_stack(): void
    {
        $kernel = app(Kernel::class);
        $reflection = new ReflectionClass($kernel);
        $prop = $reflection->getProperty('middleware');
        $prop->setAccessible(true);
        $middleware = $prop->getValue($kernel);

        $this->assertContains(
            TrustProxies::class,
            $middleware,
            'Kernel global middleware must include App\Http\Middleware\TrustProxies::class'
        );

        $this->assertNotContains(
            BaseTrustProxies::class,
            $middleware,
            'Kernel global middleware should not directly reference Illuminate\Http\Middleware\TrustProxies::class'
        );
    }

    #[Test]
    public function app_trust_proxies_extends_framework_base_middleware(): void
    {
        $middleware = new TrustProxies();
        $this->assertInstanceOf(BaseTrustProxies::class, $middleware);
    }

    #[Test]
    public function trustedproxy_config_file_exists_and_defines_proxies_and_headers(): void
    {
        $this->assertTrue(config()->has('trustedproxy.proxies') || file_exists(config_path('trustedproxy.php')));
        $this->assertNotNull(config('trustedproxy.headers'));
    }

    #[Test]
    public function untrusted_proxy_headers_do_not_mark_request_as_secure(): void
    {
        Config::set('trustedproxy.proxies', null);
        TrustProxies::flushState();

        $request = Request::create('http://crm.bansallawyers.com.au/login', 'GET', [], [], [], [
            'REMOTE_ADDR' => '198.51.100.5',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.195',
        ]);

        $middleware = new TrustProxies();
        $middleware->handle($request, function ($req) {
            return response('OK');
        });

        $this->assertFalse($request->isSecure());
        $this->assertEquals('198.51.100.5', $request->ip());
    }

    #[Test]
    public function trusted_proxy_with_wildcard_recognizes_https_and_client_ip(): void
    {
        Config::set('trustedproxy.proxies', '*');
        TrustProxies::flushState();

        $request = Request::create('http://crm.bansallawyers.com.au/login', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.195',
            'HTTP_X_FORWARDED_PORT' => '443',
        ]);

        $middleware = new TrustProxies();
        $middleware->handle($request, function ($req) {
            return response('OK');
        });

        $this->assertTrue($request->isSecure());
        $this->assertEquals('203.0.113.195', $request->ip());
    }

    #[Test]
    public function trusted_proxy_with_specific_ip_recognizes_https_from_that_proxy(): void
    {
        Config::set('trustedproxy.proxies', '10.0.0.1, 192.168.1.5');
        TrustProxies::flushState();

        // Request from trusted proxy 10.0.0.1
        $trustedReq = Request::create('http://crm.bansallawyers.com.au/login', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.195',
        ]);

        $middleware = new TrustProxies();
        $middleware->handle($trustedReq, function ($req) {
            return response('OK');
        });

        $this->assertTrue($trustedReq->isSecure());
        $this->assertEquals('203.0.113.195', $trustedReq->ip());

        // Request from untrusted proxy 172.16.0.1
        $untrustedReq = Request::create('http://crm.bansallawyers.com.au/login', 'GET', [], [], [], [
            'REMOTE_ADDR' => '172.16.0.1',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.195',
        ]);

        $middleware->handle($untrustedReq, function ($req) {
            return response('OK');
        });

        $this->assertFalse($untrustedReq->isSecure());
        $this->assertEquals('172.16.0.1', $untrustedReq->ip());
    }

    #[Test]
    public function set_secure_session_cookies_activates_when_trusted_proxy_indicates_https(): void
    {
        Config::set('session.secure', false);
        Config::set('trustedproxy.proxies', '*');
        TrustProxies::flushState();

        $request = Request::create('http://crm.bansallawyers.com.au/login', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);

        // Step 1: TrustProxies runs globally
        $trustMiddleware = new TrustProxies();
        $trustMiddleware->handle($request, function ($req) {
            // Step 2: SetSecureSessionCookies runs in web group
            $secureCookieMiddleware = new SetSecureSessionCookies();
            $secureCookieMiddleware->handle($req, function () {
                return response('OK');
            });

            return response('OK');
        });

        $this->assertTrue($request->isSecure());
        $this->assertTrue(config('session.secure'), 'session.secure must be upgraded to true when trusted proxy reports HTTPS');
    }
}
