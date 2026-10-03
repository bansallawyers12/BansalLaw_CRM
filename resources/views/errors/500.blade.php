@extends('errors.layout')

@section('title', 'Server error')

@section('content')
    <p class="error-code" aria-hidden="true">500</p>
    <h1 class="error-heading">Something went wrong</h1>
    <p class="error-message">
        We hit an unexpected problem on our side. Your work is usually safe—try again in a moment or head back to the dashboard.
    </p>
    <div class="error-actions">
        @auth('admin')
            <a class="error-btn error-btn--primary" href="{{ url('/dashboard') }}">Go to dashboard</a>
        @else
            <a class="error-btn error-btn--primary" href="{{ route('crm.login') }}">Sign in</a>
        @endauth
        <button type="button" class="error-btn error-btn--ghost" onclick="location.reload()">Try again</button>
    </div>
    @if(config('app.debug') && isset($exception))
        <div class="error-debug" role="note">{{ $exception->getMessage() }}</div>
    @endif
@endsection
