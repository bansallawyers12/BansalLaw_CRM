    <div id="menu2" class="tab-pane fade matter-tab-pane">
      @php
          $__crmEditLeadType = isset($fetchedData) && (($fetchedData->type ?? null) === 1 || in_array(trim((string) ($fetchedData->type ?? '')), ['lead', 'l', '1'], true));
          $editMatterList = $clientMatters ?? collect();
          $editDetailBase = isset($fetchedData) ? url('/clients/detail/' . base64_encode(convert_uuencode($fetchedData->id))) : '';
          $matterIconMap = [
              'CIV'   => ['icon' => 'fa-balance-scale', 'color' => '#4a6fa5'],
              'CRM'   => ['icon' => 'fa-gavel',         'color' => '#c0392b'],
              'FAM'   => ['icon' => 'fa-heart',         'color' => '#e67e22'],
              'PROP'  => ['icon' => 'fa-home',          'color' => '#27ae60'],
              'CORP'  => ['icon' => 'fa-building',      'color' => '#8e44ad'],
              'LAB'   => ['icon' => 'fa-briefcase',     'color' => '#2980b9'],
              'CONS'  => ['icon' => 'fa-shopping-cart', 'color' => '#16a085'],
              'BANK'  => ['icon' => 'fa-university',    'color' => '#d35400'],
              'TAX'   => ['icon' => 'fa-calculator',   'color' => '#7f8c8d'],
              'IP'    => ['icon' => 'fa-lightbulb',     'color' => '#f39c12'],
              'CONST' => ['icon' => 'fa-scroll',        'color' => '#1a5276'],
              'REV'   => ['icon' => 'fa-map',           'color' => '#117a65'],
              'MACT'  => ['icon' => 'fa-car-crash',     'color' => '#922b21'],
              'MERITS'=> ['icon' => 'fa-clipboard-list','color' => '#5d6d7e'],
              'JR'    => ['icon' => 'fa-search',        'color' => '#1f618d'],
              'NOICC' => ['icon' => 'fa-bell',          'color' => '#b7950b'],
              'IMM'   => ['icon' => 'fa-passport',      'color' => '#154360'],
          ];
          $defaultIcon = ['icon' => 'fa-folder-open', 'color' => '#555'];
      @endphp

      <h3><i class="fas fa-briefcase"></i> Matter Details</h3>

      {{-- ====== STEP 1: Matter Type Selector ====== --}}
      <section class="content-section" id="matterTypeSelectorSection">
        <section class="form-section">
          <div class="section-header">
            <h3><i class="fas fa-list-alt"></i> Select Matter Type</h3>
            <span class="badge" style="background:#e8f0fe;color:#3b5bdb;font-size:0.8em;padding:4px 10px;border-radius:12px;">Required to add a new matter</span>
          </div>
          <p class="text-muted" style="margin-bottom:1rem;">Select a law matter type from the dropdown to add a new matter for this client.</p>

          @php $allMattersList = $allMatters ?? collect(); @endphp
          <div class="row">
            <div class="col-md-6">
              <div class="form-group matter-type-dropdown-wrap">
                <label for="matterTypeDropdown" style="font-weight:600;">Law Matter Type <span class="text-danger">*</span></label>
                <div style="position:relative;">
                  <select id="matterTypeDropdown" class="form-control matter-type-select" onchange="onMatterDropdownChange(this)"
                    style="height:44px;font-size:0.97em;border:2px solid #d0daf5;border-radius:8px;padding-left:12px;appearance:auto;">
                    <option value="">— Select a matter type —</option>
                    @foreach($allMattersList->sortBy('id') as $mt)
                      <option value="{{ $mt->id }}"
                        data-nick="{{ $mt->nick_name }}"
                        data-title="{{ $mt->title }}"
                        data-stream="{{ $mt->stream ?? '' }}">
                        {{ $loop->iteration }}. {{ $mt->title }}
                      </option>
                    @endforeach
                  </select>
                </div>
              </div>
            </div>
            <div class="col-md-6" style="display:flex;align-items:flex-end;padding-bottom:15px;">
              <div id="matterDropdownPreview" style="display:none;align-items:center;gap:10px;background:#f0f4ff;border-radius:8px;padding:8px 16px;border:1px solid #d0daf5;">
                <i id="matterDropdownIcon" class="fas fa-folder-open" style="font-size:1.5rem;"></i>
                <span id="matterDropdownLabel" style="font-weight:600;color:#3b5bdb;font-size:0.95em;"></span>
              </div>
            </div>
          </div>

          <div id="matterDropdownCTA" style="display:none;margin-top:4px;">
            <button type="button" class="btn btn-primary" onclick="openMatterFormFromDropdown()" style="border-radius:8px;">
              <i class="fas fa-plus-circle"></i> Add this Matter
            </button>
            <button type="button" class="btn btn-default" onclick="resetMatterDropdown()" style="margin-left:8px;border-radius:8px;">
              <i class="fas fa-times"></i> Clear
            </button>
          </div>
        </section>
      </section>

      {{-- ====== STEP 2: Dynamic Matter Form (shown after matter type selected) ====== --}}
      <section class="content-section" id="matterDynamicFormSection" style="display:none;">
        <section class="form-section">
          <div class="section-header" style="align-items:center;">
            <h3 id="matterDynamicFormTitle"><i class="fas fa-folder-plus"></i> New Matter Details</h3>
            <button type="button" class="btn btn-sm btn-secondary" onclick="clearMatterTypeSelection()" style="margin-left:auto;">
              <i class="fas fa-times"></i> Cancel
            </button>
          </div>

          <div id="editAddMatterMsg2" style="margin-bottom:10px;"></div>

          {{-- Selected matter badge --}}
          <div id="selectedMatterBadge" style="margin-bottom:1.2rem;"></div>

          {{-- Dynamic sub-type selector + matter-specific fields (all rendered by JS) --}}
          <div id="matterSpecificFields"></div>

          <div id="dynLegalPartySection" class="form-group" style="margin-top:1rem;padding:1rem;background:#fafbfc;border:1px solid #e9ecef;border-radius:8px;">
            <label for="dyn_our_party_role" style="font-weight:600;">Our client&rsquo;s role <small class="text-muted">(optional)</small></label>
            <select class="form-control" id="dyn_our_party_role" style="max-width:420px;">
              <option value="">—</option>
            </select>
            <label style="margin-top:0.9rem;font-weight:600;">Other parties <small class="text-muted">(optional)</small></label>
            <div id="dyn_opposing_parties_wrap"></div>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="dyn_add_opposing_party_btn" style="margin-top:6px;">
              <i class="fas fa-plus"></i> Add other party
            </button>
          </div>

          {{-- Case detail (common) --}}
          <div class="form-group" style="margin-top:0.5rem;">
            <label>Case Detail / Additional Notes <small class="text-muted">(optional)</small></label>
            <textarea class="form-control" id="dyn_case_detail" rows="5" maxlength="5000" placeholder="Describe the matter, key facts, client's instructions..."></textarea>
          </div>

          <div style="margin-top:1rem;">
            <button type="button" class="btn btn-primary" id="dynSubmitMatterBtn" onclick="submitDynamicMatter()">
              <i class="fas fa-plus-circle"></i> Create Matter
            </button>
            <button type="button" class="btn btn-secondary" onclick="clearMatterTypeSelection()" style="margin-left:8px;">Cancel</button>
          </div>
        </section>
      </section>

      {{-- ====== STEP 3: Existing Matters Table ====== --}}
      <section class="content-section">
        <section class="form-section matter-tab-section__card">
          <div class="section-header matter-tab-section__header">
            <div>
              <h3 class="matter-tab-section__title"><i class="fas fa-folder-open"></i> Existing Matters</h3>
              <p class="matter-tab-section__subtitle text-muted">Active matters for {{ $fetchedData->first_name }} {{ $fetchedData->last_name }} ({{ $__crmEditLeadType ? 'Lead' : 'Client' }} ID: {{ $fetchedData->client_id }})</p>
            </div>
          </div>
          @if($editMatterList->isEmpty())
              <div class="matter-tab-empty">
                  <div class="matter-tab-empty__icon"><i class="fas fa-briefcase"></i></div>
                  <p class="matter-tab-empty__title">No matters yet</p>
                  <p class="matter-tab-empty__hint text-muted">Select a matter type above to create the first matter for this {{ $__crmEditLeadType ? 'lead' : 'client' }}.</p>
              </div>
          @else
              <div class="table-responsive matter-tab-table-wrap">
                  <table class="table table-hover matter-tab-table">
                      <thead>
                          <tr>
                              <th>Matter Ref</th>
                              <th>Type</th>
                              <th>Stage</th>
                              <th>Status</th>
                              <th>Date of Incident</th>
                              <th>Case Detail</th>
                              <th class="text-end">Actions</th>
                          </tr>
                      </thead>
                      <tbody>
                          @foreach($editMatterList as $cmatter)
                              @php
                                  $ref = $cmatter->client_unique_matter_no;
                                  $detailUrl = $ref !== null && $ref !== ''
                                      ? $editDetailBase . '/' . $ref
                                      : $editDetailBase;
                                  $typeLabel = $cmatter->matter
                                      ? $cmatter->matter->title
                                      : '—';
                                  $typeNick  = $cmatter->matter->nick_name ?? '';
                                  $rowIconData = $matterIconMap[$typeNick] ?? $defaultIcon;
                                  $caseSnippet = trim((string) ($cmatter->case_detail ?? ''));
                              @endphp
                              <tr>
                                  <td>
                                      <a href="{{ $detailUrl }}" class="matter-tab-ref-link">{{ $ref !== null && $ref !== '' ? $ref : '—' }}</a>
                                  </td>
                                  <td>
                                    <i class="fas {{ $rowIconData['icon'] }}" style="color:{{ $rowIconData['color'] }};margin-right:5px;"></i>
                                    {{ \Illuminate\Support\Str::limit($typeLabel, 50) }}
                                  </td>
                                  <td>{{ $cmatter->workflowStage->name ?? '—' }}</td>
                                  <td>
                                      @if((int) $cmatter->matter_status === 1)
                                          <span class="label label-success">Active</span>
                                      @else
                                          <span class="label label-default">Closed</span>
                                      @endif
                                  </td>
                                  <td>
                                      @if($cmatter->date_of_incidence)
                                          {{ $cmatter->date_of_incidence->format('d/m/Y') }}
                                      @else
                                          <span class="text-muted">—</span>
                                      @endif
                                  </td>
                                  <td class="matter-tab-case-cell">
                                      @if($caseSnippet !== '')
                                          <span class="matter-tab-case-preview" title="{{ e($caseSnippet) }}">{{ \Illuminate\Support\Str::limit($caseSnippet, 100) }}</span>
                                      @else
                                          <span class="text-muted">—</span>
                                      @endif
                                  </td>
                                  <td class="text-nowrap text-end">
                                      <button type="button" class="btn btn-xs btn-primary changeMatterAssignee" data-client-matter-id="{{ $cmatter->id }}" title="Edit matter details">
                                          <i class="fas fa-pen"></i>
                                      </button>
                                      <a href="{{ $detailUrl }}" class="btn btn-xs btn-default" title="View full matter details">
                                          <i class="fas fa-external-link-alt"></i>
                                      </a>
                                  </td>
                              </tr>
                          @endforeach
                      </tbody>
                  </table>
              </div>
              <p class="matter-tab-footer-link text-muted">
                  <a href="{{ route('clients.clientsmatterslist', array_filter(['client_id' => $fetchedData->client_id])) }}"><i class="fas fa-external-link-alt"></i> Full matter list</a>
                  @if($fetchedData->client_id)
                      <span> (filter: {{ $fetchedData->client_id }})</span>
                  @endif
              </p>
          @endif
        </section>
      </section>
    </div>
