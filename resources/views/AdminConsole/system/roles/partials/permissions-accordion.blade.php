@php
    $fieldPrefix = $fieldPrefix ?? 'create_role';
    $accordionId = $fieldPrefix . '_module_accordion';
    $isEdit = $isEdit ?? false;
@endphp

<div id="{{ $accordionId }}" class="role_accordion roles-module-accordion">
    <div class="accordion">
        <div class="accordion-header" role="button" data-bs-toggle="collapse"
            data-bs-target="#{{ $fieldPrefix }}_panel_1" aria-expanded="true">
            <h4>Office & teams</h4>
        </div>
        <div class="accordion-body collapse show" id="{{ $fieldPrefix }}_panel_1" data-bs-parent="#{{ $accordionId }}">
            <div class="select_toggle">
                <button type="button" data-class="office_team" class="btn btn-sm btn-primary roles-select-all">Select all</button>
                <button type="button" data-class="office_team" class="btn btn-sm btn-outline-secondary roles-deselect-all">Deselect all</button>
            </div>
            <ul class="roles-perm-list">
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[1]" class="office_team" @checked($permChecked(1))> Can create new offices, edit and archive all the associated offices.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[2]" class="office_team" @checked($permChecked(2))> Can only view associated office details and its information.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[3]" class="office_team" @checked($permChecked(3))> Can invite users, cancel invitations, edit and change their status.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[4]" class="office_team" @checked($permChecked(4))> Can only view users list and details of associated offices.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[5]" class="office_team" @checked($permChecked(5))> Can access Service Page.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[6]" class="office_team" @checked($permChecked(6))> Can manage Roles and Permissions</label></li>
            </ul>
        </div>
    </div>

    <div class="accordion">
        <div class="accordion-header collapsed" role="button" data-bs-toggle="collapse" data-bs-target="#{{ $fieldPrefix }}_panel_2">
            <h4>Workflows</h4>
        </div>
        <div class="accordion-body collapse" id="{{ $fieldPrefix }}_panel_2" data-bs-parent="#{{ $accordionId }}">
            <div class="select_toggle">
                <button type="button" data-class="workflows" class="btn btn-sm btn-primary roles-select-all">Select all</button>
                <button type="button" data-class="workflows" class="btn btn-sm btn-outline-secondary roles-deselect-all">Deselect all</button>
            </div>
            <ul class="roles-perm-list">
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[81]" class="workflows" @checked($permChecked(81))> Can add, edit and delete Workflow and its stages.</label></li>
            </ul>
        </div>
    </div>

    <div class="accordion">
        <div class="accordion-header collapsed" role="button" data-bs-toggle="collapse" data-bs-target="#{{ $fieldPrefix }}_panel_3">
            <h4>Partners</h4>
        </div>
        <div class="accordion-body collapse" id="{{ $fieldPrefix }}_panel_3" data-bs-parent="#{{ $accordionId }}">
            <div class="select_toggle">
                <button type="button" data-class="partners" class="btn btn-sm btn-primary roles-select-all">Select all</button>
                <button type="button" data-class="partners" class="btn btn-sm btn-outline-secondary roles-deselect-all">Deselect all</button>
            </div>
            <ul class="roles-perm-list">
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[7]" class="partners" @checked($permChecked(7))> Can add and edit partners.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[8]" class="partners" @checked($permChecked(8))> Can only view partners information without commission percentage.</label></li>
                @if(!$isEdit)
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[9]" class="partners" @checked($permChecked(9))> Can view partner's commission percentage.</label></li>
                @endif
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[10]" class="partners" @checked($permChecked(10))> Can delete partner.</label></li>
                @if(!$isEdit)
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[11]" class="partners" @checked($permChecked(11))> Can delete partner's primary contact</label></li>
                @endif
            </ul>
        </div>
    </div>

    <div class="accordion">
        <div class="accordion-header collapsed" role="button" data-bs-toggle="collapse" data-bs-target="#{{ $fieldPrefix }}_panel_4">
            <h4>Products</h4>
        </div>
        <div class="accordion-body collapse" id="{{ $fieldPrefix }}_panel_4" data-bs-parent="#{{ $accordionId }}">
            <div class="select_toggle">
                <button type="button" data-class="products" class="btn btn-sm btn-primary roles-select-all">Select all</button>
                <button type="button" data-class="products" class="btn btn-sm btn-outline-secondary roles-deselect-all">Deselect all</button>
            </div>
            <ul class="roles-perm-list">
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[12]" class="products" @checked($permChecked(12))> Can add and edit products.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[13]" class="products" @checked($permChecked(13))> Can only view product's Information.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[14]" class="products" @checked($permChecked(14))> Can delete products.</label></li>
            </ul>
        </div>
    </div>

    <div class="accordion">
        <div class="accordion-header collapsed" role="button" data-bs-toggle="collapse" data-bs-target="#{{ $fieldPrefix }}_panel_6">
            <h4>Clients</h4>
        </div>
        <div class="accordion-body collapse" id="{{ $fieldPrefix }}_panel_6" data-bs-parent="#{{ $accordionId }}">
            <div class="select_toggle">
                <button type="button" data-class="clients" class="btn btn-sm btn-primary roles-select-all">Select all</button>
                <button type="button" data-class="clients" class="btn btn-sm btn-outline-secondary roles-deselect-all">Deselect all</button>
            </div>
            <ul class="roles-perm-list">
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[20]" class="clients" @checked($permChecked(20))> Can view all the clients of all the associated offices. Can assign clients to any users of the associated offices, respectively.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[21]" class="clients" @checked($permChecked(21))> Can add, edit and archive the clients.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[22]" class="clients" @checked($permChecked(22))> Can only edit and archive assigned clients.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[23]" class="clients" @checked($permChecked(23))> Can only view assigned clients.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[24]" class="clients" @checked($permChecked(24))> Can delete client.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[25]" class="clients" @checked($permChecked(25))> Can delete client's comments.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[26]" class="clients" @checked($permChecked(26))> Can delete client's interested services.</label></li>
            </ul>
        </div>
    </div>

    <div class="accordion">
        <div class="accordion-header collapsed" role="button" data-bs-toggle="collapse" data-bs-target="#{{ $fieldPrefix }}_panel_7">
            <h4>Interested services</h4>
        </div>
        <div class="accordion-body collapse" id="{{ $fieldPrefix }}_panel_7" data-bs-parent="#{{ $accordionId }}">
            <div class="select_toggle">
                <button type="button" data-class="interested_service" class="btn btn-sm btn-primary roles-select-all">Select all</button>
                <button type="button" data-class="interested_service" class="btn btn-sm btn-outline-secondary roles-deselect-all">Deselect all</button>
            </div>
            <ul class="roles-perm-list">
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[30]" class="interested_service" @checked($permChecked(30))> Can view commission in product fees of Interested Services.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[31]" class="interested_service" @checked($permChecked(31))> Can edit commission in product fees of Interested Services.</label></li>
            </ul>
        </div>
    </div>

    <div class="accordion">
        <div class="accordion-header collapsed" role="button" data-bs-toggle="collapse" data-bs-target="#{{ $fieldPrefix }}_panel_8">
            <h4>Matters</h4>
        </div>
        <div class="accordion-body collapse" id="{{ $fieldPrefix }}_panel_8" data-bs-parent="#{{ $accordionId }}">
            <div class="select_toggle">
                <button type="button" data-class="applications" class="btn btn-sm btn-primary roles-select-all">Select all</button>
                <button type="button" data-class="applications" class="btn btn-sm btn-outline-secondary roles-deselect-all">Deselect all</button>
            </div>
            <ul class="roles-perm-list">
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[34]" class="applications" @checked($permChecked(34))> Can create matters.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[35]" class="applications" @checked($permChecked(35))> Can delete matters.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[40]" class="applications" @checked($permChecked(40))> Can view/edit assigned and added matter by the users of primary office.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[41]" class="applications" @checked($permChecked(41))> Can view/edit assigned and added matter by the users of secondary office.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[45]" class="applications" @checked($permChecked(45))> Can reopen discontinued matters.</label></li>
            </ul>
        </div>
    </div>

    <div class="accordion">
        <div class="accordion-header collapsed" role="button" data-bs-toggle="collapse" data-bs-target="#{{ $fieldPrefix }}_panel_9">
            <h4>Accounts</h4>
        </div>
        <div class="accordion-body collapse" id="{{ $fieldPrefix }}_panel_9" data-bs-parent="#{{ $accordionId }}">
            <div class="select_toggle">
                <button type="button" data-class="accounts" class="btn btn-sm btn-primary roles-select-all">Select all</button>
                <button type="button" data-class="accounts" class="btn btn-sm btn-outline-secondary roles-deselect-all">Deselect all</button>
            </div>
            <ul class="roles-perm-list">
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[46]" class="accounts" @checked($permChecked(46))> Can create invoices of associated offices.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[47]" class="accounts" @checked($permChecked(47))> Can add, edit, delete and make/revert payments of clients invoices of associated offices.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[48]" class="accounts" @checked($permChecked(48))> Can add, edit, delete and make/revert payments of invoices of only assigned clients.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[49]" class="accounts" @checked($permChecked(49))> Can view invoices of only assigned clients.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[50]" class="accounts" @checked($permChecked(50))> Can view invoices of all the clients of associated offices and shared matters.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[51]" class="accounts" @checked($permChecked(51))> Can view income shared receivables of associated offices.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[52]" class="accounts" @checked($permChecked(52))> Can make payments, revert and delete payables of income shared offices.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[53]" class="accounts" @checked($permChecked(53))> Can view income shared payables.</label></li>
            </ul>
        </div>
    </div>

    <div class="accordion">
        <div class="accordion-header collapsed" role="button" data-bs-toggle="collapse" data-bs-target="#{{ $fieldPrefix }}_panel_10">
            <h4>Quotations</h4>
        </div>
        <div class="accordion-body collapse" id="{{ $fieldPrefix }}_panel_10" data-bs-parent="#{{ $accordionId }}">
            <div class="select_toggle">
                <button type="button" data-class="quotations" class="btn btn-sm btn-primary roles-select-all">Select all</button>
                <button type="button" data-class="quotations" class="btn btn-sm btn-outline-secondary roles-deselect-all">Deselect all</button>
            </div>
            <ul class="roles-perm-list">
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[54]" class="quotations" @checked($permChecked(54))> Can create quotation templates.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[55]" class="quotations" @checked($permChecked(55))> Can edit quotation templates.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[56]" class="quotations" @checked($permChecked(56))> Can delete quotation templates.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[57]" class="quotations" @checked($permChecked(57))> Can create quotations.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[58]" class="quotations" @checked($permChecked(58))> Can edit quotations.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[59]" class="quotations" @checked($permChecked(59))> Can archive quotations.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[60]" class="quotations" @checked($permChecked(60))> Can view quotations.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[61]" class="quotations" @checked($permChecked(61))> Can delete quotations.</label></li>
            </ul>
        </div>
    </div>

    <div class="accordion">
        <div class="accordion-header collapsed" role="button" data-bs-toggle="collapse" data-bs-target="#{{ $fieldPrefix }}_panel_11">
            <h4>Reports</h4>
        </div>
        <div class="accordion-body collapse" id="{{ $fieldPrefix }}_panel_11" data-bs-parent="#{{ $accordionId }}">
            <div class="select_toggle">
                <button type="button" data-class="reports" class="btn btn-sm btn-primary roles-select-all">Select all</button>
                <button type="button" data-class="reports" class="btn btn-sm btn-outline-secondary roles-deselect-all">Deselect all</button>
            </div>
            <ul class="roles-perm-list">
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[62]" class="reports" @checked($permChecked(62))> Can view Client and Matter Reports.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[63]" class="reports" @checked($permChecked(63))> Can view Invoice Report.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[64]" class="reports" @checked($permChecked(64))> Can view Office Check-In Report.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[67]" class="reports" @checked($permChecked(67))> Can view personal task report.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[68]" class="reports" @checked($permChecked(68))> Can view all task report.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[69]" class="reports" @checked($permChecked(69))> Can export all reports.</label></li>
            </ul>
        </div>
    </div>

    <div class="accordion">
        <div class="accordion-header collapsed" role="button" data-bs-toggle="collapse" data-bs-target="#{{ $fieldPrefix }}_panel_12">
            <h4>Appointments</h4>
        </div>
        <div class="accordion-body collapse" id="{{ $fieldPrefix }}_panel_12" data-bs-parent="#{{ $accordionId }}">
            <div class="select_toggle">
                <button type="button" data-class="appointments" class="btn btn-sm btn-primary roles-select-all">Select all</button>
                <button type="button" data-class="appointments" class="btn btn-sm btn-outline-secondary roles-deselect-all">Deselect all</button>
            </div>
            <ul class="roles-perm-list">
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[70]" class="appointments" @checked($permChecked(70))> Can manage Partners appointments.</label></li>
            </ul>
        </div>
    </div>

    <div class="accordion">
        <div class="accordion-header collapsed" role="button" data-bs-toggle="collapse" data-bs-target="#{{ $fieldPrefix }}_panel_13">
            <h4>Tasks</h4>
        </div>
        <div class="accordion-body collapse" id="{{ $fieldPrefix }}_panel_13" data-bs-parent="#{{ $accordionId }}">
            <div class="select_toggle">
                <button type="button" data-class="tasks" class="btn btn-sm btn-primary roles-select-all">Select all</button>
                <button type="button" data-class="tasks" class="btn btn-sm btn-outline-secondary roles-deselect-all">Deselect all</button>
            </div>
            <ul class="roles-perm-list">
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[82]" class="tasks" @checked($permChecked(82))> Can create tasks.</label></li>
            </ul>
        </div>
    </div>

    <div class="accordion">
        <div class="accordion-header collapsed" role="button" data-bs-toggle="collapse" data-bs-target="#{{ $fieldPrefix }}_panel_14">
            <h4>Office check-in</h4>
        </div>
        <div class="accordion-body collapse" id="{{ $fieldPrefix }}_panel_14" data-bs-parent="#{{ $accordionId }}">
            <div class="select_toggle">
                <button type="button" data-class="office_checkin" class="btn btn-sm btn-primary roles-select-all">Select all</button>
                <button type="button" data-class="office_checkin" class="btn btn-sm btn-outline-secondary roles-deselect-all">Deselect all</button>
            </div>
            <ul class="roles-perm-list">
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[71]" class="office_checkin" @checked($permChecked(71))> Can add office check-ins.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[72]" class="office_checkin" @checked($permChecked(72))> Can edit office check-ins.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[73]" class="office_checkin" @checked($permChecked(73))> Can view office check-ins assigned only.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[74]" class="office_checkin" @checked($permChecked(74))> Can view all office check-ins.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[75]" class="office_checkin" @checked($permChecked(75))> Can archive office check-ins.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[76]" class="office_checkin" @checked($permChecked(76))> Can delete office check-ins.</label></li>
            </ul>
        </div>
    </div>

    <div class="accordion">
        <div class="accordion-header collapsed" role="button" data-bs-toggle="collapse" data-bs-target="#{{ $fieldPrefix }}_panel_15">
            <h4>Document checklist</h4>
        </div>
        <div class="accordion-body collapse" id="{{ $fieldPrefix }}_panel_15" data-bs-parent="#{{ $accordionId }}">
            <div class="select_toggle">
                <button type="button" data-class="document_checklist" class="btn btn-sm btn-primary roles-select-all">Select all</button>
                <button type="button" data-class="document_checklist" class="btn btn-sm btn-outline-secondary roles-deselect-all">Deselect all</button>
            </div>
            <ul class="roles-perm-list">
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[77]" class="document_checklist" @checked($permChecked(77))> Can add and rename document type.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[78]" class="document_checklist" @checked($permChecked(78))> Can activate/deactivate document type.</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[79]" class="document_checklist" @checked($permChecked(79))> Can add and edit document checklist</label></li>
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[80]" class="document_checklist" @checked($permChecked(80))> Can activate/deactivate document checklist</label></li>
            </ul>
        </div>
    </div>

    <div class="accordion mb-0">
        <div class="accordion-header collapsed" role="button" data-bs-toggle="collapse" data-bs-target="#{{ $fieldPrefix }}_panel_16">
            <h4>View on dashboard</h4>
        </div>
        <div class="accordion-body collapse" id="{{ $fieldPrefix }}_panel_16" data-bs-parent="#{{ $accordionId }}">
            <div class="select_toggle">
                <button type="button" data-class="view_on_dashboard" class="btn btn-sm btn-primary roles-select-all">Select all</button>
                <button type="button" data-class="view_on_dashboard" class="btn btn-sm btn-outline-secondary roles-deselect-all">Deselect all</button>
            </div>
            <ul class="roles-perm-list">
                <li><label class="roles-perm-item"><input type="checkbox" name="module_access[83]" class="view_on_dashboard" @checked($permChecked(83))> Can view on dashboard</label></li>
            </ul>
        </div>
    </div>
</div>
