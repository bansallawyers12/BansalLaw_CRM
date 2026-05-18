<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

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
		if ($exception instanceof MethodNotAllowedHttpException)
		{
			//return Redirect::to('/exception')->with('error', config('constants.exception'));
		}
		/* if ($this->isHttpException($exception))
		{
			if ($exception->getStatusCode() == 404)
				{
					return response()->view('errors.' . '404', [], 404);
				}
		} */
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
		
		// AJAX DataTables loaders must not receive an HTML redirect (invalid JSON → client error).
		if ($request->is('action/list')) {
			return response()->json([
				'success' => false,
				'message' => 'Unauthenticated.',
				'draw' => (int) $request->input('draw', 0),
				'recordsTotal' => 0,
				'recordsFiltered' => 0,
				'data' => [],
			], 401);
		}

		if ($request->is('action/counts')) {
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
