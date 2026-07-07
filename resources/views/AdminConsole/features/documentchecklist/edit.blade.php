@extends('layouts.crm_client_detail')
@section('title', 'Edit Checklist')
@section('content')
@php
    $redirectUrl = route('adminconsole.features.documentchecklist.index', ['action' => 'edit', 'id' => $fetchedData->id ?? '']);
@endphp
<script>window.location.replace(@json($redirectUrl));</script>
<p class="p-4">Redirecting to <a href="{{ $redirectUrl }}">Document Checklist</a>...</p>
@endsection
