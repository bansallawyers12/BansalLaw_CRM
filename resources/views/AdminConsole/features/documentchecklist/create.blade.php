@extends('layouts.crm_client_detail')
@section('title', 'Add Checklist')
@section('content')
<script>window.location.replace(@json(route('adminconsole.features.documentchecklist.index', ['action' => 'create'])));</script>
<p class="p-4">Redirecting to <a href="{{ route('adminconsole.features.documentchecklist.index', ['action' => 'create']) }}">Document Checklist</a>...</p>
@endsection
