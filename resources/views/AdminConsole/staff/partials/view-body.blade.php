@php
    $teamName = 'N/A';
    if (!empty($fetchedData->team)) {
        $teamData = \App\Models\Team::select('name')->where('id', $fetchedData->team)->first();
        $teamName = $teamData ? $teamData->name : 'N/A';
    }
    $permissionArr = [];
    if (!empty($fetchedData->permission)) {
        $permissionArr = strpos($fetchedData->permission, ',') !== false
            ? explode(',', $fetchedData->permission)
            : [$fetchedData->permission];
    }
@endphp

<div class="row staff-view-grid">
    <div class="col-md-6">
        <div class="staff-view-section">
            <h5 class="staff-view-section__title">Personal details</h5>
            <dl class="staff-view-dl">
                <dt>Name</dt>
                <dd>{{ $fetchedData->first_name }} {{ $fetchedData->last_name }}</dd>
                <dt>Email</dt>
                <dd>{{ $fetchedData->email }}</dd>
                <dt>Status</dt>
                <dd>
                    @if((int) ($fetchedData->status ?? 1) === 1)
                        <span class="badge bg-success">Active — can log in</span>
                    @else
                        <span class="badge bg-secondary">Inactive — login disabled</span>
                    @endif
                </dd>
                <dt>Phone</dt>
                <dd>{{ trim(($fetchedData->country_code ?? '') . ' ' . ($fetchedData->phone ?? '')) ?: 'N/A' }}</dd>
                <dt>Zoho mailbox</dt>
                <dd>
                    @if(app(\App\Services\StaffMailboxService::class)->mailboxPasswordConfigured($fetchedData))
                        <span class="badge bg-success">App password configured</span>
                    @else
                        <span class="badge bg-warning text-dark">App password not set</span>
                    @endif
                </dd>
                <dt>Inbox sync access</dt>
                <dd>
                    @if($fetchedData->canSyncInboxEmails())
                        <span class="badge bg-success">Can sync inbox from Zoho</span>
                    @else
                        <span class="badge bg-secondary">No inbox sync access</span>
                    @endif
                </dd>
                <dt>Full mailbox access</dt>
                <dd>
                    @if($fetchedData->canViewAllSyncedInboxMail())
                        <span class="badge bg-success">Can view and sync all mailboxes</span>
                    @else
                        <span class="badge bg-secondary">Recipient-only mailbox view</span>
                    @endif
                </dd>
                <dt>Pause mailbox sync</dt>
                <dd>
                    @if($fetchedData->canPauseMailboxInboxSync())
                        <span class="badge bg-success">Can pause and start sync for any mailbox</span>
                    @else
                        <span class="badge bg-secondary">Cannot pause mailbox sync</span>
                    @endif
                </dd>
            </dl>
        </div>
    </div>
    <div class="col-md-6">
        <div class="staff-view-section">
            <h5 class="staff-view-section__title">Office details</h5>
            <dl class="staff-view-dl">
                <dt>Position</dt>
                <dd>{{ $fetchedData->position ?: 'N/A' }}</dd>
                <dt>Role</dt>
                <dd>{{ optional($fetchedData->usertype)->name ?? 'N/A' }}</dd>
                <dt>Office</dt>
                <dd>{{ optional($fetchedData->office)->office_name ?? 'N/A' }}</dd>
                <dt>Team</dt>
                <dd>{{ $teamName }}</dd>
                <dt>Dashboard</dt>
                <dd>{{ (int) ($fetchedData->show_dashboard_per ?? 0) === 1 ? 'Can view dashboard' : 'Cannot view dashboard' }}</dd>
            </dl>
        </div>
    </div>

    @if(!empty($permissionArr))
    <div class="col-12">
        <div class="staff-view-section">
            <h5 class="staff-view-section__title">Permissions</h5>
            <div class="d-flex flex-wrap gap-2">
                @foreach(['1' => 'Notes: View', '2' => 'Notes: Add/Edit', '3' => 'Notes: Delete', '4' => 'Documents: View', '5' => 'Documents: Add/Edit', '6' => 'Documents: Delete'] as $perm => $label)
                    @if(in_array((string) $perm, $permissionArr, true) || in_array((int) $perm, $permissionArr, true))
                        <span class="badge bg-light text-dark border">{{ $label }}</span>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
    @endif

    @if((int) ($fetchedData->is_solicitor ?? 0) === 1)
    <div class="col-12">
        <div class="staff-view-section">
            <h5 class="staff-view-section__title">Legal practitioner details</h5>
            <dl class="staff-view-dl row">
                <div class="col-md-6"><dt>Practising certificate / legal practitioner number</dt><dd>{{ $fetchedData->legal_practitioner_number ?: 'N/A' }}</dd></div>
                <div class="col-md-6"><dt>Business name</dt><dd>{{ $fetchedData->company_name ?: 'N/A' }}</dd></div>
                <div class="col-md-6"><dt>Tax number</dt><dd>{{ $fetchedData->tax_number ?: 'N/A' }}</dd></div>
                <div class="col-12"><dt>Business address</dt><dd>{{ $fetchedData->business_address ?: 'N/A' }}</dd></div>
            </dl>
        </div>
    </div>
    @endif
</div>
