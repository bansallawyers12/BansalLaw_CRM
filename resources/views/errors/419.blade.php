@extends('errors.layout')

@section('title', 'Session expired')

@section('content')
    <p class="error-code" aria-hidden="true">419</p>
    <h1 class="error-heading">Session expired</h1>
    <p class="error-message">
        Your session timed out for security. Sign in again to continue working in the CRM.
    </p>
    <div class="error-actions">
        <a class="error-btn error-btn--primary" href="{{ route('crm.login') }}">Sign in again</a>
        <button type="button" class="error-btn error-btn--ghost" onclick="location.reload()">Reload page</button>
    </div>
@endsection
