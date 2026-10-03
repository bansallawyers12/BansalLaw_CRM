@extends('errors.layout')

@section('title', 'Unauthenticated')

@section('content')
    <p class="error-code" aria-hidden="true">401</p>
    <h1 class="error-heading">Please sign in</h1>
    <p class="error-message">
        You need to be signed in to view this page. Use your staff credentials to access {{ config('app.login_brand', config('app.name')) }}.
    </p>
    <div class="error-actions">
        <a class="error-btn error-btn--primary" href="{{ route('crm.login') }}">Sign in</a>
    </div>
@endsection
