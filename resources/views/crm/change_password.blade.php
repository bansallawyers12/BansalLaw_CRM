@extends('layouts.crm_client_detail')
@section('title', 'Change Password')
@section('content')

<!-- Main Content -->
<div class="main-content">
	<section class="section">
		<div class="section-body">
			<div class="server-error">
				@include('../Elements/flash-message')
			</div>
			<div class="custom-error-msg">
			</div>
			<div class="row">
				<div class="col-12 col-md-10 col-lg-8">
					<div class="card">
						<div class="card-header">
							<h4>Change Password</h4>
						</div>
						<form action="{{ route('change_password.update') }}" method="POST" name="change-password" autocomplete="off">
							@csrf
							<input type="hidden" name="admin_id" value="{{ @Auth::user()->id }}">
							<div class="card-body">
								<div class="row">
									<div class="col-12 col-md-10">
										<div class="form-group mb-3">
											<label for="old_password">Current Password <span class="span_req">*</span></label>
											<div class="input-group">
												<input type="password" id="old_password" name="old_password" class="form-control" data-valid="required" placeholder="Enter current password" required autocomplete="current-password">
												<button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('old_password', 'old_password_icon')" aria-label="Toggle password visibility">
													<i class="fa-solid fa-eye" id="old_password_icon"></i>
												</button>
											</div>
											@if ($errors->has('old_password'))
												<span class="custom-error" role="alert">
													<strong>{{ $errors->first('old_password') }}</strong>
												</span>
											@endif
										</div>
										<div class="form-group mb-3">
											<label for="password">New Password <span class="span_req">*</span></label>
											<div class="input-group">
												<input type="password" id="password" name="password" class="form-control" data-valid="required" placeholder="Enter new password (min. 8 characters)" minlength="8" required autocomplete="new-password">
												<button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('password', 'password_icon')" aria-label="Toggle password visibility">
													<i class="fa-solid fa-eye" id="password_icon"></i>
												</button>
											</div>
											<small class="form-text text-muted">Must be at least 8 characters long and different from your current password.</small>
											@if ($errors->has('password'))
												<span class="custom-error" role="alert">
													<strong>{{ $errors->first('password') }}</strong>
												</span>
											@endif 
										</div>
										<div class="form-group mb-3">
											<label for="password_confirmation">Confirm New Password <span class="span_req">*</span></label>
											<div class="input-group">
												<input type="password" id="password_confirmation" name="password_confirmation" class="form-control" data-valid="required" placeholder="Confirm new password" minlength="8" required autocomplete="new-password">
												<button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('password_confirmation', 'password_confirmation_icon')" aria-label="Toggle password visibility">
													<i class="fa-solid fa-eye" id="password_confirmation_icon"></i>
												</button>
											</div>
											@if ($errors->has('password_confirmation'))
												<span class="custom-error" role="alert">
													<strong>{{ $errors->first('password_confirmation') }}</strong>
												</span>
											@endif
										</div>
										<div class="form-group mt-4">
											<button type="submit" class="btn btn-primary px-4" onClick="customValidate('change-password')"><i class="fa-solid fa-key"></i> Change Password</button>
										</div>
									</div>
								</div>
							</div>    
						</form>	
					</div>
				</div>
			</div>
		</div>
	</section>
</div>

<script>
function togglePasswordVisibility(fieldId, iconId) {
	const field = document.getElementById(fieldId);
	const icon = document.getElementById(iconId);
	if (field && icon) {
		const isPass = field.getAttribute('type') === 'password';
		field.setAttribute('type', isPass ? 'text' : 'password');
		icon.className = isPass ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
	}
}
</script>
@endsection