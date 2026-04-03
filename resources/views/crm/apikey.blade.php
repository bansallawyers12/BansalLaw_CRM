@extends('layouts.crm_client_detail')
@section('title', 'API Key')

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
				<div class="col-12 col-md-12 col-lg-12">
					<div class="card">
						<div class="card-header">
							<h4>API Key Management</h4>
						</div>
						@if(@Auth::user()->client_id == '')
						<form action="{{ url('api-key') }}" method="POST" name="add-key" autocomplete="off" enctype="multipart/form-data">
							@csrf
							<div class="card-body">
								<div class="alert alert-info">
									<i class="fas fa-info-circle"></i> You don't have an API key yet. Click the button below to generate one.
								</div>
								<div class="form-group">
									<button type="submit" class="btn btn-primary" onClick="customValidate('add-key')">
										<i class="fas fa-key"></i> Generate API Key
									</button>
								</div>
							</div>
						</form>
						@else
						<div class="card-body">
							<div class="alert alert-success">
								<i class="fas fa-check-circle"></i> Your API key has been generated successfully.
							</div>
							<div class="form-group">
								<label>Your API Key:</label>
								<div class="input-group">
									<input type="text" class="form-control" value="{{ @Auth::user()->client_id }}" readonly id="apiKeyField">
									<div class="input-group-append">
										<button class="btn btn-primary" type="button" onclick="copyApiKey()">
											<i class="far fa-copy"></i> Copy
										</button>
									</div>
								</div>
								<small class="form-text text-muted">Keep this key secure and don't share it publicly.</small>
							</div>
						</div>
						@endif
					</div>
				</div>
			</div>
		</div>
	</section>
</div>

<script>
function copyApiKey() {
	var copyText = document.getElementById("apiKeyField");
	copyText.select();
	copyText.setSelectionRange(0, 99999);
	document.execCommand("copy");
	
	// Show feedback
	var btn = event.target.closest('button');
	var originalHTML = btn.innerHTML;
	btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
	setTimeout(function() {
		btn.innerHTML = originalHTML;
	}, 2000);
}
</script>

@endsection