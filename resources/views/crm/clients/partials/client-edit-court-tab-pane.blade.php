    {{-- ====== TAB 4: Court Dates & Hearings ====== --}}
    <div id="menu4" class="tab-pane fade">
      <h3><i class="fas fa-gavel"></i> Court Dates &amp; Hearings</h3>
      <p class="text-muted">Track important court appearances, hearings, and deadlines. All entries are optional.</p>

      {{-- Add Hearing Form --}}
      <section class="content-section">
        <section class="form-section">
          <div class="section-header">
            <h3><i class="fas fa-plus-circle"></i> Add Court Hearing / Date</h3>
            <span class="badge" style="background:#e8f5e9;color:#2e7d32;font-size:0.8em;padding:4px 10px;border-radius:12px;">Optional</span>
          </div>
          <div id="hearingFormMsg" style="margin-bottom:8px;"></div>
          <div class="row">
            <div class="col-md-4">
              <div class="form-group">
                <label>Hearing Date <span class="text-danger">*</span></label>
                <input type="date" class="form-control" id="hearing_date">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Hearing Time <small class="text-muted">(optional)</small></label>
                <input type="time" class="form-control" id="hearing_time">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Hearing Type <small class="text-muted">(optional)</small></label>
                <select class="form-control" id="hearing_type" style="height:40px;font-size:0.94em;">
                  <option value="">— Select Hearing Type —</option>
                  <option value="First Hearing">First Hearing</option>
                  <option value="Evidence Hearing">Evidence Hearing</option>
                  <option value="Arguments">Arguments</option>
                  <option value="Judgment">Judgment</option>
                  <option value="Bail Hearing">Bail Hearing</option>
                  <option value="Stay Application">Stay Application</option>
                  <option value="Case Management">Case Management</option>
                  <option value="Mediation">Mediation</option>
                  <option value="Mention">Mention</option>
                  <option value="Other">Other</option>
                </select>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Court Name <small class="text-muted">(optional)</small></label>
                <input type="text" class="form-control" id="hearing_court_name" maxlength="255" placeholder="e.g. Delhi High Court">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Case Number <small class="text-muted">(optional)</small></label>
                <input type="text" class="form-control" id="hearing_case_number" maxlength="100" placeholder="e.g. CS/1234/2024">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Judge / Bench <small class="text-muted">(optional)</small></label>
                <input type="text" class="form-control" id="hearing_judge_name" maxlength="150" placeholder="e.g. Hon. Justice Sharma">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Linked Matter <small class="text-muted">(optional)</small></label>
                <select class="form-control dyn-select" id="hearing_matter_id">
                  <option value="">— Not linked to a specific matter —</option>
                  @foreach($clientMatters ?? collect() as $cm)
                    <option value="{{ $cm->id }}">
                      {{ $cm->client_unique_matter_no ?? 'Matter #'.$cm->id }}
                      @if($cm->matter) — {{ $cm->matter->title }} @endif
                    </option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Status</label>
                <select class="form-control dyn-select" id="hearing_status">
                  <option value="Scheduled">Scheduled</option>
                  <option value="Completed">Completed</option>
                  <option value="Adjourned">Adjourned</option>
                  <option value="Cancelled">Cancelled</option>
                </select>
              </div>
            </div>
            <div class="col-md-12">
              <div class="form-group">
                <label>Notes / Instructions <small class="text-muted">(optional)</small></label>
                <textarea class="form-control" id="hearing_notes" rows="3" maxlength="5000" placeholder="Any important notes, instructions, or context for this hearing..."></textarea>
              </div>
            </div>
          </div>
          <button type="button" class="btn btn-primary" onclick="submitHearing()">
            <i class="fas fa-calendar-plus"></i> Add Hearing
          </button>
        </section>
      </section>

      {{-- Existing Hearings List --}}
      <section class="content-section" style="margin-top:1.5rem;">
        <section class="form-section">
          <div class="section-header">
            <h3><i class="fas fa-calendar-alt"></i> Scheduled &amp; Past Hearings</h3>
          </div>
          <div id="hearingsListContainer">
            @if(($courtHearings ?? collect())->isEmpty())
              <div class="matter-tab-empty" style="padding:2rem;">
                <div class="matter-tab-empty__icon"><i class="fas fa-calendar-times" style="font-size:2.5rem;color:#adb5bd;"></i></div>
                <p class="matter-tab-empty__title">No hearings recorded</p>
                <p class="matter-tab-empty__hint text-muted">Add the first court date using the form above.</p>
              </div>
            @else
              <div class="table-responsive">
                <table class="table table-hover" id="hearingsTable">
                  <thead>
                    <tr>
                      <th>Date</th>
                      <th>Time</th>
                      <th>Type</th>
                      <th>Court</th>
                      <th>Case No.</th>
                      <th>Judge</th>
                      <th>Status</th>
                      <th>Linked Matter</th>
                      <th>Notes</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody id="hearingsTableBody">
                    @foreach($courtHearings as $hearing)
                      <tr id="hearing-row-{{ $hearing->id }}" data-hearing-id="{{ $hearing->id }}">
                        <td><strong>{{ $hearing->hearing_date->format('d/m/Y') }}</strong></td>
                        <td>{{ $hearing->hearing_time ? \Carbon\Carbon::parse($hearing->hearing_time)->format('g:i A') : '—' }}</td>
                        <td>{{ $hearing->hearing_type ?: '—' }}</td>
                        <td>{{ $hearing->court_name ?: '—' }}</td>
                        <td>{{ $hearing->case_number ?: '—' }}</td>
                        <td>{{ $hearing->judge_name ?: '—' }}</td>
                        <td>
                          @php
                            $statusColors = ['Scheduled'=>'#1a73e8','Completed'=>'#188038','Adjourned'=>'#e37400','Cancelled'=>'#c5221f'];
                            $sc = $statusColors[$hearing->status] ?? '#555';
                          @endphp
                          <span style="color:{{ $sc }};font-weight:600;">{{ $hearing->status }}</span>
                        </td>
                        <td>
                          @if($hearing->client_matter_id && $hearing->matter)
                            {{ $hearing->matter->client_unique_matter_no ?? '—' }}
                          @else
                            <span class="text-muted">—</span>
                          @endif
                        </td>
                        <td style="max-width:180px;">
                          @if($hearing->notes)
                            <span title="{{ e($hearing->notes) }}">{{ \Illuminate\Support\Str::limit($hearing->notes, 60) }}</span>
                          @else
                            <span class="text-muted">—</span>
                          @endif
                        </td>
                        <td>
                          <button type="button" class="btn btn-xs btn-danger" onclick="deleteHearing({{ $hearing->id }})" title="Delete">
                            <i class="fas fa-trash"></i>
                          </button>
                        </td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            @endif
          </div>
        </section>
      </section>
    </div>
