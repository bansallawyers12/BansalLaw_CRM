<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as BaseVerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class VerifyCsrfTokenTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper to read the protected $except property from VerifyCsrfToken.
     */
    protected function getExceptList(): array
    {
        $reflection = new ReflectionClass(VerifyCsrfToken::class);
        $prop = $reflection->getProperty('except');
        $prop->setAccessible(true);
        $middleware = app(VerifyCsrfToken::class);

        return $prop->getValue($middleware);
    }

    /**
     * Helper to test inExceptArray protected method.
     */
    protected function isExcepted(Request $request): bool
    {
        $reflection = new ReflectionClass(BaseVerifyCsrfToken::class);
        $method = $reflection->getMethod('inExceptArray');
        $method->setAccessible(true);
        $middleware = app(VerifyCsrfToken::class);

        return $method->invoke($middleware, $request);
    }

    #[Test]
    public function csrf_except_array_contains_only_api_and_sms_webhook_prefixes(): void
    {
        $except = $this->getExceptList();

        $this->assertEquals([
            'api/*',
            'webhooks/sms/*',
        ], $except);
    }

    #[Test]
    public function stale_admin_and_redundant_get_routes_are_not_excepted(): void
    {
        $staleRoutes = [
            'admin/update_visit_purpose',
            'admin/update_visit_comment',
            'admin/attend_session',
            'admin/complete_session',
            'admin/update_task_comment',
            'admin/update_task_description',
            'admin/update_task_status',
            'admin/update_task_priority',
            'admin/updateduedate',
            'get-activities',
        ];

        $except = $this->getExceptList();

        foreach ($staleRoutes as $route) {
            $this->assertNotContains(
                $route,
                $except,
                "Route '{$route}' should not be in VerifyCsrfToken::\$except list."
            );
        }
    }

    #[Test]
    public function api_and_sms_webhooks_match_except_rule(): void
    {
        $apiReq = Request::create('/api/countries', 'POST');
        $this->assertTrue($this->isExcepted($apiReq));

        $smsReq = Request::create('/webhooks/sms/cellcast/status', 'POST');
        $this->assertTrue($this->isExcepted($smsReq));
    }

    #[Test]
    public function office_visit_and_task_routes_do_not_match_except_rule(): void
    {
        $visitReq1 = Request::create('/update_visit_purpose', 'POST');
        $this->assertFalse($this->isExcepted($visitReq1));

        $visitReq2 = Request::create('/attend_session', 'POST');
        $this->assertFalse($this->isExcepted($visitReq2));

        $visitReq3 = Request::create('/complete_session', 'POST');
        $this->assertFalse($this->isExcepted($visitReq3));

        $taskReq = Request::create('/tasks/update', 'POST');
        $this->assertFalse($this->isExcepted($taskReq));
    }

    #[Test]
    public function tokens_match_verifies_session_and_submitted_tokens(): void
    {
        $session = app('session.store');
        $session->start();
        $session->put('_token', 'valid-csrf-token-12345');

        $reflection = new ReflectionClass(BaseVerifyCsrfToken::class);
        $method = $reflection->getMethod('tokensMatch');
        $method->setAccessible(true);
        $middleware = app(VerifyCsrfToken::class);

        // Missing token fails
        $missingTokenReq = Request::create('/tasks/update', 'POST');
        $missingTokenReq->setLaravelSession($session);
        $this->assertFalse($method->invoke($middleware, $missingTokenReq));

        // Wrong token fails
        $wrongTokenReq = Request::create('/tasks/update', 'POST', ['_token' => 'invalid-token']);
        $wrongTokenReq->setLaravelSession($session);
        $this->assertFalse($method->invoke($middleware, $wrongTokenReq));

        // Correct token via _token field passes
        $validTokenReq = Request::create('/tasks/update', 'POST', ['_token' => 'valid-csrf-token-12345']);
        $validTokenReq->setLaravelSession($session);
        $this->assertTrue($method->invoke($middleware, $validTokenReq));

        // Correct token via X-CSRF-TOKEN header passes
        $headerTokenReq = Request::create('/tasks/update', 'POST', [], [], [], [
            'HTTP_X_CSRF_TOKEN' => 'valid-csrf-token-12345',
        ]);
        $headerTokenReq->setLaravelSession($session);
        $this->assertTrue($method->invoke($middleware, $headerTokenReq));
    }

    #[Test]
    public function middleware_throws_token_mismatch_exception_when_token_is_missing(): void
    {
        $session = app('session.store');
        $session->start();
        $session->put('_token', 'valid-csrf-token-12345');

        $request = Request::create('/tasks/update', 'POST');
        $request->setLaravelSession($session);

        // Subclass overrides runningUnitTests() to ensure real handle() enforcement is tested
        $middleware = new class(app(), app('encrypter')) extends VerifyCsrfToken {
            protected function runningUnitTests()
            {
                return false;
            }
        };

        $this->expectException(TokenMismatchException::class);
        $middleware->handle($request, function () {
            return response('OK');
        });
    }

    #[Test]
    public function api_post_is_allowed_through_csrf_middleware_without_token(): void
    {
        $request = Request::create('/api/countries', 'POST');
        $request->setLaravelSession(app('session.store'));

        $middleware = new class(app(), app('encrypter')) extends VerifyCsrfToken {
            protected function runningUnitTests()
            {
                return false;
            }
        };

        $response = $middleware->handle($request, function () {
            return response('OK');
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    #[Test]
    public function sms_webhook_post_is_allowed_through_csrf_middleware_without_token(): void
    {
        $request = Request::create('/webhooks/sms/cellcast/status', 'POST');
        $request->setLaravelSession(app('session.store'));

        $middleware = new class(app(), app('encrypter')) extends VerifyCsrfToken {
            protected function runningUnitTests()
            {
                return false;
            }
        };

        $response = $middleware->handle($request, function () {
            return response('OK');
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }
}
