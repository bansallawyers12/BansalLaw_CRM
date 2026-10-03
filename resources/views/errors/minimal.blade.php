@extends('errors.layout')

@section('title', 'Error')

@section('content')
    @php
        $status = isset($exception) && method_exists($exception, 'getStatusCode')
            ? $exception->getStatusCode()
            : 500;
    @endphp
    <p class="error-code" aria-hidden="true">{{ $status }}</p>
    <h1 class="error-heading">Request could not be completed</h1>
    <p class="error-message">
        {{ $exception->getMessage() && ! str_contains($exception->getMessage(), 'Http') ? $exception->getMessage() : 'An error occurred while loading this page.' }}
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
