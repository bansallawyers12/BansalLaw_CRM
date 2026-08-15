@php
    $mode = $mode ?? 'create';
    $isEdit = $mode === 'edit';
    $fieldPrefix = $fieldPrefix ?? ($isEdit ? 'edit_staff' : 'create_staff');
    $fetchedData = $fetchedData ?? null;
    $accordionId = $fieldPrefix . '_staff_accordion';
    $branchx = \App\Models\Branch::query()->orderBy('office_name')->get();
    $teams = \App\Models\Team::query()->orderBy('name')->get();
    $actor = auth()->guard('admin')->user();
    $canQuick = $actor instanceof \App\Models\Staff
        && app(\App\Services\CrmAccess\CrmAccessService::class)->canManageStaffQuickAccess($actor);
    $isSuperAdminActor = $actor instanceof \App\Models\Staff && (int) ($actor->role ?? 0) === 1;
    $canGrantEmailDelete = \App\Models\Staff::canGrantEmailDeleteWithAttachmentsPermission(
        $actor instanceof \App\Models\Staff ? $actor : null
    );
    $canGrantInboxSync = \App\Models\Staff::canGrantInboxSyncPermission(
        $actor instanceof \App\Models\Staff ? $actor : null
    );
    $canGrantViewAllSyncedInbox = \App\Models\Staff::canGrantViewAllSyncedInboxMailPermission(
        $actor instanceof \App\Models\Staff ? $actor : null
    );
    $canGrantPauseMailboxInboxSync = \App\Models\Staff::canGrantPauseMailboxInboxSyncPermission(
        $actor instanceof \App\Models\Staff ? $actor : null
    );
    $canGrantCloseDiscontinue = \App\Models\Staff::canGrantCloseDiscontinueMatterPermission(
        $actor instanceof \App\Models\Staff ? $actor : null
    );
    $canGrantFinalInvoiceEdit = \App\Models\Staff::canGrantFinalInvoiceEditPermission(
        $actor instanceof \App\Models\Staff ? $actor : null
    );
    $permissionArr = [];
    if ($isEdit && $fetchedData && !empty($fetchedData->permission)) {
        $permissionArr = strpos($fetchedData->permission, ',') !== false
            ? explode(',', $fetchedData->permission)
            : [$fetchedData->permission];
    }
    $permValues = array_map('strval', (array) old('permission', $permissionArr));
    $isSolicitorChecked = old('is_solicitor', $isEdit ? ($fetchedData->is_solicitor ?? 0) : 0);
    $mailboxPasswordConfigured = $isEdit && $fetchedData
        ? app(\App\Services\StaffMailboxService::class)->mailboxPasswordConfigured($fetchedData)
        : false;
@endphp

<div class="staff-modal-accordion" id="{{ $accordionId }}">
    {{-- 1. Personal details --}}
    <div class="accordion">
        <div class="accordion-header" role="button" data-bs-toggle="collapse" data-bs-target="#{{ $fieldPrefix }}_personal" aria-expanded="true" aria-controls="{{ $fieldPrefix }}_personal">
            <h4>Personal details</h4>
        </div>
        <div class="accordion-body collapse show" id="{{ $fieldPrefix }}_personal" data-bs-parent="#{{ $accordionId }}">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="form-group mb-0">
                        <label for="{{ $fieldPrefix }}_first_name">First name</label>
                        <input type="text" id="{{ $fieldPrefix }}_first_name" name="first_name" value="{{ old('first_name', $isEdit ? $fetchedData->first_name : '') }}" class="form-control" placeholder="First name" required>
                        <span class="field-error text-danger small" data-field="first_name"></span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-0">
                        <label for="{{ $fieldPrefix }}_last_name">Last name</label>
                        <input type="text" id="{{ $fieldPrefix }}_last_name" name="last_name" value="{{ old('last_name', $isEdit ? $fetchedData->last_name : '') }}" class="form-control" placeholder="Last name" required>
                        <span class="field-error text-danger small" data-field="last_name"></span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-0">
                        <label for="{{ $fieldPrefix }}_email">Email</label>
                        <input type="email" id="{{ $fieldPrefix }}_email" name="email" value="{{ old('email', $isEdit ? $fetchedData->email : '') }}" class="form-control" placeholder="Email address" required autocomplete="off">
                        <span class="field-error text-danger small" data-field="email"></span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-0">
                        <label for="{{ $fieldPrefix }}_phone">Phone number</label>
                        <div class="cus_field_input">
                            <div class="country_code">
                                <input class="telephone staff-country-code" id="{{ $fieldPrefix }}_telephone" type="tel" name="country_code" value="{{ old('country_code', $isEdit ? ($fetchedData->country_code ?? '') : '') }}">
                            </div>
                            <input type="text" id="{{ $fieldPrefix }}_phone" name="phone" value="{{ old('phone', $isEdit ? $fetchedData->phone : '') }}" class="form-control tel_input" placeholder="Phone number" required>
                        </div>
                        <span class="field-error text-danger small" data-field="phone"></span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-0">
                        <label for="{{ $fieldPrefix }}_password">{{ $isEdit ? 'New password (optional)' : 'Password (CRM login)' }}</label>
                        <input type="password" id="{{ $fieldPrefix }}_password" name="password" class="form-control" autocomplete="new-password" placeholder="{{ $isEdit ? 'Leave blank to keep current' : 'Min. 8 characters' }}" {{ $isEdit ? '' : 'required' }}>
                        <span class="field-error text-danger small" data-field="password"></span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-0">
                        <label for="{{ $fieldPrefix }}_password_confirmation">Confirm password</label>
                        <input type="password" id="{{ $fieldPrefix }}_password_confirmation" name="password_confirmation" class="form-control" autocomplete="new-password" placeholder="Repeat password" {{ $isEdit ? '' : 'required' }}>
                        <span class="field-error text-danger small" data-field="password_confirmation"></span>
                    </div>
                </div>
                @if($isEdit)
                <div class="col-md-6">
                    <div class="form-group mb-0">
                        <label for="{{ $fieldPrefix }}_status">Account status</label>
                        <select name="status" id="{{ $fieldPrefix }}_status" class="form-control">
                            <option value="1" @selected(old('status', $fetchedData->status ?? 1) == 1)>Active</option>
                            <option value="0" @selected(old('status', $fetchedData->status ?? 1) == 0)>Inactive</option>
                        </select>
                    </div>
                </div>
                @endif
                <div class="col-12">
                    <p class="text-muted small mb-0">Staff sign in at <a href="{{ url('/login') }}" target="_blank" rel="noopener">the CRM login page</a> using their email and password.</p>
                </div>
            </div>
        </div>
    </div>

    {{-- 2. Office & role --}}
    <div class="accordion">
        <div class="accordion-header collapsed" role="button" data-bs-toggle="collapse" data-bs-target="#{{ $fieldPrefix }}_office" aria-expanded="false" aria-controls="{{ $fieldPrefix }}_office">
            <h4>Office & role</h4>
        </div>
        <div class="accordion-body collapse" id="{{ $fieldPrefix }}_office" data-bs-parent="#{{ $accordionId }}">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="form-group mb-0">
                        <label for="{{ $fieldPrefix }}_position">Position title</label>
                        <input type="text" id="{{ $fieldPrefix }}_position" name="position" value="{{ old('position', $isEdit ? $fetchedData->position : '') }}" class="form-control" placeholder="Position title">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-0">
                        <label for="{{ $fieldPrefix }}_role">User role</label>
                        <select name="role" id="{{ $fieldPrefix }}_role" class="form-control" required>
                            <option value="">Choose one...</option>
                            @foreach ($usertype as $ut)
                                <option value="{{ $ut->id }}" @selected(old('role', $isEdit ? $fetchedData->role : '') == $ut->id)>{{ $ut->name }}</option>
                            @endforeach
                        </select>
                        <span class="field-error text-danger small" data-field="role"></span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-0">
                        <label for="{{ $fieldPrefix }}_office">Office</label>
                        <select class="form-control" name="office" id="{{ $fieldPrefix }}_office" required>
                            <option value="">Select office</option>
                            @foreach($branchx as $branch)
                                <option value="{{ $branch->id }}" @selected(old('office', $isEdit ? $fetchedData->office_id : '') == $branch->id)>{{ $branch->office_name }}</option>
                            @endforeach
                        </select>
                        <span class="field-error text-danger small" data-field="office"></span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-0">
                        <label for="{{ $fieldPrefix }}_team">Department (team)</label>
                        <select name="team" id="{{ $fieldPrefix }}_team" class="form-control">
                            <option value="">Choose one...</option>
                            @foreach ($teams as $tm)
                                <option value="{{ $tm->id }}" @selected(old('team', $isEdit ? $fetchedData->team : '') == $tm->id)>{{ $tm->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 3. Permissions & access --}}
    <div class="accordion">
        <div class="accordion-header collapsed" role="button" data-bs-toggle="collapse" data-bs-target="#{{ $fieldPrefix }}_access" aria-expanded="false" aria-controls="{{ $fieldPrefix }}_access">
            <h4>Permissions & access</h4>
        </div>
        <div class="accordion-body collapse" id="{{ $fieldPrefix }}_access" data-bs-parent="#{{ $accordionId }}">
            <div class="staff-access-options">
                @if($canQuick)
                <div class="form-group">
                    <input type="hidden" name="quick_access_enabled" value="0">
                    <label class="staff-checkbox-row">
                        <input type="checkbox" name="quick_access_enabled" value="1"
                            @checked(old('quick_access_enabled', $isEdit ? ($fetchedData->quick_access_enabled ?? false) : false))>
                        <span>Quick access enabled ({{ config('crm_access.quick_grant_minutes', 15) }}-minute cross-access requests)</span>
                    </label>
                </div>
                @endif

                @if($isEdit)
                <div class="form-group">
                    @if($isSuperAdminActor)
                        <input type="hidden" name="grant_super_admin_access" value="0">
                    @endif
                    <label class="staff-checkbox-row">
                        <input type="checkbox" name="grant_super_admin_access" value="1" class="staff-grant-super-admin"
                            @checked(old('grant_super_admin_access', $fetchedData->grant_super_admin_access ?? false))
                            @disabled(!$isSuperAdminActor)>
                        <span>Grant Super Admin access level</span>
                    </label>
                </div>
                @if($isSuperAdminActor && \Illuminate\Support\Facades\Schema::hasColumn('staff', 'trust_rule42_supervisor'))
                <div class="form-group">
                    <input type="hidden" name="trust_rule42_supervisor" value="0">
                    <label class="staff-checkbox-row">
                        <input type="checkbox" name="trust_rule42_supervisor" value="1"
                            @checked(old('trust_rule42_supervisor', $fetchedData->trust_rule42_supervisor ?? false))>
                        <span>Rule 42 trust supervisor</span>
                    </label>
                </div>
                @endif
                @endif

                @if($canGrantEmailDelete && \Illuminate\Support\Facades\Schema::hasColumn('staff', 'can_delete_email_with_attachments'))
                <div class="form-group">
                    <input type="hidden" name="can_delete_email_with_attachments" value="0">
                    <label class="staff-checkbox-row">
                        <input type="checkbox" name="can_delete_email_with_attachments" value="1"
                            @checked(old('can_delete_email_with_attachments', $isEdit ? ($fetchedData->can_delete_email_with_attachments ?? false) : false))>
                        <span>Can delete emails with attachments</span>
                    </label>
                </div>
                @endif

                @if($canGrantInboxSync && \Illuminate\Support\Facades\Schema::hasColumn('staff', 'can_sync_inbox_emails'))
                <div class="form-group">
                    <input type="hidden" name="can_sync_inbox_emails" value="0">
                    <label class="staff-checkbox-row">
                        <input type="checkbox" name="can_sync_inbox_emails" value="1"
                            @checked(old('can_sync_inbox_emails', $isEdit ? ($fetchedData->can_sync_inbox_emails ?? false) : false))>
                        <span>Can sync inbox from Zoho (Sync button &amp; Unassigned folder)</span>
                    </label>
                </div>
                @endif

                @if($canGrantViewAllSyncedInbox && \Illuminate\Support\Facades\Schema::hasColumn('staff', 'can_view_all_synced_inbox_mail'))
                <div class="form-group">
                    <input type="hidden" name="can_view_all_synced_inbox_mail" value="0">
                    <label class="staff-checkbox-row">
                        <input type="checkbox" name="can_view_all_synced_inbox_mail" value="1"
                            @checked(old('can_view_all_synced_inbox_mail', $isEdit ? ($fetchedData->can_view_all_synced_inbox_mail ?? false) : false))>
                        <span>Can view and sync all mailboxes</span>
                    </label>
                    <small class="text-muted d-block mt-1">Gives this staff member the same Unassigned Mail access as Super Admin: all assigned and unassigned mail, mailbox and sender filters, mailbox/range selection, and the Sync now button.</small>
                </div>
                @endif

                @if($canGrantPauseMailboxInboxSync && \Illuminate\Support\Facades\Schema::hasColumn('staff', 'can_pause_mailbox_inbox_sync'))
                <div class="form-group">
                    <input type="hidden" name="can_pause_mailbox_inbox_sync" value="0">
                    <label class="staff-checkbox-row">
                        <input type="checkbox" name="can_pause_mailbox_inbox_sync" value="1"
                            @checked(old('can_pause_mailbox_inbox_sync', $isEdit ? ($fetchedData->can_pause_mailbox_inbox_sync ?? false) : false))>
                        <span>Can pause and start inbox sync for any mailbox</span>
                    </label>
                    <small class="text-muted d-block mt-1">Lets this staff member pause or resume automatic IMAP sync for any account on Admin Console → Email (Inbox Sync column).</small>
                </div>
                @endif

                @if($canGrantCloseDiscontinue && \Illuminate\Support\Facades\Schema::hasColumn('staff', 'can_close_discontinue_matter'))
                <div class="form-group">
                    <input type="hidden" name="can_close_discontinue_matter" value="0">
                    <label class="staff-checkbox-row">
                        <input type="checkbox" name="can_close_discontinue_matter" value="1"
                            @checked(old('can_close_discontinue_matter', $isEdit ? ($fetchedData->can_close_discontinue_matter ?? false) : false))>
                        <span>Can close/discontinue matters</span>
                    </label>
                </div>
                @endif

                @if($canGrantFinalInvoiceEdit && \Illuminate\Support\Facades\Schema::hasColumn('staff', 'can_edit_final_invoice'))
                <div class="form-group">
                    <input type="hidden" name="can_edit_final_invoice" value="0">
                    <label class="staff-checkbox-row">
                        <input type="checkbox" name="can_edit_final_invoice" value="1"
                            @checked(old('can_edit_final_invoice', $isEdit ? ($fetchedData->can_edit_final_invoice ?? false) : false))>
                        <span>Can edit unpaid final invoices</span>
                    </label>
                    <small class="text-muted d-block mt-1">Allows this staff member to amend a saved invoice before any payment is applied. All edits are recorded in the client timeline.</small>
                </div>
                @endif

                <div class="form-group">
                    <label class="d-block mb-2">Module permissions</label>
                    <div class="staff-permission-grid">
                        <div class="staff-perm-group">
                            <strong>Notes</strong>
                            <label class="staff-perm-item"><input value="1" type="checkbox" name="permission[]" @checked(in_array('1', $permValues, true))> View</label>
                            <label class="staff-perm-item"><input value="2" type="checkbox" name="permission[]" @checked(in_array('2', $permValues, true))> Add/Edit</label>
                            <label class="staff-perm-item"><input value="3" type="checkbox" name="permission[]" @checked(in_array('3', $permValues, true))> Delete</label>
                        </div>
                        <div class="staff-perm-group">
                            <strong>Documents</strong>
                            <label class="staff-perm-item"><input value="4" type="checkbox" name="permission[]" @checked(in_array('4', $permValues, true))> View</label>
                            <label class="staff-perm-item"><input value="5" type="checkbox" name="permission[]" @checked(in_array('5', $permValues, true))> Add/Edit</label>
                            <label class="staff-perm-item"><input value="6" type="checkbox" name="permission[]" @checked(in_array('6', $permValues, true))> Delete</label>
                        </div>
                    </div>
                </div>
                <div class="form-group mb-0">
                    <label class="staff-checkbox-row">
                        <input value="1" type="checkbox" name="show_dashboard_per" @checked(old('show_dashboard_per', $isEdit ? ($fetchedData->show_dashboard_per ?? 0) : 0))>
                        <span>Can view on dashboard</span>
                    </label>
                </div>
            </div>
        </div>
    </div>

    {{-- 4. Legal practitioner --}}
    <div class="accordion">
        <div class="accordion-header collapsed" role="button" data-bs-toggle="collapse" data-bs-target="#{{ $fieldPrefix }}_solicitor" aria-expanded="false" aria-controls="{{ $fieldPrefix }}_solicitor">
            <h4>Legal practitioner <span class="text-muted fw-normal">(optional)</span></h4>
        </div>
        <div class="accordion-body collapse" id="{{ $fieldPrefix }}_solicitor" data-bs-parent="#{{ $accordionId }}">
            <label class="staff-checkbox-row mb-3">
                <input type="checkbox" id="{{ $fieldPrefix }}_is_solicitor" name="is_solicitor" value="1" class="staff-is-solicitor-toggle"
                    @checked($isSolicitorChecked)>
                <span>This staff member is a legal practitioner</span>
            </label>
            <div id="{{ $fieldPrefix }}_agent_details_section" class="staff-agent-details" style="display: {{ $isSolicitorChecked ? 'block' : 'none' }};">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-group mb-0">
                            <label for="{{ $fieldPrefix }}_legal_practitioner_number">Practising certificate / legal practitioner number</label>
                            <input type="text" name="legal_practitioner_number" id="{{ $fieldPrefix }}_legal_practitioner_number" value="{{ old('legal_practitioner_number', $isEdit ? $fetchedData->legal_practitioner_number : '') }}" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-0">
                            <label for="{{ $fieldPrefix }}_company_name">Business name</label>
                            <input type="text" name="company_name" value="{{ old('company_name', $isEdit ? $fetchedData->company_name : '') }}" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-0">
                            <label for="{{ $fieldPrefix }}_tax_number">Tax number (ABN/ACN)</label>
                            <input type="text" name="tax_number" value="{{ old('tax_number', $isEdit ? $fetchedData->tax_number : '') }}" class="form-control">
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-group mb-0">
                            <label for="{{ $fieldPrefix }}_business_address">Business address</label>
                            <textarea name="business_address" id="{{ $fieldPrefix }}_business_address" class="form-control" rows="2">{{ old('business_address', $isEdit ? $fetchedData->business_address : '') }}</textarea>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-0">
                            <label for="{{ $fieldPrefix }}_business_phone">Business phone</label>
                            <input type="text" name="business_phone" value="{{ old('business_phone', $isEdit ? $fetchedData->business_phone : '') }}" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-0">
                            <label for="{{ $fieldPrefix }}_business_mobile">Business mobile</label>
                            <input type="text" name="business_mobile" value="{{ old('business_mobile', $isEdit ? $fetchedData->business_mobile : '') }}" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-0">
                            <label for="{{ $fieldPrefix }}_business_email">Business email</label>
                            <input type="email" name="business_email" value="{{ old('business_email', $isEdit ? $fetchedData->business_email : '') }}" class="form-control">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 5. Email & mailbox --}}
    <div class="accordion mb-0">
        <div class="accordion-header collapsed" role="button" data-bs-toggle="collapse" data-bs-target="#{{ $fieldPrefix }}_signature" aria-expanded="false" aria-controls="{{ $fieldPrefix }}_signature">
            <h4>Email & mailbox</h4>
        </div>
        <div class="accordion-body collapse" id="{{ $fieldPrefix }}_signature" data-bs-parent="#{{ $accordionId }}">
            <div class="row g-3">
                <div class="col-12">
                    <div class="form-group mb-0">
                        <label for="{{ $fieldPrefix }}_zoho_app_password">Zoho app password</label>
                        <input type="password" id="{{ $fieldPrefix }}_zoho_app_password" name="zoho_app_password" class="form-control" autocomplete="new-password" placeholder="{{ $isEdit ? 'Leave blank to keep existing password' : 'Required to send email from CRM' }}">
                        @if($isEdit)
                            <p class="text-muted small mb-0 mt-1">
                                @if($mailboxPasswordConfigured)
                                    <span class="text-success"><i class="fa-solid fa-circle-check"></i> App password is saved for this mailbox.</span>
                                @else
                                    <span class="text-warning"><i class="fa-solid fa-triangle-exclamation"></i> No app password saved yet — staff cannot send email from the CRM until one is set.</span>
                                @endif
                            </p>
                        @else
                            <p class="text-muted small mb-0 mt-1">Generate this in Zoho Mail → Security → App Passwords. It is stored for SMTP sending and is separate from the CRM login password above.</p>
                        @endif
                        <span class="field-error text-danger small" data-field="zoho_app_password"></span>
                    </div>
                </div>
                <div class="col-12">
                    <label for="{{ $fieldPrefix }}_email_signature">Email signature</label>
                    <p class="text-muted small">Added automatically when this staff member sends email from the CRM. Paste HTML via Source code, or insert a table from the toolbar — a live preview is shown below.</p>
                    <textarea class="form-control tinymce-editor-full staff-email-signature" name="email_signature" id="{{ $fieldPrefix }}_email_signature">{{ old('email_signature', $isEdit ? ($fetchedData->email_signature ?? '') : '') }}</textarea>
                    <div class="staff-signature-preview-wrap">
                        <div class="staff-signature-preview-label">HTML preview</div>
                        <iframe class="staff-signature-preview" id="{{ $fieldPrefix }}_email_signature_preview" title="Signature HTML preview"></iframe>
                    </div>
                    <span class="field-error text-danger small" data-field="email_signature"></span>
                </div>
            </div>
        </div>
    </div>
</div>

<p class="staff-modal-scroll-hint text-muted small mb-0 mt-2">
    <i class="fa-solid fa-up-down-left-right-v"></i> Expand each section above or scroll to see all fields.
</p>
