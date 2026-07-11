@extends('layouts.crm_client_detail')
@section('title', 'Other Parties')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/listing-pagination.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-container.css') }}">
@endsection

@section('content')
<div class="listing-container">
    <section class="listing-section" style="padding-top: 40px;">
        <div class="listing-section-body">
            @include('../Elements/flash-message')

            <div class="card">
                <div class="card-header">
                    <h4>Other Parties</h4>
                    <div class="card-header-actions">
                        <a href="{{ route('leads.create', ['other_party' => 1]) }}" class="btn btn-primary">Create Other Party</a>
                        <select name="per_page" id="per_page" class="form-control per-page-select" style="max-width:120px;">
                            @foreach([10, 20, 50, 100] as $option)
                                <option value="{{ $option }}" {{ ($perPage ?? 20) == $option ? 'selected' : '' }}>{{ $option }} / page</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="card-body">
                    <ul class="nav nav-pills mb-3">
                        <li class="nav-item"><a class="nav-link" href="{{ URL::to('/clients') }}">Clients</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ URL::to('/leads') }}">Leads</a></li>
                        <li class="nav-item"><a class="nav-link active" href="{{ route('leads.other_parties.index') }}">Other Parties</a></li>
                    </ul>

                    <form action="{{ route('leads.other_parties.index') }}" method="get" class="mb-3">
                        <div class="row g-2">
                            <div class="col-md-3">
                                <input type="text" name="client_id" class="form-control" placeholder="ID ref" value="{{ request('client_id') }}">
                            </div>
                            <div class="col-md-3">
                                <input type="text" name="name" class="form-control" placeholder="Name" value="{{ request('name') }}">
                            </div>
                            <div class="col-md-3">
                                <input type="text" name="email" class="form-control" placeholder="Email" value="{{ request('email') }}">
                            </div>
                            <div class="col-md-3">
                                <input type="text" name="phone" class="form-control" placeholder="Phone" value="{{ request('phone') }}">
                            </div>
                        </div>
                        <div class="mt-2">
                            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                            <a href="{{ route('leads.other_parties.index') }}" class="btn btn-secondary btn-sm">Reset</a>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>@sortablelink('first_name', 'Name')</th>
                                    <th>Ref</th>
                                    <th>Phone</th>
                                    <th>Email</th>
                                    <th>@sortablelink('created_at', 'Created')</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($lists as $list)
                                    <tr>
                                        <td>
                                            <a href="{{ route('clients.detail', base64_encode(convert_uuencode($list->id))) }}">
                                                {{ $list->first_name }} {{ $list->last_name }}
                                            </a>
                                        </td>
                                        <td>{{ $list->client_id }}</td>
                                        <td>{{ $list->phone ?: '—' }}</td>
                                        <td>{{ $list->email ?: '—' }}</td>
                                        <td>{{ $list->created_at ? $list->created_at->format('d/m/Y') : '—' }}</td>
                                        <td>
                                            <a href="{{ route('clients.edit', base64_encode(convert_uuencode($list->id))) }}" class="btn btn-sm btn-outline-primary" title="Edit">
                                                <i class="fa-solid fa-pen"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-muted text-center py-4">No other parties found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div class="text-muted small">Showing {{ $lists->count() }} of {{ $totalData ?? 0 }}</div>
                        {{ $lists->links() }}
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@section('scripts')
<script>
document.getElementById('per_page')?.addEventListener('change', function () {
    var url = new URL(window.location.href);
    url.searchParams.set('per_page', this.value);
    window.location.href = url.toString();
});
</script>
@endsection
