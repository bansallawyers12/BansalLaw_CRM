<?php
		if(Auth::check()) {
			$roles = \App\Models\UserRole::find(Auth::user()->role);
			$newarray = json_decode($roles->module_access);
			$module_access = (array) $newarray;

			// CRM access approvers get full admin console menu access (same as Super Admin)
			$_settingUser = Auth::user();
			$_isApproverOrAdmin = $_settingUser instanceof \App\Models\Staff
				&& app(\App\Services\CrmAccess\CrmAccessService::class)->hasAdminConsoleLikeSuperAdminAccess($_settingUser);
			if ($_isApproverOrAdmin) {
				// Ensure the gated numeric keys are present so all guarded menu items show
				$module_access['1'] = true;
				$module_access['4'] = true;
				$module_access['6'] = true;
			}

			$_accessGate = app(\App\Services\CrmAccess\StaffAccessGate::class);
			$_isRestrictedStaff = $_settingUser instanceof \App\Models\Staff
				&& $_accessGate->isFeatureEnabled()
				&& $_accessGate->isRestricted($_settingUser)
				&& ! $_accessGate->hasFullCrmAccess($_settingUser);
			$_canAc = static function (string $key) use ($_isRestrictedStaff, $_accessGate, $_settingUser): bool {
				if (! $_isRestrictedStaff) {
					return true;
				}

				return $_accessGate->canAdminConsoleModule($_settingUser, $key);
			};
		} else {
			$module_access = [];
			$_isApproverOrAdmin = false;
			$_isRestrictedStaff = false;
			$_canAc = static fn (string $key): bool => false;
		}
?>
<div class="custom_nav_setting">
    <ul>
		@if($_canAc('email_labels'))
		<li class="{{(Route::currentRouteName() == 'adminconsole.features.emaillabels.index' || Route::currentRouteName() == 'adminconsole.features.emaillabels.create' || Route::currentRouteName() == 'adminconsole.features.emaillabels.edit') ? 'active' : ''}}"><a class="nav-link" href="{{route('adminconsole.features.emaillabels.index')}}">Email Labels</a></li>
		@endif

		@if($_canAc('workflows'))
        <li class="{{(str_starts_with(Route::currentRouteName() ?? '', 'adminconsole.features.workflow.')) ? 'active' : ''}}"><a class="nav-link" href="{{route('adminconsole.features.workflow.index')}}">Workflows</a></li>
		@endif

		@if($_canAc('emails'))
        <li class="{{(Route::currentRouteName() == 'adminconsole.features.emails.index' || Route::currentRouteName() == 'adminconsole.features.emails.create' || Route::currentRouteName() == 'adminconsole.features.emails.edit') ? 'active' : ''}}"><a class="nav-link" href="{{route('adminconsole.features.emails.index')}}">Email</a></li>
		@endif
		@if($_canAc('crm_email_template'))
		<li class="{{(Route::currentRouteName() == 'adminconsole.features.crmemailtemplate.index' || Route::currentRouteName() == 'adminconsole.features.crmemailtemplate.create' || Route::currentRouteName() == 'adminconsole.features.crmemailtemplate.edit') ? 'active' : ''}}"><a class="nav-link" href="{{route('adminconsole.features.crmemailtemplate.index')}}">Crm Email Template</a></li>
		@endif

			<?php
			// Restricted Staff Access grants bypass empty role module_access keys.
			if((array_key_exists('1',  $module_access) || $_isRestrictedStaff) && $_canAc('offices')) {
			?>
			<li class="{{(Route::currentRouteName() == 'adminconsole.system.offices.index' || Route::currentRouteName() == 'adminconsole.system.offices.create' || Route::currentRouteName() == 'adminconsole.system.offices.edit' || Route::currentRouteName() == 'adminconsole.system.offices.view' || Route::currentRouteName() == 'adminconsole.system.offices.viewclient') ? 'active' : ''}}"><a class="nav-link" href="{{route('adminconsole.system.offices.index')}}">Offices</a></li>
			<?php } ?>
			<?php
			if((array_key_exists('4',  $module_access) || $_isRestrictedStaff) && $_canAc('staff')) {
			?>
			<li class="{{(Route::currentRouteName() == 'adminconsole.staff.index' || Route::currentRouteName() == 'adminconsole.staff.active' || Route::currentRouteName() == 'adminconsole.staff.inactive' || Route::currentRouteName() == 'adminconsole.staff.invited' || Route::currentRouteName() == 'adminconsole.staff.create' || Route::currentRouteName() == 'adminconsole.staff.edit' || Route::currentRouteName() == 'adminconsole.staff.view') ? 'active' : ''}}"><a class="nav-link" href="{{ route('adminconsole.staff.index') }}">Staff</a></li>
			<?php } ?>
			<?php
			if((array_key_exists('4',  $module_access) || $_isRestrictedStaff) && $_canAc('teams')) {
			?>
			<li class="{{(Route::currentRouteName() == 'adminconsole.system.teams.index' ) ? 'active' : ''}}"><a class="nav-link" href="{{route('adminconsole.system.teams.index')}}">Teams</a></li>
			<?php } ?>
			<?php
			if((array_key_exists('6',  $module_access) || $_isRestrictedStaff) && $_canAc('roles')) {
			?>
			<li class="{{(Route::currentRouteName() == 'adminconsole.system.roles.index' || Route::currentRouteName() == 'adminconsole.system.roles.create' || Route::currentRouteName() == 'adminconsole.system.roles.edit' || Route::currentRouteName() == 'adminconsole.system.roles.view') ? 'active' : ''}}"><a class="nav-link" href="{{route('adminconsole.system.roles.index')}}">Roles</a></li>
			<?php } ?>
			
			{{-- Personal / Matter Document Folders are managed on the client details page, so they are hidden from this admin sidebar. --}}

			@if($_canAc('document_checklist'))
			<li class="{{ in_array(Route::currentRouteName(), ['adminconsole.features.documentchecklist.index', 'adminconsole.features.documentchecklist.create', 'adminconsole.features.documentchecklist.edit', 'adminconsole.features.documentchecklist.view']) ? 'active' : '' }}"><a class="nav-link" href="{{route('adminconsole.features.documentchecklist.index')}}">Document Checklist</a></li>
			@endif

			@if($_canAc('matter_list'))
			<li class="{{ in_array(Route::currentRouteName(), ['adminconsole.features.matter.index', 'adminconsole.features.matter.create', 'adminconsole.features.matter.edit', 'adminconsole.features.matter.view']) ? 'active' : '' }}"><a class="nav-link" href="{{route('adminconsole.features.matter.index')}}">Matter List</a></li>
			@endif

			<?php
			$smsclasstype = '';
			$currentRoute = Route::currentRouteName() ?? '';
			if(str_starts_with($currentRoute, 'adminconsole.features.sms.') && !str_starts_with($currentRoute, 'adminconsole.features.sms.templates.')){
				$smsclasstype = 'active';
			}
			$smstemplatesclasstype = str_starts_with($currentRoute, 'adminconsole.features.sms.templates.') ? 'active' : '';
			?>
			@if($_canAc('sms'))
			<li class="{{$smsclasstype}}"><a class="nav-link" href="{{route('adminconsole.features.sms.dashboard')}}">SMS Management</a></li>
			@endif
			@if($_canAc('sms_templates'))
			<li class="{{$smstemplatesclasstype}}"><a class="nav-link" href="{{route('adminconsole.features.sms.templates.index')}}">SMS Templates</a></li>
			@endif

			<?php
			$esignatureclasstype = '';
			if(str_starts_with(Route::currentRouteName() ?? '', 'adminconsole.features.esignature.')){
				$esignatureclasstype = 'active';
			}
			?>
			@if($_canAc('esignature'))
			<li class="{{$esignatureclasstype}}"><a class="nav-link" href="{{route('adminconsole.features.esignature.index')}}">E-Signature</a></li>
			@endif
			
			<?php
			// Activity Search menu - Super Admin / approvers, or restricted with explicit grant
			if(($_isApproverOrAdmin || ($_isRestrictedStaff && $_canAc('activity_search')))) {
				$activitySearchclasstype = '';
				if(str_starts_with(Route::currentRouteName() ?? '', 'adminconsole.system.activity-search.')){
					$activitySearchclasstype = 'active';
				}
			?>
			<li class="{{$activitySearchclasstype}}"><a class="nav-link" href="{{route('adminconsole.system.activity-search.index')}}">Activity Search</a></li>
			<?php
			}
			?>

			<?php
			$showStaffAccessMenu = $_settingUser instanceof \App\Models\Staff
				&& $_accessGate->hasFullCrmAccess($_settingUser);
			if ($showStaffAccessMenu) {
				$staffAccessActive = str_starts_with(Route::currentRouteName() ?? '', 'adminconsole.system.staff-access.');
			?>
			<li class="{{ $staffAccessActive ? 'active' : '' }}"><a class="nav-link" href="{{ route('adminconsole.system.staff-access.index') }}">Staff Access</a></li>
			<?php
			}
			?>

			<?php
			$grantsDashUser = Auth::user();
			$showGrantsDashboard = $grantsDashUser instanceof \App\Models\Staff
				&& app(\App\Services\CrmAccess\CrmAccessService::class)->isApprover($grantsDashUser);
			if ($showGrantsDashboard) {
				$grantsDashActive = (Route::currentRouteName() === 'crm.access.dashboard') ? 'active' : '';
			?>
			<li class="{{ $grantsDashActive }}"><a class="nav-link" href="{{ route('crm.access.dashboard') }}">Grants dashboard</a></li>
			<?php } ?>
			
		</ul>
</div>
