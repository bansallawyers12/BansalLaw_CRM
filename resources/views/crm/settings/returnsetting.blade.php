@extends('layouts.crm_client_detail')
@section('title', 'Tax Setting')

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
							<h4>Tax Settings</h4>
						</div>
						<div class="card-body">
							<p class="text-muted">GST settings have been deprecated. No configuration required.</p>
						</div>
					</div>	
				</div>	
			</div>
		</div>
	</section>
</div>
@endsection