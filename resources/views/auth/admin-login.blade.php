@extends('layouts.crm-login')

@section('title', 'Staff Login')

@section('content')
<div class="crm-login-wrapper">
	<div class="crm-login-card">
		<div class="crm-login-form-panel">
			<div class="login-brand">
				<div class="brand-header-icon" aria-hidden="true">
					<i class="fa-solid fa-scale-balanced"></i>
				</div>
				<div>
					<div class="login-brand-name">{{ config('app.login_brand') ?? config('app.name') }}</div>
					<div class="login-brand-sub">Staff login</div>
				</div>
			</div>

			<div class="form-header-title">Sign in</div>
			<div class="form-header-sub">Use your staff email and password.</div>

			<div class="alert-flash-wrap">
				@include('../Elements/flash-message')
			</div>

			<form action="{{ URL::to('login') }}" method="post" name="admin_login" autocomplete="on">
				<input type="hidden" name="_token" value="{{ csrf_token() }}">

				<!-- Email Input Group -->
				<div class="login-form-group">
					<label for="email" class="login-form-label">Email Address</label>
					<div class="login-input-wrap">
						<i class="fa-solid fa-envelope login-input-icon" aria-hidden="true"></i>
						<input id="email" 
						       type="email" 
						       class="login-input-control" 
						       name="email" 
						       placeholder="name@bansallawyers.com.au" 
						       tabindex="1" 
						       value="{{ (Cookie::get('email') != '' && !old('email')) ? Cookie::get('email') : old('email') }}" 
						       required 
						       autofocus>
					</div>
					@if ($errors->has('email'))
						<div style="color: #dc2626; font-size: 12.5px; margin-top: 6px; font-weight: 600;">
							{{ $errors->first('email') }}
						</div>
					@endif
				</div>

				<!-- Password Input Group -->
				<div class="login-form-group">
					<label for="password" class="login-form-label">Password</label>
					<div class="login-input-wrap">
						<i class="fa-solid fa-lock login-input-icon" aria-hidden="true"></i>
						<input id="password" 
						       type="password" 
						       class="login-input-control" 
						       name="password" 
						       placeholder="••••••••" 
						       tabindex="2" 
						       value="{{ old('password') }}" 
						       required>
						<button type="button" class="btn-toggle-password" id="togglePasswordBtn" title="Show/Hide Password" aria-label="Toggle password visibility">
							<i class="fa-solid fa-eye" id="togglePasswordIcon"></i>
						</button>
					</div>
					@if ($errors->has('password'))
						<div style="color: #dc2626; font-size: 12.5px; margin-top: 6px; font-weight: 600;">
							{{ $errors->first('password') }}
						</div>
					@endif
				</div>

				<!-- Google Recaptcha -->
				@if(config('services.recaptcha.key'))
					<div class="recaptcha-wrapper">
						<div class="g-recaptcha" data-sitekey="{{ config('services.recaptcha.key') }}"></div>
					</div>
					@if ($errors->has('g-recaptcha-response'))
						<div style="color: #dc2626; font-size: 12.5px; margin-top: -12px; margin-bottom: 16px; text-align: center; font-weight: 600;">
							Captcha verification is required.
						</div>
					@endif
				@endif

				<!-- Remember Me Option -->
				<div class="login-row-options">
					<label class="remember-check-wrap" for="remember-me">
						<input type="checkbox" 
						       name="remember" 
						       class="remember-check-input" 
						       tabindex="3" 
						       id="remember-me" 
						       @if(Cookie::get('email') != '' || old('remember')) checked @endif>
						<span>Remember Me</span>
					</label>
				</div>

				<!-- Submit Button -->
				<div class="login-form-group" style="margin-bottom: 0;">
					<button type="submit" class="btn-login-submit" tabindex="4">
						<span>Sign In</span>
						<i class="fa-solid fa-arrow-right-to-bracket" aria-hidden="true"></i>
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	const toggleBtn = document.getElementById('togglePasswordBtn');
	const passwordInput = document.getElementById('password');
	const toggleIcon = document.getElementById('togglePasswordIcon');

	if (toggleBtn && passwordInput && toggleIcon) {
		toggleBtn.addEventListener('click', function() {
			const isPassword = passwordInput.getAttribute('type') === 'password';
			passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
			toggleIcon.className = isPassword ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
		});
	}
});
</script>
@endsection
