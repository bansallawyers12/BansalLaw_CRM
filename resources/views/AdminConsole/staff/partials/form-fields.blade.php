@php
    $mode = $mode ?? 'create';
    $isEdit = $mode === 'edit';
    $fieldPrefix = $fieldPrefix ?? ($isEdit ? 'edit_staff' : 'create_staff');
    $fetchedData = $fetchedData ?? null;
    $accordionId = $fieldPrefix . '_staff_accordion';
    $branchx = \App\Models\Branch::query()->orderBy('office_name')->get();
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
    $canGrantAssignEmailsBySubject = \App\Models\Staff::canGrantAssignEmailsBySubjectPermission(
        $actor instanceof \App\Models\Staff ? $actor : null
    );
    $canGrantPersonalCalendar = \App\Models\Staff::canGrantPersonalCalendarPermission(
        $actor instanceof \App\Models\Staff ? $actor : null
    );
    $canGrantMatterTeamAccess = \App\Models\Staff::canGrantManageMatterTeamAccessPermission(
        $actor instanceof \App\Models\Staff ? $actor : null
    );
    $canGrantFullCrmAllocationAccess = \App\Models\Staff::canGrantFullCrmAllocationAccessPermission(
        $actor instanceof \App\Models\Staff ? $actor : null
    );
    $permissionArr = [];
    if ($isEdit && $fetchedData && !empty($fetchedData->permission)) {
        $permissionArr = strpos($fetchedData->permission, ',') !== false
            ? explode(',', $fetchedData->permission)
            : [$fetchedData->permission];
    }
    $permValues = array_map('strval', (array) old('permission', $permissionArr));
    $mailboxPasswordConfigured = $isEdit && $fetchedData
        ? app(\App\Services\StaffMailboxService::class)->mailboxPasswordConfigured($fetchedData)
        : false;
    $predefinedRoleIds = array_map('intval', array_keys(config('crm_roles.defaults', [])));
    $currentRoleId = $isEdit ? (int) ($fetchedData->role ?? 0) : 0;
    $roleOptions = collect($usertype ?? [])->filter(function ($ut) use ($predefinedRoleIds, $currentRoleId) {
        $id = (int) $ut->id;

        return in_array($id, $predefinedRoleIds, true) || ($currentRoleId > 0 && $id === $currentRoleId);
    })->values();
    $officeId = old('office', $isEdit ? ($fetchedData->office_id ?? '') : '');
    if ($officeId === '' || $officeId === null) {
        $officeId = optional($branchx->first())->id ?? '';
    }
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
                <div class="col-md-6">
                    <div class="form-group mb-0">
                        <label for="{{ $fieldPrefix }}_role">User role</label>
                        <select name="role" id="{{ $fieldPrefix }}_role" class="form-control" required>
                            <option value="">Choose a role...</option>
                            @foreach ($roleOptions as $ut)
                                <option value="{{ $ut->id }}" @selected(old('role', $isEdit ? $fetchedData->role : '') == $ut->id)>{{ $ut->name }}</option>
                            @endforeach
                        </select>
                        <span class="field-error text-danger small" data-field="role"></span>
                    </div>
                </div>
                <input type="hidden" name="office" value="{{ $officeId }}">
            </div>
        </div>
    </div>

    {{-- 2. Permissions & access --}}
    <div class="accordion">
        <div class="accordion-header collapsed" role="button" data-bs-toggle="collapse" data-bs-target="#{{ $fieldPrefix }}_access" aria-expanded="false" aria-controls="{{ $fieldPrefix }}_access">
            <h4>Permissions & access</h4>
        </div>
        <div class="accordion-body collapse" id="{{ $fieldPrefix }}_access" data-bs-parent="#{{ $accordionId }}">
            <div class="staff-permission-options">
                @if($canQuick)
                <div class="form-group">
                    <input type="hidden" name="quick_access_enabled" value="0">
                    <label class="staff-checkbox-row">
                        <input type="checkbox" name="quick_access_enabled" value="1"
                            @checked(old('quick_access_enabled', $isEdit ? ($fetchedData->quick_access_enabled ?? false) : false))>
                        <span>Quick access enabled ({{ config('crm_access.quick_grant_minutes', 15) }}-minute cross-access requests)</span>
                    </label>
                    <small class="text-muted d-block mt-1">When this is on, a locked client or lead shows a {{ config('crm_access.quick_grant_minutes', 15) }}-minute Quick access button. The button stays hidden for people who already open every file (Super Admin role, Calling Team, and named partners). Turning this off revokes active quick grants immediately.</small>
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
                        <span>Admin access</span>
                    </label>
                    <small class="text-muted d-block mt-1">They can approve access requests and switch to full CRM privileges from the header. This does not open Admin Console and does not show other people’s calendars.</small>
                </div>
                @endif

                @if(\Illuminate\Support\Facades\Schema::hasColumn('staff', 'can_access_admin_console'))
                @php
                    $targetIsNativeSuperAdmin = $isEdit && (int) ($fetchedData->role ?? 0) === 1;
                    $consoleChecked = $targetIsNativeSuperAdmin
                        || (bool) old('can_access_admin_console', $isEdit ? ($fetchedData->can_access_admin_console ?? false) : false);
                @endphp
                <div class="form-group">
                    @if($isSuperAdminActor && ! $targetIsNativeSuperAdmin)
                        <input type="hidden" name="can_access_admin_console" value="0">
                    @endif
                    <label class="staff-checkbox-row">
                        <input type="checkbox" name="can_access_admin_console" value="1"
                            @checked($consoleChecked)
                            @disabled(! $isSuperAdminActor || $targetIsNativeSuperAdmin)>
                        <span>Access to Admin Console</span>
                    </label>
                    <small class="text-muted d-block mt-1">Opens the Admin Console menu. Admin access above does not include this. The Super Admin role always has it.</small>
                </div>
                @endif

                @if($canGrantMatterTeamAccess)
                <div class="form-group">
                    <small class="text-muted d-block">Temporary matter access on a client file is limited to Super Admin, Ajay Bansal, and Khushi Sangroya.</small>
                </div>
                @endif

                @if($canGrantFullCrmAllocationAccess && \Illuminate\Support\Facades\Schema::hasColumn('staff', 'has_full_crm_allocation_access'))
                <div class="form-group">
                    <input type="hidden" name="has_full_crm_allocation_access" value="0">
                    <label class="staff-checkbox-row">
                        <input type="checkbox" name="has_full_crm_allocation_access" value="1"
                            @checked(old('has_full_crm_allocation_access', $isEdit ? ($fetchedData->has_full_crm_allocation_access ?? false) : false))>
                        <span>Full CRM access (all clients, leads, matters &amp; email)</span>
                    </label>
                    <small class="text-muted d-block mt-1">Bypasses allocation and matter-team restrictions so this staff member can open any client, lead, matter, documents, and synced mailbox mail. Only native Super Admin can enable this.</small>
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

    {{-- Email access (mailbox permissions — leave the rules as they are for now) --}}
    <div class="accordion">
        <div class="accordion-header collapsed" role="button" data-bs-toggle="collapse" data-bs-target="#{{ $fieldPrefix }}_email_access" aria-expanded="false" aria-controls="{{ $fieldPrefix }}_email_access">
            <h4>Email access</h4>
        </div>
        <div class="accordion-body collapse" id="{{ $fieldPrefix }}_email_access" data-bs-parent="#{{ $accordionId }}">
            <div class="staff-permission-options">
                <p class="text-muted small mb-3">Mailbox permissions. These stay as they are for now.</p>
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

                @if($canGrantAssignEmailsBySubject && \Illuminate\Support\Facades\Schema::hasColumn('staff', 'can_assign_emails_by_subject'))
                <div class="form-group mb-0">
                    <input type="hidden" name="can_assign_emails_by_subject" value="0">
                    <label class="staff-checkbox-row">
                        <input type="checkbox" name="can_assign_emails_by_subject" value="1"
                            @checked(old('can_assign_emails_by_subject', $isEdit ? ($fetchedData->can_assign_emails_by_subject ?? false) : false))>
                        <span>Can assign unassigned emails by subject</span>
                    </label>
                    <small class="text-muted d-block mt-1">Shows the Assign by subject button on Unassigned Mail so this staff member can match and assign emails from client ID / matter in the subject.</small>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Calendar --}}
    <div class="accordion">
        <div class="accordion-header collapsed" role="button" data-bs-toggle="collapse" data-bs-target="#{{ $fieldPrefix }}_calendar" aria-expanded="false" aria-controls="{{ $fieldPrefix }}_calendar">
            <h4>Calendar</h4>
        </div>
        <div class="accordion-body collapse" id="{{ $fieldPrefix }}_calendar" data-bs-parent="#{{ $accordionId }}">
            @if($canGrantPersonalCalendar && \Illuminate\Support\Facades\Schema::hasColumn('staff', 'can_access_personal_calendar'))
                <input type="hidden" name="can_access_personal_calendar" value="1">
            @endif
            <p class="text-muted small">They always see their own personal calendar: bookings, reminders, follow-ups, hearings and deadlines.</p>
            @if($canGrantPersonalCalendar && \Illuminate\Support\Facades\Schema::hasColumn('staff', 'staff_calendar_visibility'))
            @php
                $seeAllCalendars = old('see_all_calendars', $isEdit && ($fetchedData->staffCalendarVisibility() ?? 'own') === 'all');
            @endphp
            <div class="form-group">
                <input type="hidden" name="see_all_calendars" value="0">
                <label class="staff-checkbox-row">
                    <input type="checkbox" name="see_all_calendars" value="1"
                        @checked($seeAllCalendars)>
                    <span>Can see all calendars</span>
                </label>
                <small class="text-muted d-block mt-1">Leave this off so they only see their own personal calendar. Tick it for calling team and reception so they can open every staff calendar and the website booking calendars.</small>
            </div>
            @endif
            @include('AdminConsole.staff.partials.default-calendar-type')
        </div>
    </div>

    {{-- Email & mailbox --}}
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
