@extends('layouts.crm_client_detail')
@section('title', 'Edit Roles and Permissions')
@section('content')
@php
    $redirectUrl = route('adminconsole.system.roles.index', ['action' => 'edit', 'id' => $fetchedData->id ?? '']);
@endphp
<script>window.location.replace(@json($redirectUrl));</script>
<p class="p-4">Redirecting to <a href="{{ $redirectUrl }}">Roles</a>...</p>
@endsection
