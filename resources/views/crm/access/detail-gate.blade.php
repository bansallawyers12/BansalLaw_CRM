@extends('layouts.crm_client_detail')
@section('title', 'Request access')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">
<div class="main-content">
    <section class="section">
        <div class="section-body">
            <div class="card">
                <div class="card-body py-5 text-center">
                    <h5 class="mb-2">Access required</h5>
                    <p class="text-muted mb-0">Please request access to open this client detail.</p>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
