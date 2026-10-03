@extends('errors.layout')

@section('title', 'Service unavailable')

@section('content')
    <p class="error-code" aria-hidden="true">503</p>
    <h1 class="error-heading">We’ll be right back</h1>
    <p class="error-message">
        {{ config('app.login_brand', config('app.name')) }} is temporarily unavailable for maintenance or a quick update. Please try again shortly.
    </p>
    <div class="error-actions">
        <button type="button" class="error-btn error-btn--primary" onclick="location.reload()">Refresh page</button>
        <a class="error-btn error-btn--ghost" href="{{ route('crm.login') }}">Sign in</a>
    </div>
@endsection
