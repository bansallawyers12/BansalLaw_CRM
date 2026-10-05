<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Arr;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
		\Illuminate\Auth\AuthenticationException::class,
		\Illuminate\Auth\Access\AuthorizationException::class,
		\Symfony\Component\HttpKernel\Exception\HttpException::class,
		\Illuminate\Database\Eloquent\ModelNotFoundException::class,
		\Illuminate\Session\TokenMismatchException::class,
		\Illuminate\Validation\ValidationException::class,
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Throwable $exception)
    {
        try {
            \App\Services\SystemBreakdownService::recordException($exception);
        } catch (\Throwable $loggingError) {
            // Absorb any logging error to ensure normal reporting is unaffected
        }

        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Throwable $exception)
    {
        // For system-errors monitor page: Show full diagnostic error even if APP_DEBUG=false
        if ($request->is('system-errors*')) {
            return response()->make(
                '<div style="background:#0b0f19; color:#f8fafc; font-family:sans-serif; padding:32px; min-height:100vh; line-height:1.6;">' .
                '<h2 style="color:#ef4444; font-size:22px; margin-bottom:12px;">⚠️ System Breakdown Monitor Diagnostic Error</h2>' .
                '<p style="color:#94a3b8; margin-bottom:16px;">Error intercepted on <code>/system-errors</code>:</p>' .
                '<div style="background:#1e293b; border-left:4px solid #ef4444; padding:16px; border-radius:6px; margin-bottom:20px;">' .
                '<p style="font-size:16px; color:#fca5a5; font-weight:bold;">' . htmlspecialchars($exception->getMessage()) . '</p>' .
                '<p style="font-family:monospace; color:#94a3b8; font-size:12px; margin-top:6px;">' . htmlspecialchars($exception->getFile()) . ':' . $exception->getLine() . '</p>' .
                '</div>' .
                '<h3 style="color:#93c5fd; font-size:15px; margin-bottom:8px;">Stack Trace:</h3>' .
                '<pre style="background:#050811; padding:16px; border-radius:8px; overflow:auto; font-size:11.5px; line-height:1.6; color:#cbd5e1; border:1px solid #1e293b; max-height:400px;">' .
                htmlspecialchars($exception->getTraceAsString()) .
                '</pre>' .
                '<div style="margin-top:20px; display:flex; gap:12px;">' .
                '<a href="' . url('/system-errors?tab=logs') . '" style="background:#3b82f6; color:#fff; padding:10px 18px; border-radius:6px; text-decoration:none; font-weight:bold;">📂 View Log Files Tab</a>' .
                '<a href="' . url('/system-errors') . '" style="background:#334155; color:#fff; padding:10px 18px; border-radius:6px; text-decoration:none;">🔄 Retry</a>' .
                '</div>' .
                '</div>',
                500
            );
        }

        if ($exception instanceof \Illuminate\Http\Exceptions\PostTooLargeException) {
            if ($request->expectsJson() || $request->ajax() || str_contains($request->path(), 'documents') || str_contains($request->path(), 'upload')) {
                return response()->json([
                    'status' => false,
                    'success' => false,
                    'message' => 'The uploaded file exceeds the server maximum upload limit (post_max_size).',
                ], 413);
            }
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            if ($exception instanceof NotFoundHttpException) {
                return response()->json([
                    'success' => false,
                    'message' => 'The requested resource was not found.',
                ], 404);
            }

            if ($exception instanceof HttpExceptionInterface && ! config('app.debug')) {
                $status = $exception->getStatusCode();
                if ($status >= 500) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Something went wrong. Please try again later.',
                    ], $status);
                }
            }
        }

        return parent::render($request, $exception);
    }

	protected function unauthenticated($request, AuthenticationException $exception)
	{
		// Check if this is an API request
		// API routes should always return JSON 401, not HTML redirects
		$isApiRoute = $request->is('api/*');
		
		// Also check if Authorization header with Bearer token is present
		// This indicates an API authentication attempt
		$hasBearerToken = $request->hasHeader('Authorization') && 
						  str_starts_with($request->header('Authorization', ''), 'Bearer ');
		
		// AJAX open-tasks loaders must not receive an HTML redirect (invalid JSON → client error).
		if ($request->is('tasks/list') || $request->is('action/list')) {
			return response()->json([
				'success' => false,
				'message' => 'Unauthenticated.',
				'html' => '',
				'current_page' => 1,
				'last_page' => 1,
				'per_page' => 20,
				'from' => 0,
				'to' => 0,
				'total' => 0,
				'has_more' => false,
				'next_page' => null,
				'counts' => [
					'all' => 0,
					'call' => 0,
					'checklist' => 0,
					'review' => 0,
					'query' => 0,
					'urgent' => 0,
					'personal_action' => 0,
					'follow_up' => 0,
				],
			], 401);
		}

		if ($request->is('tasks/counts') || $request->is('action/counts')) {
			return response()->json([
				'all' => 0,
				'call' => 0,
				'checklist' => 0,
				'review' => 0,
				'query' => 0,
				'urgent' => 0,
				'personal_action' => 0,
				'follow_up' => 0,
				'unauthenticated' => true,
			], 401);
		}

		// Return JSON 401 for API routes or requests with bearer tokens
		if ($request->expectsJson() || $isApiRoute || $hasBearerToken)
		{
			return response()->json([
				'success' => false,
				'message' => 'Unauthenticated.',
				'error' => 'Invalid or expired authentication token.'
			], 401);
		}
		
		// For web routes, redirect to login page
		$guard = Arr::get($exception->guards(), 0);

		switch ($guard)
		{
			case 'admin': $login = 'crm.login'; // Updated from admin.login
			break;
			default: $login = 'crm.login';
			break;
		}
        return redirect()->guest(route($login));
	}
}
