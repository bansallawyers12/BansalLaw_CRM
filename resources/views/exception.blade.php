<!DOCTYPE html>
<html lang="en">
	<head>
		<base href="./">
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
		<meta name="description" content="ApnaMentor for higher education">
		<meta name="author" content="Raghav Garg">
		<meta name="keyword" content="Bootstrap,Admin,Template,Open,Source,jQuery,CSS,HTML,RWD,Dashboard">
		<meta name="csrf-token" content="{{ csrf_token() }}">
		<title>Tour Planner | Exception</title>
    
		<!-- Icons-->
			@include('components.font-awesome')
		
		<!-- Main styles for this application-->
			@include('components.bootstrap5-assets')
			<link rel="stylesheet" type="text/css" href="{{asset('public/css/style.css')}}" />
	</head>
	<body class="d-flex min-vh-100 flex-row align-items-center bg-light">
		<div id="loader">
			<div class="loading_image">
				<div class="valid">
					<img src="{{asset('public/img/loading.gif') }}">
				</div>
			</div>
		</div>
		<div class="container">
			<div class="row justify-content-center">
				<div class="col-md-8">
					@include('Elements/flash-message')
					<div class="card-group">
						<div class="card p-4">
							<form method="POST" action="{{ url('/exception') }}" name="exception">
								@csrf
								<div class="card-body">
									<h1>Exception</h1>
									<div class="input-group mb-3">
										<textarea class="form-control" name="comment" placeholder="Please write comment, what did you face." data-valid="required"></textarea>	
									</div>
									<div class="row">
										<div class="col-6">
											<button type="button" class="btn btn-primary px-4" onclick="customValidate('exception')">Post</button>
										</div>
									</div>
								</div>
							</form>
						</div>
						<div class="card text-white bg-primary py-5 d-md-down-none" style="width:44%">
							<div class="card-body text-center">
								<div>
									<p>Please write your comment, if you are seeing this page.</p>
									<p>This page occur because you are facing any issue, so please write your comment what your are exactly facing, so we can resolve as soon as possible</p>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		
		<!-- Apnamentor necessary plugins-->
			<script src="{{ asset('js/jquery.min.js') }}"></script>
			@include('components.bootstrap5-scripts')
			<script src="{{asset('public/js/custom-form-validation.js')}}"></script>
	</body>
</html>