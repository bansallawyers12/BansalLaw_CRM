@extends('errors.layout')

@section('title', 'Page not found')

@section('content')
    <p class="error-code" aria-hidden="true">404</p>
    <h1 class="error-heading">This page isn’t here</h1>
    <p class="error-message">
        The link may be outdated, or the address was typed incorrectly. Check the URL or return to a safe place in the CRM.
    </p>
    <div class="error-actions">
        @auth('admin')
            <a class="error-btn error-btn--primary" href="{{ url('/dashboard') }}">Go to dashboard</a>
        @else
            <a class="error-btn error-btn--primary" href="{{ route('crm.login') }}">Sign in</a>
        @endauth
        <button type="button" class="error-btn error-btn--ghost" onclick="history.length > 1 ? history.back() : (window.location.href = '{{ route('crm.login') }}')">
            Go back
        </button>
    </div>
@endsection
