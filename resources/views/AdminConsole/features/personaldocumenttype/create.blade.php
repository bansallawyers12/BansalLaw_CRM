@extends('layouts.crm_client_detail')
@section('title', 'Add Personal Document Folder')
@section('content')
<script>window.location.replace(@json(route('adminconsole.features.personaldocumenttype.index', ['action' => 'create'])));</script>
<p class="p-4">Redirecting to <a href="{{ route('adminconsole.features.personaldocumenttype.index', ['action' => 'create']) }}">Personal Document Folders</a>...</p>
@endsection
