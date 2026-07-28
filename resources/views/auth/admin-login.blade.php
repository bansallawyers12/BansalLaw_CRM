@extends('layouts.crm-login')

@section('title', 'Staff Login')

@section('content')
<div class="crm-login-wrapper">
	<div class="crm-login-card">
		<!-- Left Side Branding Panel -->
		<div class="crm-login-branding">
			<div class="brand-header-logo">
				<div class="brand-header-icon" aria-hidden="true">
					<i class="fa-solid fa-scale-balanced"></i>
				</div>
				<div>
					<div class="brand-header-text">{{ config('app.login_brand') ?? config('app.name') }}</div>
					<div class="brand-header-sub">Legal Practice Management</div>
				</div>
			</div>

			<div class="brand-body-content">
				<div class="brand-headline">Streamline Your Legal Operations</div>
				<div class="brand-subline">Access client matters, automated email sync, appointments, and practice analytics in one place.</div>

				<ul class="brand-features">
					<li class="brand-feature-item">
						<div class="brand-feature-icon" aria-hidden="true"><i class="fa-solid fa-check"></i></div>
						<span>Automated Zoho & Outlook Email Sync</span>
					</li>
					<li class="brand-feature-item">
						<div class="brand-feature-icon" aria-hidden="true"><i class="fa-solid fa-check"></i></div>
						<span>Client Matter & Document Management</span>
					</li>
					<li class="brand-feature-item">
						<div class="brand-feature-icon" aria-hidden="true"><i class="fa-solid fa-check"></i></div>
						<span>Calendar Scheduling & Conflict Check</span>
					</li>
				</ul>
			</div>

			<div class="brand-footer-text">
				<i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
				<span>Encrypted SSL 256-Bit Connection</span>
			</div>
		</div>

		<!-- Right Side Form Panel -->
		<div class="crm-login-form-panel">
			<div class="form-header-title">Staff Sign In</div>
			<div class="form-header-sub">Please enter your credentials to access your CRM account</div>

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
						       value="{{ (Cookie::get('password') != '' && !old('password')) ? Cookie::get('password') : old('password') }}" 
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
						       @if(Cookie::get('email') != '' && Cookie::get('password') != '') checked @endif>
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
