@extends('layouts.crm_client_detail')
@section('title', 'Edit Personal Document Folder')
@section('content')
@php
    $redirectUrl = route('adminconsole.features.personaldocumenttype.index', ['action' => 'edit', 'id' => $fetchedData->id ?? '']);
@endphp
<script>window.location.replace(@json($redirectUrl));</script>
<p class="p-4">Redirecting to <a href="{{ $redirectUrl }}">Personal Document Folders</a>...</p>
@endsection
