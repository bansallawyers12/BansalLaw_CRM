@extends('layouts.crm_client_detail')
@include('components.require-tinymce')
@section('title', 'Staff')

@section('content')
<!-- Main Content -->
<div class="main-content adminconsole-staff-page">
	<section class="section">
		<div class="section-body">
		<div class="server-error">
				@include('../Elements/flash-message')
			</div>
			<div class="custom-error-msg">
			</div>
			<form action="{{ route('adminconsole.staff.update', $fetchedData->id) }}" name="edit-staff" autocomplete="off" enctype="multipart/form-data" method="POST">
                @csrf
                @method('PUT')
				<div class="row">
					<div class="col-12 col-md-12 col-lg-12">
						<div class="card">
							<div class="card-header">
								<h4>Edit Staff</h4>
								<div class="card-header-action">
									<a href="{{ route('adminconsole.staff.active') }}" class="btn btn-primary"><i class="fa-solid fa-arrow-left"></i> Back</a>
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
									<input type="text" name="first_name" value="{{ old('first_name', @$fetchedData->first_name) }}" class="form-control" data-valid="required" autocomplete="off" placeholder="Enter Staff First Name">
                                    @if ($errors->has('first_name'))
										<span class="custom-error" role="alert">
											<strong>{{ @$errors->first('first_name') }}</strong>
										</span>
									@endif
								</div>
								<div class="form-group">
									<label for="last_name">Last Name</label>
									<input type="text" name="last_name" value="{{ old('last_name', @$fetchedData->last_name) }}" class="form-control" data-valid="required" autocomplete="off" placeholder="Enter Staff Last Name">
                                    @if ($errors->has('last_name'))
										<span class="custom-error" role="alert">
											<strong>{{ @$errors->first('last_name') }}</strong>
										</span>
									@endif
								</div>
								<div class="form-group">
									<label for="email">Email</label>
									<input type="text" name="email" value="{{ old('email', @$fetchedData->email) }}" class="form-control" data-valid="" autocomplete="off">
                                    @if ($errors->has('email'))
										<span class="custom-error" role="alert">
											<strong>{{ @$errors->first('email') }}</strong>
										</span>
									@endif
								</div>
								<div class="form-group">
									<label for="password">New Password (optional)</label>
									<input type="password" name="password" class="form-control" autocomplete="new-password" placeholder="Leave blank to keep current password" data-valid="" />
									<small class="form-text text-muted">Min. 8 characters. Staff sign in at <a href="{{ url('/login') }}" target="_blank" rel="noopener">the CRM login</a> with their email and this password.</small>
									@if ($errors->has('password'))
										<span class="custom-error" role="alert">
											<strong>{{ $errors->first('password') }}</strong>
										</span>
									@endif
								</div>
								<div class="form-group">
									<label for="password_confirmation">Confirm New Password</label>
									<input type="password" name="password_confirmation" class="form-control" autocomplete="new-password" placeholder="Repeat new password" data-valid="" />
									@if ($errors->has('password_confirmation'))
										<span class="custom-error" role="alert">
											<strong>{{ $errors->first('password_confirmation') }}</strong>
										</span>
									@endif
								</div>
								<div class="form-group">
									<label for="status"><strong>Account Status</strong></label>
									<select name="status" id="status" class="form-control">
										<option value="1" @if(old('status', $fetchedData->status ?? 1) == 1) selected @endif>Active</option>
										<option value="0" @if(old('status', $fetchedData->status ?? 1) == 0) selected @endif>Inactive</option>
									</select>
									<small class="form-text text-muted">Inactive staff cannot log in.</small>
								</div>
								<div class="form-group">
									<label for="name">Phone Number</label>
									<div class="cus_field_input">
									<div class="country_code">
										<input class="telephone" id="telephone" type="tel" name="country_code" value="{{ old('country_code', $fetchedData->country_code ?? $fetchedData->phone ?? '') }}" >
									</div>
									<input type="text" name="phone" value="{{ old('phone', @$fetchedData->phone) }}" class="form-control tel_input" data-valid="" autocomplete="off" placeholder="Enter Phone">
                                    @if ($errors->has('phone'))
										<span class="custom-error" role="alert">
											<strong>{{ @$errors->first('phone') }}</strong>
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
									<label for="name">Position Title</label>
									<input type="text" name="position" value="{{ old('position', @$fetchedData->position) }}" class="form-control" data-valid="" autocomplete="off" placeholder="Enter Position Title">
                                    @if ($errors->has('position'))
										<span class="custom-error" role="alert">
											<strong>{{ @$errors->first('position') }}</strong>
										</span>
									@endif
								</div>

                                <div class="form-group">
									<label for="role">User Role (Type)</label>
									<select name="role" id="role" class="form-control" data-valid="required" autocomplete="new-password">
										<option value="">Choose One...</option>
										@if(count(@$usertype) !== 0)
											@foreach (@$usertype as $ut)
												<option value="{{ @$ut->id }}" @if(old('role', $fetchedData->role) == $ut->id) selected @endif>{{ @$ut->name }}</option>
											@endforeach
										@endif
									</select>
									@if ($errors->has('role'))
										<span class="custom-error" role="alert">
											<strong>{{ @$errors->first('role') }}</strong>
										</span>
									@endif
								</div>

								<div class="form-group">
                                    <?php
                                    $branchx = \App\Models\Branch::query()->orderBy('office_name')->get();
                                    ?>
									<label for="office">Office</label>
									<select class="form-control" data-valid="required" name="office" id="office">
										<option value="">Select</option>
										@foreach($branchx as $branch)
											<option @if(old('office', $fetchedData->office_id) == $branch->id) selected @endif value="{{$branch->id}}">{{$branch->office_name}}</option>
										@endforeach
									</select>
									@if ($errors->has('office'))
										<span class="custom-error" role="alert">
											<strong>{{ @$errors->first('office') }}</strong>
										</span>
									@endif
								</div>

                                <div class="form-group">
									<label for="team">Department (Team)</label>
									<select name="team" id="team" class="form-control" data-valid="" autocomplete="new-password">
										<option value="">Choose One...</option>

											@foreach (\App\Models\Team::query()->orderBy('name')->get() as $tm)
												<option @if(old('team', $fetchedData->team) == $tm->id) selected @endif value="{{ @$tm->id }}">{{ @$tm->name }}</option>
											@endforeach

									</select>
                                </div>

                                @php
                                    $_quickActor = auth()->guard('admin')->user();
                                    $_canQuick = $_quickActor instanceof \App\Models\Staff
                                        && app(\App\Services\CrmAccess\CrmAccessService::class)->canManageStaffQuickAccess($_quickActor);
                                    $_isSuperAdminActor = $_quickActor instanceof \App\Models\Staff
                                        && (int) ($_quickActor->role ?? 0) === 1;
                                @endphp
                                @if($_canQuick)
                                <div class="form-group">
                                    <label class="d-flex align-items-center mb-0">
                                        <input type="hidden" name="quick_access_enabled" value="0">
                                        <input type="checkbox" name="quick_access_enabled" value="1" class="me-2"
                                            @if(old('quick_access_enabled', $fetchedData->quick_access_enabled ?? false)) checked @endif>
                                        <span>Quick access enabled ({{ config('crm_access.quick_grant_minutes', 15) }}-minute cross-access requests)</span>
                                    </label>
                                    <small class="text-muted d-block mt-1">Super Admin or access approver. Turning off revokes active quick and pending supervisor grants immediately.</small>
                                </div>
                                @endif

                                <div class="form-group">
                                    @if($_isSuperAdminActor)
                                        <input type="hidden" name="grant_super_admin_access" value="0">
                                    @endif
                                    <label class="d-flex align-items-center mb-0">
                                        <input type="checkbox" name="grant_super_admin_access" value="1" class="me-2"
                                            @if(old('grant_super_admin_access', $fetchedData->grant_super_admin_access ?? false)) checked @endif
                                            @if(!$_isSuperAdminActor) disabled @endif>
                                        <span>Do u want this user to grant Super admin access level?</span>
                                    </label>
                                    @if($_isSuperAdminActor)
                                        <small class="text-muted d-block mt-1">Unchecked by default. When enabled, this user gets the same elevated CRM access currently granted through configured approver staff IDs.</small>
                                    @else
                                        <small class="text-muted d-block mt-1">Only Superadmin role user can provide this access.</small>
                                    @endif
                                </div>

                                @if($_isSuperAdminActor && \Illuminate\Support\Facades\Schema::hasColumn('staff', 'trust_rule42_supervisor'))
                                <div class="form-group">
                                    <input type="hidden" name="trust_rule42_supervisor" value="0">
                                    <label class="d-flex align-items-center mb-0">
                                        <input type="checkbox" name="trust_rule42_supervisor" value="1" class="me-2"
                                            @if(old('trust_rule42_supervisor', $fetchedData->trust_rule42_supervisor ?? false)) checked @endif>
                                        <span>Rule 42 trust supervisor</span>
                                    </label>
                                    <small class="text-muted d-block mt-1">May document supervisor overrides on fee transfers from trust (draft invoice, date order, voided invoice). Native Super Admin role already has full override authority.</small>
                                </div>
                                @endif

                                @php
                                    $_canGrantEmailDelete = \App\Models\Staff::canGrantEmailDeleteWithAttachmentsPermission($_quickActor instanceof \App\Models\Staff ? $_quickActor : null);
                                @endphp
                                @if($_canGrantEmailDelete && \Illuminate\Support\Facades\Schema::hasColumn('staff', 'can_delete_email_with_attachments'))
                                <div class="form-group">
                                    <input type="hidden" name="can_delete_email_with_attachments" value="0">
                                    <label class="d-flex align-items-center mb-0">
                                        <input type="checkbox" name="can_delete_email_with_attachments" value="1" class="me-2"
                                            @if(old('can_delete_email_with_attachments', $fetchedData->can_delete_email_with_attachments ?? false)) checked @endif>
                                        <span>Can delete emails with attachments</span>
                                    </label>
                                    <small class="text-muted d-block mt-1">When enabled, this user sees a Delete option on client/lead emails and may permanently remove the message and its attachments from the CRM.</small>
                                </div>
                                @endif

                                @php
                                    $_canGrantCloseDiscontinue = \App\Models\Staff::canGrantCloseDiscontinueMatterPermission($_quickActor instanceof \App\Models\Staff ? $_quickActor : null);
                                @endphp
                                @if($_canGrantCloseDiscontinue && \Illuminate\Support\Facades\Schema::hasColumn('staff', 'can_close_discontinue_matter'))
                                <div class="form-group">
                                    <input type="hidden" name="can_close_discontinue_matter" value="0">
                                    <label class="d-flex align-items-center mb-0">
                                        <input type="checkbox" name="can_close_discontinue_matter" value="1" class="me-2"
                                            @if(old('can_close_discontinue_matter', $fetchedData->can_close_discontinue_matter ?? false)) checked @endif>
                                        <span>Can close/discontinue matters</span>
                                    </label>
                                    <small class="text-muted d-block mt-1">When enabled, this user sees Close Matter on client profiles and may discontinue client matters from the workflow tab.</small>
                                </div>
                                @endif

                                @php
                                    $_canGrantFinalInvoiceEdit = \App\Models\Staff::canGrantFinalInvoiceEditPermission(
                                        $_quickActor instanceof \App\Models\Staff ? $_quickActor : null
                                    );
                                @endphp
                                @if($_canGrantFinalInvoiceEdit && \Illuminate\Support\Facades\Schema::hasColumn('staff', 'can_edit_final_invoice'))
                                <div class="form-group">
                                    <input type="hidden" name="can_edit_final_invoice" value="0">
                                    <label class="d-flex align-items-center mb-0">
                                        <input type="checkbox" name="can_edit_final_invoice" value="1" class="me-2"
                                            @if(old('can_edit_final_invoice', $fetchedData->can_edit_final_invoice ?? false)) checked @endif>
                                        <span>Can edit unpaid final invoices</span>
                                    </label>
                                    <small class="text-muted d-block mt-1">Allows this staff member to amend a saved invoice before any payment is applied. All edits are recorded in the client timeline.</small>
                                </div>
                                @endif

                                <div class="form-group">
                                    <label for="role">Permission</label>
							    	<?php
                                    if( isset($fetchedData->permission) && $fetchedData->permission !="")
                                    {
                                        if( strpos($fetchedData->permission,",") ){
                                            $permission_arr =  explode(",",$fetchedData->permission);
                                        } else {
                                            $permission_arr = [$fetchedData->permission];
                                        } ?>

                                            <br><b>Notes</b>  &nbsp;&nbsp;&nbsp;&nbsp;
                                            <input value="1" <?php if ( in_array(1, $permission_arr) ) echo "checked='checked'"; ?> type="checkbox" name="permission[]" class="show_dashboard_per">&nbsp; View &nbsp;
                                            <input value="2" <?php if ( in_array(2, $permission_arr) ) echo "checked='checked'"; ?> type="checkbox" name="permission[]" class="show_dashboard_per">&nbsp; Add/Edit &nbsp;
                                            <input value="3" <?php if ( in_array(3, $permission_arr) ) echo "checked='checked'"; ?> type="checkbox" name="permission[]" class="show_dashboard_per">&nbsp; Delete &nbsp;

                                            <br><b>Documents</b>
                                            <input value="4" <?php if ( in_array(4, $permission_arr) ) echo "checked='checked'"; ?> type="checkbox" name="permission[]" class="show_dashboard_per">&nbsp; View &nbsp;
                                            <input value="5" <?php if ( in_array(5, $permission_arr) ) echo "checked='checked'"; ?> type="checkbox" name="permission[]" class="show_dashboard_per">&nbsp; Add/Edit &nbsp;
                                            <input value="6" <?php if ( in_array(6, $permission_arr) ) echo "checked='checked'"; ?> type="checkbox" name="permission[]" class="show_dashboard_per">&nbsp; Delete &nbsp;
                                        <?php
                                    }
                                    else
                                    {
                                    ?>
                                        <br><b>Notes</b>  &nbsp;&nbsp;&nbsp;&nbsp;
                                        <input value="1" type="checkbox" name="permission[]" class="show_dashboard_per">&nbsp; View &nbsp;
                                        <input value="2" type="checkbox" name="permission[]" class="show_dashboard_per">&nbsp; Add/Edit &nbsp;
                                        <input value="3" type="checkbox" name="permission[]" class="show_dashboard_per">&nbsp; Delete &nbsp;

                                        <br><b>Documents</b>
                                        <input value="4" type="checkbox" name="permission[]" class="show_dashboard_per">&nbsp; View &nbsp;
                                        <input value="5" type="checkbox" name="permission[]" class="show_dashboard_per">&nbsp; Add/Edit &nbsp;
                                        <input value="6" type="checkbox" name="permission[]" class="show_dashboard_per">&nbsp; Delete &nbsp;
                                    <?php
                                    }?>
                                </div>

							    <div class="form-group">
							    	<label><input @if($fetchedData->show_dashboard_per == 1) checked @endif value="1" type="checkbox" name="show_dashboard_per" class="show_dashboard_per"> Can view on dasboard</label>
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
										<input type="checkbox" id="is_solicitor" name="is_solicitor" value="1" 
											@if($fetchedData->is_solicitor == 1) checked @endif class="me-2">
										<h5 class="mb-0">Is this staff a Legal Practitioner?</h5>
									</label>
								</div>

								<!-- Agent Details Fields -->
								<div id="agent_details_section" style="display: {{ $fetchedData->is_solicitor == 1 ? 'block' : 'none' }};">
									<hr>
									<h6 class="text-primary mb-3">Legal Practitioner registration details</h6>
									
									<div class="row">
										<div class="col-md-6">
											<div class="form-group">
												<label for="marn_number">MARN Number</label>
												<input type="text" name="marn_number" id="marn_number" value="{{ old('marn_number', @$fetchedData->marn_number) }}" class="form-control" placeholder="Enter MARN Number">
											</div>
										</div>
									</div>

									<h6 class="text-primary mb-3 mt-4">Business Details</h6>
									
									<div class="row">
										<div class="col-md-6">
											<div class="form-group">
												<label for="company_name">Business Name</label>
												<input type="text" name="company_name" value="{{ old('company_name', @$fetchedData->company_name) }}" class="form-control" placeholder="Enter Business Name">
											</div>
										</div>
										<div class="col-md-6">
											<div class="form-group">
												<label for="tax_number">Tax Number (ABN/ACN)</label>
												<input type="text" name="tax_number" value="{{ old('tax_number', @$fetchedData->tax_number) }}" class="form-control" placeholder="Enter Tax Number">
											</div>
										</div>
									</div>

									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="business_address">Business Address</label>
												<textarea name="business_address" class="form-control" rows="2" placeholder="Enter Business Address">{{ old('business_address', @$fetchedData->business_address) }}</textarea>
											</div>
										</div>
									</div>

									<div class="row">
										<div class="col-md-4">
											<div class="form-group">
												<label for="business_phone">Business Phone</label>
												<input type="text" name="business_phone" value="{{ old('business_phone', @$fetchedData->business_phone) }}" class="form-control" placeholder="Enter Business Phone">
											</div>
										</div>
										<div class="col-md-4">
											<div class="form-group">
												<label for="business_mobile">Business Mobile</label>
												<input type="text" name="business_mobile" value="{{ old('business_mobile', @$fetchedData->business_mobile) }}" class="form-control" placeholder="Enter Business Mobile">
											</div>
										</div>
										<div class="col-md-4">
											<div class="form-group">
												<label for="business_email">Business Email</label>
												<input type="email" name="business_email" value="{{ old('business_email', @$fetchedData->business_email) }}" class="form-control" placeholder="Enter Business Email">
											</div>
										</div>
									</div>

								</div>
							</div>
						</div>
					</div>

					<div class="col-12 col-md-12 col-lg-12">
						<div class="card">
							<div class="card-body">
								<h4>EMAIL SIGNATURE</h4>
								<p class="text-muted mb-3">This signature is automatically added when the staff member composes, replies, or forwards emails using their login email address.</p>
								<div class="form-group mb-0">
									<label for="email_signature">Email Signature</label>
									<p class="text-muted small">Paste HTML via Source code, or insert a table from the toolbar. A live HTML preview is shown below.</p>
									<textarea class="form-control tinymce-editor-full staff-email-signature" name="email_signature" id="email_signature">{{ old('email_signature', @$fetchedData->email_signature) }}</textarea>
									<div class="staff-signature-preview-wrap">
										<div class="staff-signature-preview-label">HTML preview</div>
										<iframe class="staff-signature-preview" id="email_signature_preview" title="Signature HTML preview"></iframe>
									</div>
								</div>
							</div>
						</div>
					</div>

					<div class="col-12">
						<div class="form-group float-right">
							<input type="submit" value="Update Staff" class="btn btn-primary">
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
	// Sync TinyMCE signature content before form submit
	$('form[name="edit-staff"]').on('submit', function() {
		if (typeof tinymce !== 'undefined') {
			tinymce.triggerSave();
		}
	});

	// Toggle Legal Practitioner details section
	$('#is_solicitor').on('change', function() {
		if ($(this).is(':checked')) {
			$('#agent_details_section').slideDown();
		} else {
			$('#agent_details_section').slideUp();
		}
	});

    // Scroll to the first error banner or inline error on redirect-back
    @if($errors->any() || Session::has('error'))
    var $firstError = $('.server-error .alert-danger, .custom-error').first();
    if ($firstError.length) {
        $('html, body').animate({ scrollTop: $firstError.offset().top - 80 }, 400);
    }
    @endif

    var $grantSuperAdminAccess = $('input[name="grant_super_admin_access"][type="checkbox"]');
    if ($grantSuperAdminAccess.length) {
        $grantSuperAdminAccess.on('change', function() {
            var isChecked = $(this).is(':checked');
            var message = isChecked
                ? 'Are you sure you want to grant Super admin access level to this user?'
                : 'Are you sure you want to remove Super admin access level from this user?';

            if (!window.confirm(message)) {
                $(this).prop('checked', !isChecked);
            }
        });
    }
});
</script>
@endsection
