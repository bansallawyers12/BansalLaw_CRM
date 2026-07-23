@extends('layouts.crm_client_detail_dashboard')

@section('title', 'Unassigned Mail')

@section('content')
    <main class="main-content unassigned-mail-page">
        <header class="unassigned-mail-page__header">
            <div class="unassigned-mail-page__header-main">
                <div class="unassigned-mail-page__header-icon" aria-hidden="true">
                    <i class="fa-solid fa-inbox"></i>
                </div>
                <div class="unassigned-mail-page__header-text">
                    <h1>Unassigned mail</h1>
                    <p class="unassigned-mail-page__subtitle">
                        Review synced emails, assign to clients, and mark items as read.
                    </p>
                </div>
            </div>
        </header>

        <section class="unassigned-mail-section">
            @include('crm.emails_outlook', ['unassignedOnly' => true])
        </section>
    </main>
@endsection
