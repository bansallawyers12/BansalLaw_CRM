@extends('layouts.crm_client_detail')
@section('title', 'Add Matter')
@section('content')
<script>window.location.replace(@json(route('adminconsole.features.matter.index', ['action' => 'create'])));</script>
<p class="p-4">Redirecting to <a href="{{ route('adminconsole.features.matter.index', ['action' => 'create']) }}">Matter List</a>...</p>
@endsection
