<div class="col-12 col-md-12 col-lg-12">
    <div class="form-group">
        <label>Attachment</label>
        <input type="file" name="attach[]" class="form-control" multiple>
    </div>
</div>
<div class="col-12 col-md-12 col-lg-12">
    <div class="form-group">
        <label>Standard checklist templates</label>
        <small class="text-muted d-block mb-1">Admin-uploaded PDF packs for this matter type.</small>
        <div class="table-responsive uploadchecklists">
            <table id="mychecklist-datatable" class="table text_wrap table-2">
                <thead>
                    <tr>
                        <th></th>
                        <th>File Name</th>
                        <th>File</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $__matterChecklistRows = \Illuminate\Support\Facades\Schema::hasTable('matter_checklists')
                            ? \App\Models\UploadChecklist::orderBy('id')->get()
                            : collect();
                    @endphp
                    @foreach($__matterChecklistRows as $uclist)
                    <tr data-matter-id="{{ $uclist->matter_id ?? '' }}" data-checklist-id="{{ $uclist->id }}">
                        <td><input type="checkbox" name="checklistfile[]" value="{{ $uclist->id }}" class="checklistfile-cb"></td>
                        <td>{{ $uclist->name }}</td>
                        <td><a target="_blank" href="{{ URL::to('/checklists/'.$uclist->file) }}">{{ $uclist->name }}</a></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
<div class="col-12 col-md-12 col-lg-12" id="compose-matter-documents-section" style="display: none;">
    <div class="form-group">
        <label>Matter documents</label>
        <small class="text-muted d-block mb-1">Files already uploaded for this record (Matter documents tab).</small>
        <div class="table-responsive">
            <table id="my-matter-documents-datatable" class="table text_wrap table-2">
                <thead>
                    <tr>
                        <th></th>
                        <th>Checklist</th>
                        <th>File</th>
                    </tr>
                </thead>
                <tbody id="compose-matter-documents-tbody"></tbody>
            </table>
        </div>
    </div>
</div>
