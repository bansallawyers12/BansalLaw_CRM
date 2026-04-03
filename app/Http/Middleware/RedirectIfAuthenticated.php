<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
	public function handle($request, Closure $next, $guard = null)
	{
			// Allow authenticated users to see login page when "logout this tab" sent them here
			if ($request->query('tab_logout')) {
				return $next($request);
			}
			switch ($guard) {
			case 'admin' :
				if (Auth::guard($guard)->check()) {
					return redirect()->route('dashboard');
				}
				break;
				default:
					if (Auth::guard($guard)->check()) {
						return redirect()->route('dashboard');
					}
					break;
			}
		 return $next($request);
	}
}
