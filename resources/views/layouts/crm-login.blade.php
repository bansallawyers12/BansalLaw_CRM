<!DOCTYPE html>
<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<meta name="csrf-token" content="{{ csrf_token() }}">
	<meta name="description" content="">
	@php($loginBrand = config('app.login_brand') ?? config('app.name'))
	<meta name="author" content="{{ $loginBrand }}">
	<title>{{ $loginBrand }} | @yield('title')</title>
	<link rel="icon" type="image/png" href="{{asset('img/favicon.png')}}">
	<!-- Favicons-->

	 <!-- BASE CSS -->
	@include('components.bootstrap5-assets')
	<link href="{{asset('css/app.min.css')}}" rel="stylesheet">
	<link href="{{asset('css/bootstrap-social.css')}}" rel="stylesheet">
	<link href="{{asset('css/style.css')}}" rel="stylesheet">
	<link href="{{asset('css/components.css')}}" rel="stylesheet">
	<link href="{{asset('css/custom.css')}}" rel="stylesheet">
	<link href="{{asset('css/crm-theme.css')}}" rel="stylesheet">
	<link href="{{asset('css/crm/modal-ui.css')}}?v={{ @filemtime(public_path('css/crm/modal-ui.css')) ?: time() }}" rel="stylesheet">
	@include('components.sweetalert2-assets')
	@include('components.font-awesome')

    <script async src="https://www.google.com/recaptcha/api.js"></script> <!-- Add recaptcha script -->
</head>
<style>
.bg{
    height: 100%;
    margin: 0;
    background-position: center;
    background-repeat: no-repeat;
    background-size: cover;
}
</style>
<body class="bg crm-login-page">
	<div class="loader"></div>
	<div id="app">
		@yield('content')
	</div>
	<!-- COMMON SCRIPTS -->
	<script type="text/javascript">
		var site_url = "<?php echo URL::to('/'); ?>";
	</script>
	<script src="{{ asset('js/jquery.min.js') }}"></script>
	{{-- app.min.js: legacy Popper/Bootstrap/moment bundle (jQuery stripped; CDN 3.7.1 above must load first) --}}
	<script src="{{asset('js/app.min.js')}}"></script>
	@include('components.bootstrap5-scripts')
	<script src="{{asset('js/bootstrap5-jquery-compat.js')}}"></script>
	<script src="{{asset('js/scripts.js')}}"></script>
	<script src="{{asset('js/custom.js')}}"></script>
	@include('components.sweetalert2-scripts')
</body>
</html>
