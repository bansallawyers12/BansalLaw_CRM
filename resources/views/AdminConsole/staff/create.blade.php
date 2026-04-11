@extends('layouts.crm_client_detail')
@section('title', 'Staff')

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
			<form action="{{ route('adminconsole.staff.store') }}" name="edit-staff" autocomplete="off" enctype="multipart/form-data" method="POST">
                @csrf
                <div class="row">
					<div class="col-12 col-md-12 col-lg-12">
						<div class="card">
							<div class="card-header">
								<h4>Create Staff</h4>
								<div class="card-header-action">
									<a href="{{ route('adminconsole.staff.active') }}" class="btn btn-primary"><i class="fa fa-arrow-left"></i> Back</a>
								</div>
							</div>
						</div>
					</div>
					<div class="col-12 col-md-6 col-lg-6">
						<div class="card">
							<div class="card-body">
								<h4>PERSONAL DETAILS</h4>
								<div class="form-group">
									<label for="first_name">First Name</label>
									<input type="text" name="first_name" value="{{ old('first_name') }}" class="form-control" data-valid="required" autocomplete="off" placeholder="Enter Staff First Name">
									@if ($errors->has('first_name'))
										<span class="custom-error" role="alert">
											<strong>{{ $errors->first('first_name') }}</strong>
										</span>
									@endif
								</div>
								<div class="form-group">
									<label for="last_name">Last Name</label>
									<input type="text" name="last_name" value="{{ old('last_name') }}" class="form-control" data-valid="required" autocomplete="off" placeholder="Enter Staff Last Name">
									@if ($errors->has('last_name'))
										<span class="custom-error" role="alert">
											<strong>{{ $errors->first('last_name') }}</strong>
										</span>
									@endif
								</div>
								<div class="form-group">
									<label for="email">Email</label>
									<input type="text" name="email" value="{{ old('email') }}" class="form-control" data-valid="required email" autocomplete="off" placeholder="Enter Email">
									@if ($errors->has('email'))
										<span class="custom-error" role="alert">
											<strong>{{ $errors->first('email') }}</strong>
										</span>
									@endif
								</div>

								<div class="form-group">
									<label for="password">Password (CRM login)</label>
									<input type="password" name="password" class="form-control" autocomplete="new-password" placeholder="Min. 8 characters" data-valid="required" />
									<small class="form-text text-muted">Staff use this email and password at <a href="{{ url('/login') }}" target="_blank" rel="noopener">the CRM login page</a>.</small>
									@if ($errors->has('password'))
										<span class="custom-error" role="alert">
											<strong>{{ $errors->first('password') }}</strong>
										</span>
									@endif
								</div>
								<div class="form-group">
									<label for="password_confirmation">Confirm Password</label>
									<input type="password" name="password_confirmation" class="form-control" autocomplete="new-password" placeholder="Repeat password" data-valid="required" />
									@if ($errors->has('password_confirmation'))
										<span class="custom-error" role="alert">
											<strong>{{ $errors->first('password_confirmation') }}</strong>
										</span>
									@endif
								</div>
								<div class="form-group">
									<label for="phone">Phone Number</label>
									<div class="cus_field_input">
									<div class="country_code">
										<input class="telephone" id="telephone" type="tel" name="country_code" readonly value="{{ old('country_code', '') }}" >
									</div>
									<input type="text" name="phone" value="{{ old('phone') }}" class="form-control tel_input" data-valid="required" autocomplete="off" placeholder="Enter Phone">
									@if ($errors->has('phone'))
										<span class="custom-error" role="alert">
											<strong>{{ $errors->first('phone') }}</strong>
										</span>
									@endif
								</div>

								</div>
							</div>
						</div>
					</div>
					<div class="col-12 col-md-6 col-lg-6">
						<div class="card">
							<div class="card-body">
								<h4>Office DETAILS</h4>
								<div class="form-group">
									<label for="position">Position Title</label>
									<input type="text" name="position" value="{{ old('position') }}" class="form-control" data-valid="" autocomplete="off" placeholder="Enter Position Title">
									@if ($errors->has('position'))
										<span class="custom-error" role="alert">
											<strong>{{ $errors->first('position') }}</strong>
										</span>
									@endif
								</div>

                                <div class="form-group">
									<label for="role">User Role (Type)</label>
									<select name="role" id="role" class="form-control" data-valid="required" autocomplete="new-password">
										<option value="">Choose One...</option>
										@foreach ($usertype as $ut)
											<option value="{{ $ut->id }}" @if(old('role') == $ut->id) selected @endif>{{ $ut->name }}</option>
										@endforeach
									</select>
									@if ($errors->has('role'))
										<span class="custom-error" role="alert">
											<strong>{{ $errors->first('role') }}</strong>
										</span>
									@endif
								</div>

								<div class="form-group">
                                    @php $branchx = \App\Models\Branch::query()->orderBy('office_name')->get(); @endphp
									<label for="office">Office</label>
									<select class="form-control" data-valid="required" name="office" id="office">
										<option value="">Select</option>
										@foreach($branchx as $branch)
											<option value="{{ $branch->id }}" @if(old('office') == $branch->id) selected @endif>{{ $branch->office_name }}</option>
										@endforeach
									</select>
									@if ($errors->has('office'))
										<span class="custom-error" role="alert">
											<strong>{{ $errors->first('office') }}</strong>
										</span>
									@endif
								</div>

								<div class="form-group">
									<label for="team">Department (Team)</label>
									<select name="team" id="team" class="form-control" autocomplete="new-password">
										<option value="">Choose One...</option>
										@foreach (\App\Models\Team::query()->orderBy('name')->get() as $tm)
											<option value="{{ $tm->id }}" @if(old('team') == $tm->id) selected @endif>{{ $tm->name }}</option>
										@endforeach
									</select>
                                </div>

                                @php
                                    $_qaActor = auth()->guard('admin')->user();
                                    $_canQa = $_qaActor instanceof \App\Models\Staff
                                        && app(\App\Services\CrmAccess\CrmAccessService::class)->canManageStaffQuickAccess($_qaActor);
                                @endphp
                                @if($_canQa)
                                <div class="form-group">
                                    <label class="d-flex align-items-center mb-0">
                                        <input type="hidden" name="quick_access_enabled" value="0">
                                        <input type="checkbox" name="quick_access_enabled" value="1" class="mr-2"
                                            @if(old('quick_access_enabled')) checked @endif>
                                        <span>Quick access enabled ({{ config('crm_access.quick_grant_minutes', 15) }}-minute cross-access requests)</span>
                                    </label>
                                    <small class="text-muted d-block mt-1">Super Admin or access approver. Enabling grants this staff member the ability to request cross-access.</small>
                                </div>
                                @endif

                                <div class="form-group">
                                    <label for="role">Permission</label>
							    	<br><b>Notes</b>  &nbsp;&nbsp;&nbsp;&nbsp;
                                    <input value="1" type="checkbox" name="permission[]" class="show_dashboard_per">&nbsp; View &nbsp;
                                    <input value="2" type="checkbox" name="permission[]" class="show_dashboard_per">&nbsp; Add/Edit &nbsp;
                                    <input value="3" type="checkbox" name="permission[]" class="show_dashboard_per">&nbsp; Delete &nbsp;

                                    <br><b>Documents</b>
                                    <input value="4" type="checkbox" name="permission[]" class="show_dashboard_per">&nbsp; View &nbsp;
                                    <input value="5" type="checkbox" name="permission[]" class="show_dashboard_per">&nbsp; Add/Edit &nbsp;
                                    <input value="6" type="checkbox" name="permission[]" class="show_dashboard_per">&nbsp; Delete &nbsp;
                                </div>

                                @include('AdminConsole.staff.partials.sheet-access', ['sheetDefinitions' => $sheetDefinitions, 'selectedSheetKeys' => $selectedSheetKeys])

								<div class="form-group">
							    	<label><input value="1" type="checkbox" name="show_dashboard_per" class="show_dashboard_per"> Can view on dasboard</label>
								</div>
							</div>
						</div>
					</div>

					<!-- Legal Practitioner details section -->
					<div class="col-12 col-md-12 col-lg-12">
						<div class="card">
							<div class="card-body">
								<div class="form-group">
									<label class="d-flex align-items-center">
										<input type="checkbox" id="is_solicitor" name="is_solicitor" value="1" class="mr-2" @if(old('is_solicitor')) checked @endif>
										<h5 class="mb-0">Is this staff a Legal Practitioner?</h5>
									</label>
								</div>

								<!-- Agent Details Fields (Hidden by default) -->
								<div id="agent_details_section" style="display: {{ old('is_solicitor') ? 'block' : 'none' }};">
									<hr>
									<h6 class="text-primary mb-3">Legal Practitioner registration details</h6>
									
									<div class="row">
										<div class="col-md-6">
											<div class="form-group">
												<label for="marn_number">MARN Number</label>
												<input type="text" name="marn_number" id="marn_number" value="{{ old('marn_number') }}" class="form-control" placeholder="Enter MARN Number">
											</div>
										</div>
									</div>

									<h6 class="text-primary mb-3 mt-4">Business Details</h6>
									
									<div class="row">
										<div class="col-md-6">
											<div class="form-group">
												<label for="company_name">Business Name</label>
												<input type="text" name="company_name" value="{{ old('company_name') }}" class="form-control" placeholder="Enter Business Name">
											</div>
										</div>
										<div class="col-md-6">
											<div class="form-group">
												<label for="tax_number">Tax Number (ABN/ACN)</label>
												<input type="text" name="tax_number" value="{{ old('tax_number') }}" class="form-control" placeholder="Enter Tax Number">
											</div>
										</div>
									</div>

									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="business_address">Business Address</label>
												<textarea name="business_address" class="form-control" rows="2" placeholder="Enter Business Address">{{ old('business_address') }}</textarea>
											</div>
										</div>
									</div>

									<div class="row">
										<div class="col-md-4">
											<div class="form-group">
												<label for="business_phone">Business Phone</label>
												<input type="text" name="business_phone" value="{{ old('business_phone') }}" class="form-control" placeholder="Enter Business Phone">
											</div>
										</div>
										<div class="col-md-4">
											<div class="form-group">
												<label for="business_mobile">Business Mobile</label>
												<input type="text" name="business_mobile" value="{{ old('business_mobile') }}" class="form-control" placeholder="Enter Business Mobile">
											</div>
										</div>
										<div class="col-md-4">
											<div class="form-group">
												<label for="business_email">Business Email</label>
												<input type="email" name="business_email" value="{{ old('business_email') }}" class="form-control" placeholder="Enter Business Email">
											</div>
										</div>
									</div>

								</div>
							</div>
						</div>
					</div>

					<div class="col-12">
						<div class="form-group float-right">
							<input type="submit" value="Save Staff" class="btn btn-primary">
						</div>
							</div>
						</div>
					</div>
				</div>
			</form>
		</div>
	</section>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
	// Toggle Legal Practitioner details section
	$('#is_solicitor').on('change', function() {
		if ($(this).is(':checked')) {
			$('#agent_details_section').slideDown();
		} else {
			$('#agent_details_section').slideUp();
		}
	});

    // Scroll to the first error banner or inline error on redirect-back
    @if($errors->any())
    var $firstError = $('.server-error .alert-danger, .custom-error').first();
    if ($firstError.length) {
        $('html, body').animate({ scrollTop: $firstError.offset().top - 80 }, 400);
    }
    @endif
});
</script>
@endsection
