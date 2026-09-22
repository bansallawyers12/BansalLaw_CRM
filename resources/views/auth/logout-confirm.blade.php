@extends('layouts.crm-login')

@section('title', 'Confirm Sign Out')

@section('content')
<div class="crm-login-wrapper">
	<div class="crm-login-card" style="max-width: 500px; margin: 0 auto; display: block;">
		<div class="crm-login-form-panel" style="width: 100%; padding: 40px 32px;">
			<div style="margin-bottom: 24px; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 12px;">
				<div aria-hidden="true" style="width: 52px; height: 52px; border-radius: 50%; background: #fee2e2; color: #dc2626; display: flex; align-items: center; justify-content: center; font-size: 22px;">
					<i class="fa-solid fa-right-from-bracket"></i>
				</div>
				<div>
					<div class="form-header-title" style="font-size: 22px; margin-bottom: 6px;">Sign Out of CRM?</div>
					<div class="form-header-sub" style="font-size: 13.5px;">To prevent accidental sign-out from links or bookmarks, please confirm your action below.</div>
				</div>
			</div>

			@if(!empty($user))
			<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 16px; margin-bottom: 24px; font-size: 13px; color: #475569; text-align: center;">
				Signed in as: <strong style="color: #0f172a;">{{ $user->name ?? $user->first_name ?? 'Staff User' }}</strong> ({{ $user->email }})
			</div>
			@endif

			<form action="{{ route('crm.logout') }}" method="post" style="display: flex; gap: 12px; flex-direction: column;">
				@csrf
				<button type="submit" class="btn-login-submit" style="background: #dc2626; border-color: #dc2626; justify-content: center;">
					<span>Log Out Everywhere</span>
					<i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
				</button>
				<a href="{{ route('dashboard') }}" class="btn btn-outline-secondary" style="display: flex; align-items: center; justify-content: center; height: 44px; font-size: 14px; font-weight: 500; border-radius: 8px; text-decoration: none;">
					<span>Cancel and Return to Dashboard</span>
				</a>
			</form>
		</div>
	</div>
</div>
@endsection
