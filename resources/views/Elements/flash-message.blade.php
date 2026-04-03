@if ($message = Session::get('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
	<button type="button" class="close" data-bs-dismiss="alert" aria-label="Close">☓</button>
        <strong>{{ $message }}</strong>
</div>
@endif
@if ($message = Session::get('error'))
<div class="alert alert-danger alert-dismissible fade show">
	<button type="button" class="close" data-bs-dismiss="alert" aria-label="Close">☓</button>
        <strong>{{ $message }}</strong>
</div>
@endif

@if ($message = Session::get('warning'))
<div class="alert alert-warning alert-dismissible fade show">
	<button type="button" class="close" data-bs-dismiss="alert" aria-label="Close">☓</button>
	<strong>{{ $message }}</strong>
</div>
@endif

@if ($message = Session::get('info'))
<div class="alert alert-info alert-dismissible fade show">
	<button type="button" class="close" data-bs-dismiss="alert" aria-label="Close">☓</button>
	<strong>{{ $message }}</strong>
</div>
@endif

@if ($errors->any())
<div class="alert alert-danger alert-dismissible fade show">
	<button type="button" class="close" data-bs-dismiss="alert" aria-label="Close">☓</button>
	<strong>Please check the form below for errors.</strong>
	@if ($errors->first())
		<span class="d-block mt-1">{{ $errors->first() }}</span>
	@endif
</div>
@endif