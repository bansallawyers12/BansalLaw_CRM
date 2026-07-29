<?php
namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\IpUtils;
use Cookie;

class AdminLoginController extends Controller
{
    protected $redirectTo = '/dashboard';

    public function __construct()
    {
        $this->middleware('guest:admin')->except('logout');
    }

    public function username(): string
    {
        return 'email';
    }

    protected function guard()
    {
        return Auth::guard('admin');
    }

    public function showLoginForm()
    {
        return view('auth.admin-login');
    }

    public function login(Request $request): mixed
    {
        $this->validateLogin($request);

        $throttleKey = Str::lower($request->input($this->username())) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return redirect()->back()
                ->withInput($request->only($this->username(), 'remember'))
                ->withErrors([
                    $this->username() => __('auth.throttle', [
                        'seconds' => RateLimiter::availableIn($throttleKey),
                        'minutes' => ceil(RateLimiter::availableIn($throttleKey) / 60),
                    ]),
                ]);
        }

        if ($this->attemptLogin($request)) {
            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();
            return $this->authenticated($request, $this->guard()->user())
                ?: redirect()->intended($this->redirectTo);
        }

        RateLimiter::hit($throttleKey, 60);

        return $this->sendFailedLoginResponse($request);
    }

    protected function attemptLogin(Request $request): bool
    {
        return $this->guard()->attempt(
            $request->only($this->username(), 'password'),
            $request->boolean('remember')
        );
    }

    protected function validateLogin(Request $request): void
    {
        $rules = [
            'email'    => 'required|string',
            'password' => 'required|string',
        ];

        if (!app()->environment('local') && config('services.recaptcha.key') && config('services.recaptcha.secret')) {
            $rules['g-recaptcha-response'] = 'required';
        }

        $request->validate($rules);
    }

    public function authenticated(Request $request, $user): mixed
    {
        if (!app()->environment('local') && config('services.recaptcha.key') && config('services.recaptcha.secret')) {
            $recaptcha_response = $request->input('g-recaptcha-response');
            if (is_null($recaptcha_response)) {
                return redirect()->back()
                    ->withErrors(['g-recaptcha-response' => 'Please Complete the Recaptcha to proceed']);
            }

            $body = [
                'secret'   => config('services.recaptcha.secret'),
                'response' => $recaptcha_response,
                'remoteip' => IpUtils::anonymize($request->ip()),
            ];

            $response = Http::get('https://www.google.com/recaptcha/api/siteverify', $body);
            $result   = json_decode($response);

            if (!$response->successful() || $result->success != true) {
                return redirect()->back()
                    ->withErrors(['g-recaptcha-response' => 'Please Complete the Recaptcha Again to proceed']);
            }
        }

        if (!empty($request->remember)) {
            \Cookie::queue(\Cookie::make('email', $request->email, 3600));
            \Cookie::queue(\Cookie::make('password', $request->password, 3600));
        } else {
            \Cookie::queue(\Cookie::forget('email'));
            \Cookie::queue(\Cookie::forget('password'));
        }

        $obj              = new \App\Models\StaffLoginLog;
        $obj->level       = 'info';
        $obj->user_id     = $user->id;
        $obj->ip_address  = $request->getClientIp();
        $obj->user_agent  = $_SERVER['HTTP_USER_AGENT'];
        $obj->message     = 'Logged in successfully';
        $obj->save();

        return redirect()->intended($this->redirectTo);
    }

    protected function sendFailedLoginResponse(Request $request): mixed
    {
        $errors = [$this->username() => trans('auth.failed')];

        $staff = \App\Models\Staff::where($this->username(), $request->{$this->username()})->first();

        if ($staff && !\Hash::check($request->password, $staff->password)) {
            $errors = ['password' => 'Wrong password'];
        }

        if ($request->expectsJson()) {
            return response()->json($errors, 422);
        }

        $obj             = new \App\Models\StaffLoginLog;
        $obj->level      = 'critical';
        $obj->user_id    = $staff ? $staff->id : null;
        $obj->ip_address = $request->getClientIp();
        $obj->user_agent = $_SERVER['HTTP_USER_AGENT'];
        $obj->message    = 'Invalid Email or Password !';
        $obj->save();

        return redirect()->back()
            ->withInput($request->only($this->username(), 'remember'))
            ->withErrors($errors);
    }

    public function logout(Request $request): mixed
    {
        $user = $request->id;

        $obj             = new \App\Models\StaffLoginLog;
        $obj->level      = 'info';
        $obj->user_id    = $user;
        $obj->ip_address = $request->getClientIp();
        $obj->user_agent = $_SERVER['HTTP_USER_AGENT'];
        $obj->message    = 'Logged out successfully';
        $obj->save();

        Auth::guard('admin')->logout();
        $request->session()->flush();
        $request->session()->regenerate();

        return redirect()->route('crm.login');
    }
}
