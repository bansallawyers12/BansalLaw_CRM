@props(['matter', 'workflowStages'])

@php
    $client = $matter->client;
    $migrationAgent = $matter->migrationAgent;
    $personResponsible = $matter->personResponsible;
    $personAssisting = $matter->personAssisting;
    
    // Get matter name
    if ($matter->sel_matter_id == 1) {
        $matter_name = 'General matter';
    } else {
        $matterModel = $matter->matter ?? null;
        $matter_name = $matterModel ? $matterModel->title : 'NA';
    }
    
    // Get email count
    $total_email_assign_cnt = $matter->mailReports()
        ->where('client_id', $matter->client_id)
        ->where('conversion_type', 'conversion_email_fetch')
        ->whereNull('mail_is_read')
        ->where(function($query) {
            $query->orWhere('mail_body_type', 'inbox')
                  ->orWhere('mail_body_type', 'sent');
        })->count();
@endphp

<tr>
    <td class="col-matter" style="white-space: initial;">
        <a href="{{ route('clients.detail', [base64_encode(convert_uuencode($matter->client_id)), $matter->client_unique_matter_no]) }}">
            {{ $matter_name }} ({{ $matter->client_unique_matter_no }})
        </a>
        <span class="totalEmailCntToClientMatter">{{ $total_email_assign_cnt }}</span>
    </td>
    <td class="col-client_id">
        <a href="{{ route('clients.detail', base64_encode(convert_uuencode($matter->client_id))) }}">
            {{ $client->client_id ?: config('constants.empty') }}
        </a>
    </td>
    <td class="col-client_name">
        {{ $client->first_name ?: config('constants.empty') }} {{ $client->last_name ?: config('constants.empty') }}
    </td>
    <td class="col-dob">
        @if($client && $client->dob)
            {{ \Carbon\Carbon::parse($client->dob)->format('d/m/Y') }}
        @else
            {{ config('constants.empty') }}
        @endif
    </td>
    <td class="col-migration_agent">
        {{ $migrationAgent ? $migrationAgent->first_name . ' ' . $migrationAgent->last_name : config('constants.empty') }}
    </td>
    <td class="col-person_responsible">
        {{ $personResponsible ? $personResponsible->first_name . ' ' . $personResponsible->last_name : config('constants.empty') }}
    </td>
    <td class="col-person_assisting">
        {{ $personAssisting ? $personAssisting->first_name . ' ' . $personAssisting->last_name : config('constants.empty') }}
    </td>
    <td class="col-stage">
        <select class="form-select stageCls" id="stage_{{ $matter->id }}" style="height: 30px;border-color: #e0e0e0;">
            @foreach($workflowStages as $stage)
                <option value="{{ $stage->id }}" {{ $matter->workflow_stage_id == $stage->id ? 'selected' : '' }}>
                    {{ $stage->name }}
                </option>
            @endforeach
        </select>
    </td>
</tr>
