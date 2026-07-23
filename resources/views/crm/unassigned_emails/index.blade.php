@extends('layouts.crm_client_detail_dashboard')

@section('title', 'Unassigned Mail')

@section('content')
    @php
        $staffUser = auth('admin')->user();
        $staffFirstName = ($staffUser && ! empty($staffUser->first_name)) ? $staffUser->first_name : 'there';
        $pageTz = ($staffUser && ! empty($staffUser->time_zone)) ? $staffUser->time_zone : config('app.timezone');
        $pageNow = now()->timezone($pageTz);
    @endphp
    <main class="main-content unassigned-mail-page">
        <header class="unassigned-mail-page__header">
            <div class="unassigned-mail-page__header-main">
                <div class="unassigned-mail-page__header-icon" aria-hidden="true">
                    <i class="fa-solid fa-inbox"></i>
                </div>
                <div class="unassigned-mail-page__header-text">
                    <h1>Unassigned mail</h1>
                    <p class="unassigned-mail-page__subtitle">
                        Review synced inbox emails, assign them to clients, and track what is already linked.
                    </p>
                    <p class="unassigned-mail-page__meta">
                        <time datetime="{{ $pageNow->toIso8601String() }}">{{ $pageNow->format('l, j F Y') }} · {{ $pageNow->format('g:i A') }}</time>
                    </p>
                </div>
            </div>
        </header>

        <section class="unassigned-mail-section">
            @include('crm.emails_outlook', ['unassignedOnly' => true])
        </section>
    </main>
@endsection
