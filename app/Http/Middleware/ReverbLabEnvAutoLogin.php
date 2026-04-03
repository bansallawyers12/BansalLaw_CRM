<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * For /reverb-messaging-test routes only: if the visitor is not logged in as
 * staff (admin guard), attempt login using REVERB_ACCESS_* from .env.
 */
class ReverbLabEnvAutoLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('admin')->check()) {
            return $next($request);
        }

        $email = config('reverb_lab.access_login');
        $password = config('reverb_lab.access_password');

        if ($email === null || $email === '' || $password === null || $password === '') {
            return redirect()->guest(route('crm.login'));
        }

        if (Auth::guard('admin')->attempt(['email' => $email, 'password' => $password], false)) {
            $request->session()->regenerate();

            return $next($request);
        }

        return redirect()->guest(route('crm.login'));
    }
}
