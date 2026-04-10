@if(Auth::user() instanceof \App\Models\Staff && Auth::user()->hasEffectiveSuperAdminPrivileges())
	<li class="breadcrumb-menu d-md-down-none">
    	<div class="btn-group" role="group" aria-label="Button group">
			<!-- Website Settings link removed -->
		</div>
	</li>
@endif	