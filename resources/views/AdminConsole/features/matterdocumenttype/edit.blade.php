@extends('layouts.crm_client_detail')
@section('title', 'Edit Matter Document Folder')
@section('content')
@php
    $redirectUrl = route('adminconsole.features.matterdocumenttype.index', ['action' => 'edit', 'id' => $fetchedData->id ?? '']);
@endphp
<script>window.location.replace(@json($redirectUrl));</script>
<p class="p-4">Redirecting to <a href="{{ $redirectUrl }}">Matter Document Folders</a>...</p>
@endsection
