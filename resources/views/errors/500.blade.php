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
    @if((config('app.debug') || request()->is('system-errors*')) && isset($exception))
        <div class="error-debug" role="note" style="display:block; margin-top:20px; text-align:left; background:#1e293b; color:#fca5a5; padding:15px; border-radius:8px; font-family:monospace; font-size:12px; border:1px solid #ef4444; word-break:break-all;">
            <strong>Error Details:</strong> {{ $exception->getMessage() }}<br>
            <small style="color:#94a3b8;">{{ $exception->getFile() }}:{{ $exception->getLine() }}</small>
        </div>
    @endif
@endsection
