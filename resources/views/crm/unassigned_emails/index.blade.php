@extends('layouts.crm_client_detail_dashboard')

@section('title', 'Unassigned Mail')

@section('content')
    <main class="main-content unassigned-mail-page">
        <section class="unassigned-mail-section">
            @include('crm.emails_outlook', ['unassignedOnly' => true])
        </section>
    </main>
@endsection
