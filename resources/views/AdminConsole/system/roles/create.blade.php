@extends('layouts.crm_client_detail')
@section('title', 'Create Roles and Permissions')
@section('content')
<script>window.location.replace(@json(route('adminconsole.system.roles.index', ['action' => 'create'])));</script>
<p class="p-4">Redirecting to <a href="{{ route('adminconsole.system.roles.index', ['action' => 'create']) }}">Roles</a>...</p>
@endsection
