@extends('layouts.crm_client_detail')
@section('title', 'Activity Search')

@section('styles')
<style>
    .adminconsole-activity-search .ts-wrapper { width: 100% !important; max-width: 100% !important; }
    .adminconsole-activity-search .activity-search-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.9rem 1rem;
    }
    .adminconsole-activity-search .activity-search-grid .as-field {
        min-width: 0;
    }
    @media (max-width: 991.98px) {
        .adminconsole-activity-search .activity-search-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    @media (max-width: 575.98px) {
        .adminconsole-activity-search .activity-search-grid {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>
@endsection

@section('content')

<div class="main-content adminconsole-features adminconsole-activity-search">
    <section class="section">
        <div class="section-body">
            <div class="server-error">
                @include('../Elements/flash-message')
            </div>
            <div class="custom-error-msg"></div>

            <div class="row">
                <div class="col-3 col-md-3 col-lg-3">
                    @include('../Elements/CRM/setting')
                </div>

                <div class="col-9 col-md-9 col-lg-9">
                    <div class="card activity-search-card">
                        <div class="card-header activity-search-header">
                            <div class="activity-search-title-block">
                                <h4>Activity Search</h4>
                                <p class="activity-search-subtitle">Find staff and client activity across the CRM</p>
                            </div>
                            <div class="card-header-action">
                                @if(isset($totalActivities) && $totalActivities > 0)
                                    <button type="button" class="btn btn-outline-primary activity-search-export-btn" onclick="exportActivities()">
                                        <i class="fa-solid fa-file-export me-1"></i> Export
                                    </button>
                                @endif
                            </div>
                        </div>

                        <div class="card-body">
                            <form action="{{ route('adminconsole.system.activity-search.index') }}" method="GET" id="searchForm" class="activity-search-form">
                                <input type="hidden" name="search" value="1">

                                <div class="activity-search-filters">
                                    <div class="activity-search-grid">
                                        <div class="as-field">
                                            <label for="assigner_id" class="form-label">Assigner</label>
                                            <select name="assigner_id" id="assigner_id" class="form-control crm-ts-activity-search crm-ts-activity-search-staff">
                                                <option value="">All assigners</option>
                                                @foreach($staffList as $staff)
                                                    <option value="{{ $staff['id'] }}"
                                                        data-email="{{ $staff['email'] }}"
                                                        {{ request('assigner_id') == $staff['id'] ? 'selected' : '' }}>
                                                        {{ $staff['name'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="as-field">
                                            <label for="assignee_id" class="form-label">Assignee</label>
                                            <select name="assignee_id" id="assignee_id" class="form-control crm-ts-activity-search crm-ts-activity-search-staff">
                                                <option value="">All assignees</option>
                                                @foreach($staffList as $staff)
                                                    <option value="{{ $staff['id'] }}"
                                                        data-email="{{ $staff['email'] }}"
                                                        {{ request('assignee_id') == $staff['id'] ? 'selected' : '' }}>
                                                        {{ $staff['name'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="as-field">
                                            <label for="client_id" class="form-label">Client</label>
                                            <select name="client_id" id="client_id" class="form-control crm-ts-activity-search crm-ts-activity-search-ajax">
                                                <option value="">All clients</option>
                                                @if(request('client_id'))
                                                    <option value="{{ request('client_id') }}" selected>
                                                        {{ request('client_name', 'Selected Client') }}
                                                    </option>
                                                @endif
                                            </select>
                                        </div>

                                        <div class="as-field">
                                            <label for="activity_type" class="form-label">Activity type</label>
                                            <select name="activity_type" id="activity_type" class="form-control crm-ts-activity-search">
                                                <option value="">All types</option>
                                                @foreach($activityTypes as $key => $label)
                                                    <option value="{{ $key }}"
                                                        {{ request('activity_type') == $key ? 'selected' : '' }}>
                                                        {{ $label }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="as-field">
                                            <label for="task_group" class="form-label">Task category</label>
                                            <select name="task_group" id="task_group" class="form-control crm-ts-activity-search">
                                                <option value="">All categories</option>
                                                @foreach($taskGroups as $key => $label)
                                                    <option value="{{ $key }}"
                                                        {{ request('task_group') == $key ? 'selected' : '' }}>
                                                        {{ $label }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="as-field">
                                            <label for="task_status" class="form-label">Task status</label>
                                            <select name="task_status" id="task_status" class="form-control crm-ts-activity-search">
                                                <option value="">All statuses</option>
                                                <option value="0" {{ request('task_status') === '0' ? 'selected' : '' }}>Incomplete</option>
                                                <option value="1" {{ request('task_status') === '1' ? 'selected' : '' }}>Completed</option>
                                            </select>
                                        </div>

                                        <div class="as-field">
                                            <label for="keyword" class="form-label">Keyword</label>
                                            <div class="activity-search-input-wrap">
                                                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                                                <input type="text" name="keyword" id="keyword" class="form-control"
                                                       placeholder="Subject or description"
                                                       value="{{ request('keyword') }}">
                                            </div>
                                        </div>

                                        <div class="as-field">
                                            <label for="date_from" class="form-label">Date from</label>
                                            <input type="date" name="date_from" id="date_from" class="form-control"
                                                   value="{{ request('date_from') }}">
                                        </div>

                                        <div class="as-field">
                                            <label for="date_to" class="form-label">Date to</label>
                                            <input type="date" name="date_to" id="date_to" class="form-control"
                                                   value="{{ request('date_to') }}">
                                        </div>
                                    </div>

                                    <div class="activity-search-actions">
                                        <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                                            Reset
                                        </button>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fa-solid fa-magnifying-glass me-1"></i> Search
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <div class="activity-search-results">
                                @if(request('search'))
                                    <div class="activity-search-results-head">
                                        <h5>Results</h5>
                                        <span class="activity-search-count">{{ number_format($totalActivities) }} found</span>
                                    </div>

                                    @if($activities->count() > 0)
                                        <div class="table-responsive">
                                            <table class="table table-hover text_wrap activity-search-table">
                                                <thead>
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>Assigner</th>
                                                        <th>Assignee</th>
                                                        <th>Client</th>
                                                        <th>Type</th>
                                                        <th>Subject</th>
                                                        <th>Status</th>
                                                        <th></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($activities as $activity)
                                                        <tr>
                                                            <td class="activity-search-date">
                                                                <span class="activity-search-date-day">{{ $activity->created_at ? $activity->created_at->format('Y-m-d') : 'N/A' }}</span>
                                                                <span class="activity-search-date-time">{{ $activity->created_at ? $activity->created_at->format('h:i A') : '' }}</span>
                                                            </td>
                                                            <td>
                                                                <span class="activity-search-person">{{ $activity->creator_first_name }} {{ $activity->creator_last_name }}</span>
                                                                <span class="activity-search-meta">{{ $activity->creator_email }}</span>
                                                            </td>
                                                            <td>
                                                                @if($activity->assignee_first_name)
                                                                    <span class="activity-search-person">{{ $activity->assignee_first_name }} {{ $activity->assignee_last_name }}</span>
                                                                    <span class="activity-search-meta">{{ $activity->assignee_email }}</span>
                                                                @else
                                                                    <span class="activity-search-meta">—</span>
                                                                @endif
                                                            </td>
                                                            <td>
                                                                @if($activity->client_first_name)
                                                                    <a href="{{ route('clients.detail', base64_encode(convert_uuencode($activity->client_id))) }}" target="_blank" rel="noopener">
                                                                        {{ $activity->client_first_name }} {{ $activity->client_last_name }}
                                                                    </a>
                                                                @else
                                                                    <span class="activity-search-meta">—</span>
                                                                @endif
                                                            </td>
                                                            <td>
                                                                @php
                                                                    $typeLabels = [
                                                                        'activity' => ['label' => 'Activity', 'tone' => 'navy'],
                                                                        'sms' => ['label' => 'SMS', 'tone' => 'info'],
                                                                        'email' => ['label' => 'Email', 'tone' => 'navy'],
                                                                        'document' => ['label' => 'Document', 'tone' => 'info'],
                                                                        'note' => ['label' => 'Note', 'tone' => 'gold'],
                                                                        'financial' => ['label' => 'Financial', 'tone' => 'success'],
                                                                    ];
                                                                    $typeInfo = $typeLabels[$activity->activity_type] ?? ['label' => ucfirst($activity->activity_type ?? 'N/A'), 'tone' => 'muted'];
                                                                @endphp
                                                                <span class="activity-search-pill activity-search-pill--{{ $typeInfo['tone'] }}">{{ $typeInfo['label'] }}</span>
                                                                @if($activity->task_group)
                                                                    <span class="activity-search-meta">{{ $activity->task_group }}</span>
                                                                @endif
                                                            </td>
                                                            <td>
                                                                <span class="activity-search-subject">{{ \Illuminate\Support\Str::limit($activity->subject, 50) }}</span>
                                                                @if($activity->description)
                                                                    <span class="activity-search-meta">{!! \Illuminate\Support\Str::limit(strip_tags($activity->description), 80) !!}</span>
                                                                @endif
                                                            </td>
                                                            <td>
                                                                @if($activity->task_group)
                                                                    @if($activity->task_status == 1)
                                                                        <span class="activity-search-pill activity-search-pill--success">Complete</span>
                                                                    @else
                                                                        <span class="activity-search-pill activity-search-pill--gold">Pending</span>
                                                                    @endif
                                                                @else
                                                                    <span class="activity-search-meta">—</span>
                                                                @endif
                                                            </td>
                                                            <td class="text-nowrap activity-search-row-actions">
                                                                <button type="button" class="btn btn-sm btn-outline-primary"
                                                                        onclick="viewActivityDetails({{ $activity->id }})"
                                                                        data-bs-toggle="tooltip" title="View details">
                                                                    <i class="fa-solid fa-eye"></i>
                                                                </button>
                                                                @if($activity->client_id)
                                                                    <a href="{{ route('clients.detail', base64_encode(convert_uuencode($activity->client_id))) }}"
                                                                       target="_blank"
                                                                       rel="noopener"
                                                                       class="btn btn-sm btn-primary"
                                                                       data-bs-toggle="tooltip" title="View client">
                                                                        <i class="fa-solid fa-user"></i>
                                                                    </a>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>

                                        <div class="activity-search-pagination">
                                            {{ $activities->links() }}
                                        </div>
                                    @else
                                        <div class="activity-search-empty activity-search-empty--soft">
                                            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                                            <p>No activities match these filters. Try widening the date range or clearing a filter.</p>
                                        </div>
                                    @endif
                                @else
                                    <div class="activity-search-empty activity-search-empty-hint">
                                        <div class="activity-search-empty-icon">
                                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                                        </div>
                                        <h5>Search activities</h5>
                                        <p>Filter by staff, client, type, or date, then run a search.</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<div class="modal fade activity-details-modal" id="activityDetailsModal" tabindex="-1" aria-labelledby="activityDetailsModalLabel" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="activityDetailsModalLabel">Activity details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="activityDetailsContent">
                <div class="text-center">
                    <i class="fa-solid fa-spinner fa-spin"></i> Loading...
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
$(document).ready(function() {
    var clientSearchUrl = @json(route('adminconsole.system.activity-search.search-clients'));

    var staffTsRender = {
        item: function (data, escape) {
            return '<div class="as-ts-item" title="' + escape(data.text || '') + '">' + escape(data.text || '') + '</div>';
        },
        option: function (data, escape) {
            var email = data.email || '';
            if (!email && data.$option) {
                email = data.$option.getAttribute('data-email') || '';
            }
            var html = '<div class="as-ts-option"><span class="as-ts-option-name">' + escape(data.text || '') + '</span>';
            if (email) {
                html += '<span class="as-ts-option-email">' + escape(email) + '</span>';
            }
            html += '</div>';
            return html;
        }
    };

    $('.crm-ts-activity-search:not(.crm-ts-activity-search-ajax)').each(function () {
        var isStaff = $(this).hasClass('crm-ts-activity-search-staff');
        var cfg = {
            plugins: ['clear_button'],
            allowEmptyOption: true,
            placeholder: $(this).find('option[value=""]').text() || 'Select an option',
            create: false,
            maxOptions: null,
            render: isStaff ? staffTsRender : {
                item: function (data, escape) {
                    return '<div class="as-ts-item" title="' + escape(data.text || '') + '">' + escape(data.text || '') + '</div>';
                }
            }
        };
        if (isStaff) {
            cfg.searchField = ['text', 'email'];
            cfg.onInitialize = function () {
                var self = this;
                Object.keys(self.options).forEach(function (value) {
                    var opt = self.options[value];
                    if (opt && opt.$option) {
                        opt.email = opt.$option.getAttribute('data-email') || '';
                    }
                });
            };
        }
        initTS(this, cfg);
    });

    initTS('#client_id', {
        plugins: ['clear_button'],
        valueField: 'id',
        labelField: 'text',
        searchField: ['text'],
        loadThrottle: 250,
        placeholder: 'Search for a client...',
        allowEmptyOption: true,
        render: {
            item: function (data, escape) {
                return '<div class="as-ts-item" title="' + escape(data.text || '') + '">' + escape(data.text || '') + '</div>';
            }
        },
        shouldLoad: function (query) {
            return query.length >= 2;
        },
        load: function (query, callback) {
            if (query.length < 2) {
                callback();
                return;
            }
            var url = clientSearchUrl + (clientSearchUrl.indexOf('?') >= 0 ? '&' : '?') + 'q=' + encodeURIComponent(query);
            fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    callback($.isArray(data) ? data : []);
                })
                .catch(function () {
                    callback();
                });
        }
    });

    $('[data-bs-toggle="tooltip"]').tooltip();
});

function resetForm() {
    window.location.href = '{{ route("adminconsole.system.activity-search.index") }}';
}

function exportActivities() {
    const form = document.getElementById('searchForm');
    const formData = new FormData(form);
    const params = new URLSearchParams(formData);

    window.location.href = '{{ route("adminconsole.system.activity-search.export") }}?' + params.toString();
}

function viewActivityDetails(activityId) {
    $('#activityDetailsModal').modal('show');

    $.ajax({
        url: '/crm/activities',
        method: 'GET',
        data: { id: activityId },
        success: function(response) {
            if (response.status) {
                let html = '<div class="activity-details">';
                html += '<table class="table table-borderless">';
                html += '<tr><th width="30%">Activity ID:</th><td>#' + activityId + '</td></tr>';
                html += '<tr><th>Subject:</th><td>' + (response.data.subject || 'N/A') + '</td></tr>';
                html += '<tr><th>Description:</th><td>' + (response.data.description || 'N/A') + '</td></tr>';
                html += '<tr><th>Activity Type:</th><td>' + (response.data.activity_type || 'N/A') + '</td></tr>';
                html += '<tr><th>Created At:</th><td>' + (response.data.created_at ? (typeof formatDisplayDateTime === 'function' ? (formatDisplayDateTime(response.data.created_at) || 'N/A') : String(response.data.created_at)) : 'N/A') + '</td></tr>';
                html += '</table>';
                html += '</div>';

                $('#activityDetailsContent').html(html);
            } else {
                $('#activityDetailsContent').html('<div class="alert alert-danger">Failed to load activity details.</div>');
            }
        },
        error: function() {
            $('#activityDetailsContent').html('<div class="alert alert-danger">Error loading activity details.</div>');
        }
    });
}
</script>
@endsection
