@extends('layouts.crm_client_detail_dashboard')

@section('title', 'Unassigned Mail')

@section('content')
    @php
        $staffUser = auth('admin')->user();
        $staffFirstName = ($staffUser && ! empty($staffUser->first_name)) ? $staffUser->first_name : 'there';
        $pageTz = ($staffUser && ! empty($staffUser->time_zone)) ? $staffUser->time_zone : config('app.timezone');
        $pageNow = now()->timezone($pageTz);
    @endphp
    <main class="main-content">
        <header class="header">
            <div class="header-title-section">
                <h1>Unassigned mail</h1>
                <p class="dashboard-header-meta">
                    Synced inbox emails waiting to be linked to a client, or already assigned from sync ·
                    <time datetime="{{ $pageNow->toIso8601String() }}">{{ $pageNow->format('l, j F Y') }} · {{ $pageNow->format('g:i A') }}</time>
                </p>
            </div>
        </header>

        <section class="unassigned-mail-section">
            @include('crm.emails_outlook', ['unassignedOnly' => true])
        </section>
    </main>
@endsection

@push('styles')
<style>
    .unassigned-mail-section .outlook-container {
        min-height: calc(100vh - 220px);
    }
</style>
@endpush
