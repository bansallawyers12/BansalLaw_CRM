@extends('errors.layout')

@section('title', 'Access denied')

@section('content')
    <p class="error-code" aria-hidden="true">403</p>
    <h1 class="error-heading">Access denied</h1>
    <p class="error-message">
        You don’t have permission to open this page. If you think you should, ask an administrator to review your role.
    </p>
    <div class="error-actions">
        @auth('admin')
            <a class="error-btn error-btn--primary" href="{{ url('/dashboard') }}">Go to dashboard</a>
        @else
            <a class="error-btn error-btn--primary" href="{{ route('crm.login') }}">Sign in</a>
        @endauth
        <button type="button" class="error-btn error-btn--ghost" onclick="history.back()">Go back</button>
    </div>
@endsection
