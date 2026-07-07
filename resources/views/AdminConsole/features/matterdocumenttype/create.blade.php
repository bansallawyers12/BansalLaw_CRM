@extends('layouts.crm_client_detail')
@section('title', 'Add Matter Document Folder')
@section('content')
<script>window.location.replace(@json(route('adminconsole.features.matterdocumenttype.index', ['action' => 'create'])));</script>
<p class="p-4">Redirecting to <a href="{{ route('adminconsole.features.matterdocumenttype.index', ['action' => 'create']) }}">Matter Document Folders</a>...</p>
@endsection
