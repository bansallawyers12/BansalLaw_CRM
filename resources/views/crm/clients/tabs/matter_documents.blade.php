           <!-- Matter Documents tab (matter-specific; internal id matterdocuments-tab) -->
           <div class="tab-pane client-documents-tab{{ strtolower((string) ($activeTab ?? '')) === 'matterdocuments' ? ' active' : '' }}" id="matterdocuments-tab">
                <div class="card full-width documentalls-container">
                    <?php
                    $client_selected_matter_id1 = null;
                    $matter_cnt = \App\Models\ClientMatter::select('id')->where('client_id',$fetchedData->id)->where('matter_status',1)->count();
                    if( $matter_cnt >0 ) {
                        //if client unique reference id is present in url
                        if( isset($id1) && $id1 != "") {
                            // Only resolve by ref if it matches an active matter; a discontinued
                            // matter ref here would make all active-matter documents invisible.
                            $matter_get_id = \App\Models\ClientMatter::select('id')
                                ->where('client_id',$fetchedData->id)
                                ->where('client_unique_matter_no',$id1)
                                ->where('matter_status', 1)
                                ->first();
                            // Fall back to latest active matter if the ref belongs to a discontinued one
                            if (!$matter_get_id) {
                                $matter_get_id = \App\Models\ClientMatter::select('id')
                                    ->where('client_id', $fetchedData->id)
                                    ->where('matter_status', 1)
                                    ->orderBy('id', 'desc')
                                    ->first();
                            }
                        } else {
                            $matter_get_id = \App\Models\ClientMatter::select('id')
                                ->where('client_id', $fetchedData->id)
                                ->where('matter_status', 1)
                                ->orderBy('id', 'desc')
                                ->first();
                        }
                        if($matter_get_id ){
                            $client_selected_matter_id1 = $matter_get_id->id;
                        }
                    }

                    /*$visaDocCatList = \App\Models\VisaDocumentType::select('id', 'title','client_id','client_matter_id')
                    ->where('status', 1)
                    ->where(function($query) use ($client_selected_matter_id1) {
                        $query->whereNull('client_matter_id')
                            ->orWhere('client_matter_id', (int) $client_selected_matter_id1);
                    })
                    ->orderBy('id', 'ASC')
                    ->get();*/


                    $SelectedClientId = $fetchedData->id;
                    $visaDocCatList = \Illuminate\Support\Facades\Schema::hasTable('visa_document_types')
                        ? \App\Models\VisaDocumentType::select('id', 'title', 'client_id', 'client_matter_id')
                            ->where('status', 1)
                            ->where(function ($query) use ($SelectedClientId, $client_selected_matter_id1) {
                                $query->where(function ($q) {
                                    $q->whereNull('client_id')
                                        ->whereNull('client_matter_id');
                                })
                                    ->orWhere(function ($q) use ($SelectedClientId) {
                                        $q->where('client_id', $SelectedClientId)
                                            ->whereNull('client_matter_id');
                                    })
                                    ->orWhere(function ($q) use ($SelectedClientId, $client_selected_matter_id1) {
                                        $q->where('client_id', $SelectedClientId)
                                            ->where('client_matter_id', $client_selected_matter_id1);
                                    });
                            })
                            ->orderByRaw("
                            CASE
                                WHEN (client_id IS NULL AND client_matter_id IS NULL) THEN 1
                                WHEN (client_id = ? AND client_matter_id = ?) THEN 2
                                WHEN (client_id = ? AND client_matter_id IS NULL) THEN 3
                                ELSE 4
                            END, id ASC
                        ", [$SelectedClientId, $client_selected_matter_id1, $SelectedClientId])
                            ->get()
                        : collect();

                    $documentsTableReady = \Illuminate\Support\Facades\Schema::hasTable('documents')
                        && \Illuminate\Support\Facades\Schema::hasColumn('documents', 'client_id')
                        && \Illuminate\Support\Facades\Schema::hasColumn('documents', 'not_used_doc')
                        && \Illuminate\Support\Facades\Schema::hasColumn('documents', 'folder_name')
                        && \Illuminate\Support\Facades\Schema::hasColumn('documents', 'doc_type')
                        && \Illuminate\Support\Facades\Schema::hasColumn('documents', 'type')
                        && \Illuminate\Support\Facades\Schema::hasColumn('documents', 'checklist')
                        && \Illuminate\Support\Facades\Schema::hasColumn('documents', 'client_matter_id');

                    ?>

                    @if (! \Illuminate\Support\Facades\Schema::hasTable('visa_document_types'))
                        <div class="alert alert-warning" style="margin: 15px;">
                            Matter document categories are unavailable: the <code>visa_document_types</code> table is missing.
                            Run <code>php artisan migrate</code> on the server, then reload this page.
                        </div>
                    @endif

                    <!-- Matter Documents content -->
                    <div class="visa-documents-content" id="visa-documents-content">
                        <!-- Matter document type subtabs container -->
                        <div class="subtab-header-container md-folder-bar">
                            <nav class="subtabs6 md-folder-tabs">
                                <?php foreach ($visaDocCatList as $catIdx => $catVal): ?>
                                    <?php
                                    $id = $catVal->id;
                                    $isActive = ($catIdx === 0) ? 'active' : '';
                                    $folderName = $id;
                                    $isClientGenerated = $catVal->client_matter_id !== null;
                                    ?>
                                    <div class="button-container md-folder-tab-wrap">
                                        <button type="button" class="subtab6-button <?= $isActive ?>" data-subtab6="<?= $id ?>" title="<?= \App\Support\DocumentLabel::forDisplay($catVal->title) ?>">
                                            <?= \App\Support\DocumentLabel::forDisplay($catVal->title !== null && $catVal->title !== '' ? $catVal->title : 'Untitled folder') ?>
                                        </button>
                                        <?php if ($isClientGenerated): ?>
                                            <div class="action-buttons md-folder-tab-actions">
                                                <button type="button" class="btn btn-sm btn-warning update-visa-cat-title" data-id="<?= $id ?>" data-title="<?= e(\App\Support\DocumentLabel::forDisplay($catVal->title)) ?>" title="Rename folder"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i></button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </nav>
                            <div class="md-header-actions">
                                <button type="button" class="btn md-btn md-btn-light add-visa-doc-category-btn add-visa-doc-category" data-type="visa" data-categoryid="">
                                    <i class="fa-solid fa-plus"></i> Add Folder
                                </button>
                                <button type="button" class="btn md-btn md-btn-ghost md-not-used-btn client-nav-button" data-tab="notuseddocuments">
                                    <i class="fa-solid fa-folder-minus"></i> Not Used Documents
                                </button>
                            </div>
                        </div>

                        <!-- Subtab6 Contents -->
                        <div class="subtab6-content">
                            <?php foreach ($visaDocCatList as $catIdx => $catVal):
                                $id = $catVal->id;
                                $isActive = ($catIdx === 0) ? 'active' : '';
                                $folderName = $id;
                                $matterPreviewContainerId = 'preview-container-matter-' . $id;
                                ?>
                                <div class="subtab6-pane <?= $isActive ?>" id="<?= $id ?>-subtab6">
                                    <div class="checklist-table-container">
                                        <div class="subtab6-header md-section-header">
                                            <h3><i class="fa-solid fa-file-lines"></i> <?= \App\Support\DocumentLabel::forDisplay($catVal->title) ?> Documents</h3>
                                            <div class="md-section-actions">
                                                <button type="button" class="btn md-btn md-btn-primary add-checklist-btn add_migration_doc" data-type="visa" data-categoryid="<?= $id ?>">
                                                    <i class="fa-solid fa-plus"></i> Add Checklist
                                                </button>
                                                <button type="button" class="btn md-btn md-btn-outline bulk-upload-toggle-btn-visa" data-categoryid="<?= $id ?>" data-categoryname="<?= \App\Support\DocumentLabel::forDisplay($catVal->title) ?>" data-matterid="<?= $client_selected_matter_id1 ?? '' ?>">
                                                    <i class="fa-solid fa-upload"></i> Bulk Upload
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <!-- Bulk upload dropzone for matter documents (hidden by default) -->
                                        <div class="bulk-upload-dropzone-container-visa matter-bulk-dropzone" id="bulk-upload-visa-<?= $id ?>" style="display: none;">
                                            <div class="bulk-upload-dropzone-visa" data-categoryid="<?= $id ?>" data-matterid="<?= $client_selected_matter_id1 ?? '' ?>" style="text-align: center; padding: 30px; cursor: pointer;">
                                                <i class="fa-solid fa-cloud-arrow-up matter-bulk-dropzone-icon"></i>
                                                <p class="matter-bulk-dropzone-lead">
                                                    <strong>Drag and drop files here</strong> or <strong>click to browse</strong>
                                                </p>
                                                <p class="matter-bulk-dropzone-hint">PDF, images, Word, Excel (XLS/XLSX/CSV), audio (MP3), videos (MP4, WebM, MOV, VOB, etc.), and MS Teams recordings — up to {{ (int) config('crm.document_upload.max_file_size_mb', 100) }}MB ({{ (int) config('crm.personal_video_upload.max_size_mb', 300) }}MB for videos). You can select multiple files.</p>
                                                <input type="file" class="bulk-upload-file-input-visa" data-categoryid="<?= $id ?>" data-matterid="<?= $client_selected_matter_id1 ?? '' ?>" multiple style="display: none;" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.csv,.mp3,.mp4,.webm,.mov,.m4v,.avi,.mkv,.vob,audio/mpeg,audio/mp3,video/mp4,video/webm,video/quicktime,video/mpeg,video/*">
                                            </div>
                                            <div class="bulk-upload-file-list-visa" style="display: none; margin-top: 20px;">
                                                <h5 style="margin-bottom: 15px;">Files Selected: <span class="file-count-visa">0</span></h5>
                                                <div class="bulk-upload-files-container-visa"></div>
                                            </div>
                                        </div>
                                        <div class="checklist-table-scroll">
                                        <table class="checklist-table">
                                            <thead>
                                                <tr>
                                                    <th>Checklist</th>
                                                    <th>File Name</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody class="tdata migdocumnetlist1 migdocumnetlist_<?= $id ?>">
                                                <?php
                                                $documents = collect();
                                                if ($documentsTableReady) {
                                                    $documents = \App\Models\Document::with('signers')->where('client_id', $fetchedData->id)
                                                        ->whereNull('not_used_doc')
                                                        ->whereIn('doc_type', ['matter', 'visa'])
                                                        ->where('folder_name', $folderName)
                                                        ->where('type', 'client')
                                                        ->orderBy('created_at', 'DESC')
                                                        ->get();
                                                }
                                                $parentDocs = $documents->filter(fn ($d) => ! str_ends_with($d->checklist ?? '', '_signed'));
                                                $signedByParent = $documents->filter(fn ($d) => str_ends_with($d->checklist ?? '', '_signed'))
                                                    ->groupBy(fn ($d) => ($d->folder_name ?? '') . '|' . ($d->client_matter_id ?? '') . '|' . substr($d->checklist ?? '', 0, -7));
                                                $parentKeysWithActiveParent = $parentDocs->map(fn ($d) => ($d->folder_name ?? '') . '|' . ($d->client_matter_id ?? '') . '|' . ($d->checklist ?? ''))->unique()->values();
                                                $orphanSignedKeys = $signedByParent->keys()->filter(fn ($k) => ! $parentKeysWithActiveParent->contains($k))->sortBy(fn ($k) => $signedByParent->get($k)->min('created_at'));
                                                ?>
                                                <?php foreach ($parentDocs as $visaKey => $fetch): ?>
                                                    <?php
                                                    $admin = \App\Models\Staff::where('id', $fetch->user_id)->first();
                                                    $previewUrl = url('/documents/preview/' . $fetch->id);
                                                    $downloadFilename = $fetch->myfile_key ?: trim(($fetch->file_name ?? '') . '.' . ($fetch->filetype ?? ''), '.');
                                                    ?>
                                                    <tr class="drow" data-matterid="<?= $fetch->client_matter_id ?>" data-catid="<?= $fetch->folder_name ?>" id="id_<?= $fetch->id ?>">
                                                        <td style="white-space: initial;">
                                                            <div data-id="<?= $fetch->id ?>" data-visachecklistname="<?= \App\Support\DocumentLabel::forDisplay($fetch->checklist) ?>" class="visachecklist-row md-checklist-row" title="Uploaded by: <?= \App\Support\DocumentLabel::forDisplay($admin->first_name ?? 'NA') ?> on <?= date('d/m/Y H:i', strtotime($fetch->created_at)) ?>">
                                                                <span class="md-checklist-label"><?= \App\Support\DocumentLabel::forDisplay($fetch->checklist) ?></span>
                                                                <div class="checklist-actions">
                                                                    <?php if (!$fetch->file_name): ?>
                                                                    <a href="javascript:;" class="edit-checklist-btn" data-id="<?= $fetch->id ?>" data-checklist="<?= \App\Support\DocumentLabel::forDisplay($fetch->checklist) ?>" title="Edit Checklist Name">
                                                                        <i class="fa-solid fa-pen-to-square"></i>
                                                                    </a>
                                                                    <a href="javascript:;" class="delete-checklist-btn" data-id="<?= $fetch->id ?>" data-checklist="<?= \App\Support\DocumentLabel::forDisplay($fetch->checklist) ?>" title="Delete Checklist">
                                                                        <i class="fa-solid fa-trash"></i>
                                                                    </a>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td style="white-space: initial;">
                                                            <?php if ($fetch->file_name): ?>
                                                                <?php
                                                                $displayFileName = $fetch->file_name . '.' . ($fetch->filetype ?? '');
                                                                $matterFileExt = strtolower((string) ($fetch->filetype ?? ''));
                                                                if (in_array($matterFileExt, ['xls', 'xlsx', 'csv', 'ods'], true)) {
                                                                    $matterFileIcon = 'fa-file-excel';
                                                                } elseif (in_array($matterFileExt, ['doc', 'docx', 'rtf', 'odt'], true)) {
                                                                    $matterFileIcon = 'fa-file-word';
                                                                } elseif ($matterFileExt === 'pdf') {
                                                                    $matterFileIcon = 'fa-file-pdf';
                                                                } elseif (in_array($matterFileExt, ['mp4', 'webm', 'mov', 'm4v', 'avi', 'mkv', 'vob'], true)) {
                                                                    $matterFileIcon = 'fa-file-video';
                                                                } else {
                                                                    $matterFileIcon = 'fa-file-image';
                                                                }
                                                                ?>
                                                                <div data-id="<?= $fetch->id ?>" data-name="<?= \App\Support\DocumentLabel::forDisplay($fetch->file_name) ?>" data-uploaded-at="<?= date('d/m/Y H:i', strtotime($fetch->created_at)) ?>" class="doc-row" title="Uploaded by: <?= \App\Support\DocumentLabel::forDisplay($admin->first_name ?? 'NA') ?> on <?= date('d/m/Y H:i', strtotime($fetch->created_at)) ?>" oncontextmenu='showVisaFileContextMenu(event, <?= (int) $fetch->id ?>, <?= json_encode($fetch->filetype ?? 'pdf') ?>, <?= json_encode($previewUrl) ?>, <?= json_encode((string) $id) ?>, <?= json_encode($fetch->status ?? 'draft') ?>); return false;'>
                                                                    <a href="javascript:void(0);" onclick='previewFile(<?= json_encode($fetch->filetype ?? 'pdf') ?>, <?= json_encode($previewUrl) ?>, <?= json_encode($matterPreviewContainerId) ?>)'>
                                                                        <i class="fa-solid <?= $matterFileIcon ?> matter-doc-file-icon"></i> <span><?= \App\Support\DocumentLabel::forDisplay($displayFileName) ?></span>
                                                                    </a>
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="migration_upload_document" style="display: inline-block;">
                                                                    <form method="POST" enctype="multipart/form-data" id="mig_upload_form_<?= $fetch->id ?>">
                                                                        @csrf
                                                                        <input type="hidden" name="clientid" value="<?= $fetchedData->id ?>">
                                                                        <input type="hidden" name="client_matter_id" value="<?= $fetch->client_matter_id ?? '' ?>">
                                                                        <input type="hidden" name="fileid" value="<?= $fetch->id ?>">
                                                                        <input type="hidden" name="type" value="client">
                                                                        <input type="hidden" name="doctype" value="matter">
                                                                        <input type="hidden" name="doccategory" value="<?= $catVal->title ?>">
                                                                        <div class="document-drag-drop-zone visa-doc-drag-zone" data-fileid="<?= $fetch->id ?>" data-doccategory="<?= $id ?>" data-formid="mig_upload_form_<?= $fetch->id ?>">
                                                                            <div class="drag-zone-inner">
                                                                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                                                                <span class="drag-zone-text">Drag file here or <strong>click to browse</strong></span>
                                                                            </div>
                                                                        </div>
                                                                        <input class="migdocupload d-none" data-fileid="<?= $fetch->id ?>" data-doccategory="<?= $id ?>" type="file" name="document_upload" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.csv,.mp3,.mp4,.webm,.mov,.m4v,.avi,.mkv,.vob,audio/mpeg,audio/mp3,video/mp4,video/webm,video/quicktime,video/mpeg,video/*" style="display: none;"/>
                                                                    </form>
                                                                </div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($fetch->myfile): ?>
                                                                <a class="renamechecklist" data-id="<?= $fetch->id ?>" href="javascript:;" style="display: none;"></a>
                                                                <a class="renamedoc" data-id="<?= $fetch->id ?>" href="javascript:;" style="display: none;"></a>
                                                                <a class="download-file" data-id="<?= $fetch->id ?>" data-document-id="<?= $fetch->id ?>" data-filename="<?= e($downloadFilename) ?>" href="#" style="display: none;"></a>
                                                                <a class="notuseddoc" data-id="<?= $fetch->id ?>" data-doctype="matter" data-href="documents/not-used" href="javascript:;" style="display: none;"></a>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <?php
                                                    $docStatus = $fetch->status ?? '';
                                                    $showSigActionBar = in_array($docStatus, ['placed', 'sent']) && in_array($fetch->doc_type, ['matter', 'visa'], true) && $fetch->file_name && ($fetch->filetype ?? '') === 'pdf';
                                                    if ($showSigActionBar):
                                                        $signingUrl = null;
                                                        if ($fetch->signature_doc_link) {
                                                            $links = json_decode($fetch->signature_doc_link, true);
                                                            $signingUrl = is_array($links) && isset($links[0]['url']) ? $links[0]['url'] : null;
                                                        }
                                                        $pendingSigner = $fetch->signers()->whereIn('status', ['pending'])->first();
                                                        $signerId = $pendingSigner ? $pendingSigner->id : null;
                                                    ?>
                                                    <tr class="visa-sig-action-bar" data-doc-id="<?= $fetch->id ?>" data-signer-id="<?= $signerId ?>">
                                                        <td colspan="3" style="padding: 10px 16px;">
                                                            <div class="d-flex flex-wrap align-items-center gap-2" style="flex-wrap: wrap;">
                                                                <button type="button" class="btn btn-sm btn-primary visa-sig-send-btn" data-doc-id="<?= $fetch->id ?>" <?= $docStatus === 'sent' ? 'disabled' : '' ?>>
                                                                    <i class="fa-solid fa-paper-plane me-1"></i> Send
                                                                </button>
                                                                <button type="button" class="btn btn-sm btn-outline-secondary visa-sig-revise-btn" data-doc-id="<?= $fetch->id ?>">
                                                                    <i class="fa-solid fa-pen-to-square me-1"></i> Revise
                                                                </button>
                                                                <button type="button" class="btn btn-sm btn-outline-danger visa-sig-remove-btn" data-doc-id="<?= $fetch->id ?>">
                                                                    <i class="fa-solid fa-xmark me-1"></i> Remove
                                                                </button>
                                                                <?php if ($docStatus === 'sent' && $signingUrl && $signerId): ?>
                                                                <button type="button" class="btn btn-sm btn-outline-info visa-sig-reminder-btn" data-doc-id="<?= $fetch->id ?>" data-signer-id="<?= $signerId ?>">
                                                                    <i class="fa-solid fa-bell me-1"></i> Reminder
                                                                </button>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php endif; ?>
                                                    <?php
                                                    $signedKey = ($fetch->folder_name ?? '') . '|' . ($fetch->client_matter_id ?? '') . '|' . ($fetch->checklist ?? '');
                                                    $signedDocs = $signedByParent->get($signedKey, collect());
                                                    foreach ($signedDocs as $signedDoc):
                                                        $signedAdmin = \App\Models\Staff::where('id', $signedDoc->user_id)->first();
                                                        $signedFileUrl = url()->route('documents.preview.signed', $signedDoc->id);
                                                        $signedDownloadUrl = $signedDoc->signed_doc_link ?? $signedDoc->myfile;
                                                        $signedDisplayName = ($signedDoc->file_name ?? 'signed') . '.' . ($signedDoc->filetype ?? 'pdf');
                                                    ?>
                                                    <tr class="drow visa-signed-row" data-matterid="<?= $signedDoc->client_matter_id ?>" data-catid="<?= $signedDoc->folder_name ?>" id="id_<?= $signedDoc->id ?>">
                                                        <td style="white-space: initial;">
                                                            <div data-id="<?= $signedDoc->id ?>" class="visachecklist-row" style="display: flex; align-items: center; gap: 8px;">
                                                                <span style="flex: 1;"><?= \App\Support\DocumentLabel::forDisplay($signedDoc->checklist) ?></span>
                                                            </div>
                                                        </td>
                                                        <td style="white-space: initial;">
                                                            <?php
                                                            $signedFileUrlJs = addslashes($signedFileUrl);
                                                            ?>
                                                            <div data-id="<?= $signedDoc->id ?>" data-name="<?= \App\Support\DocumentLabel::forDisplay($signedDoc->file_name ?? '') ?>" data-uploaded-at="<?= !empty($signedDoc->created_at) ? date('d/m/Y H:i', strtotime($signedDoc->created_at)) : '' ?>" class="doc-row" title="Signed document<?= !empty($signedDoc->created_at) ? ' on ' . date('d/m/Y H:i', strtotime($signedDoc->created_at)) : '' ?>" oncontextmenu="showVisaFileContextMenu(event, <?= $signedDoc->id ?>, '<?= \App\Support\DocumentLabel::forDisplay($signedDoc->filetype ?? 'pdf') ?>', '<?= $signedFileUrlJs ?>', '<?= $id ?>', '<?= $signedDoc->status ?? 'signed' ?>'); return false;">
                                                                <a href="javascript:void(0);" onclick="previewFile('<?= $signedDoc->filetype ?? 'pdf' ?>','<?= $signedFileUrlJs ?>','<?= $matterPreviewContainerId ?>')">
                                                                    <i class="fa-solid fa-file-image"></i> <span><?= \App\Support\DocumentLabel::forDisplay($signedDisplayName) ?></span>
                                                                </a>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <a class="renamechecklist" data-id="<?= $signedDoc->id ?>" href="javascript:;" style="display: none;"></a>
                                                            <a class="renamedoc" data-id="<?= $signedDoc->id ?>" href="javascript:;" style="display: none;"></a>
                                                            <a class="download-file" data-document-id="<?= $signedDoc->id ?>" data-id="<?= $signedDoc->id ?>" data-filename="<?= e($signedDoc->getSignedDownloadFilename()) ?>" href="#" style="display: none;"></a>
                                                            <a class="notuseddoc" data-id="<?= $signedDoc->id ?>" data-doctype="matter" data-href="documents/not-used" href="javascript:;" style="display: none;"></a>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                <?php endforeach; ?>
                                                <?php
                                                // Orphan signed docs: parent moved to not used or deleted — show signed row(s) so signed version still displays
                                                foreach ($orphanSignedKeys as $orphanKey):
                                                    $signedDocs = $signedByParent->get($orphanKey, collect());
                                                    foreach ($signedDocs as $signedDoc):
                                                        $signedAdmin = \App\Models\Staff::where('id', $signedDoc->user_id)->first();
                                                        $signedFileUrl = url()->route('documents.preview.signed', $signedDoc->id);
                                                        $signedDownloadUrl = $signedDoc->signed_doc_link ?? $signedDoc->myfile;
                                                        $signedDisplayName = ($signedDoc->file_name ?? 'signed') . '.' . ($signedDoc->filetype ?? 'pdf');
                                                        $signedFileUrlJs = addslashes($signedFileUrl);
                                                ?>
                                                    <tr class="drow visa-signed-row" data-matterid="<?= $signedDoc->client_matter_id ?>" data-catid="<?= $signedDoc->folder_name ?>" id="id_<?= $signedDoc->id ?>">
                                                        <td style="white-space: initial;">
                                                            <div data-id="<?= $signedDoc->id ?>" class="visachecklist-row" style="display: flex; align-items: center; gap: 8px;">
                                                                <span style="flex: 1;"><?= \App\Support\DocumentLabel::forDisplay($signedDoc->checklist) ?></span>
                                                            </div>
                                                        </td>
                                                        <td style="white-space: initial;">
                                                            <div data-id="<?= $signedDoc->id ?>" data-name="<?= \App\Support\DocumentLabel::forDisplay($signedDoc->file_name ?? '') ?>" data-uploaded-at="<?= !empty($signedDoc->created_at) ? date('d/m/Y H:i', strtotime($signedDoc->created_at)) : '' ?>" class="doc-row" title="Signed document<?= !empty($signedDoc->created_at) ? ' on ' . date('d/m/Y H:i', strtotime($signedDoc->created_at)) : '' ?>" oncontextmenu="showVisaFileContextMenu(event, <?= $signedDoc->id ?>, '<?= \App\Support\DocumentLabel::forDisplay($signedDoc->filetype ?? 'pdf') ?>', '<?= $signedFileUrlJs ?>', '<?= $id ?>', '<?= $signedDoc->status ?? 'signed' ?>'); return false;">
                                                                <a href="javascript:void(0);" onclick="previewFile('<?= $signedDoc->filetype ?? 'pdf' ?>','<?= $signedFileUrlJs ?>','<?= $matterPreviewContainerId ?>')">
                                                                    <i class="fa-solid fa-file-image"></i> <span><?= \App\Support\DocumentLabel::forDisplay($signedDisplayName) ?></span>
                                                                </a>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <a class="renamechecklist" data-id="<?= $signedDoc->id ?>" href="javascript:;" style="display: none;"></a>
                                                            <a class="renamedoc" data-id="<?= $signedDoc->id ?>" href="javascript:;" style="display: none;"></a>
                                                            <a class="download-file" data-document-id="<?= $signedDoc->id ?>" data-id="<?= $signedDoc->id ?>" data-filename="<?= e($signedDoc->getSignedDownloadFilename()) ?>" href="#" style="display: none;"></a>
                                                            <a class="notuseddoc" data-id="<?= $signedDoc->id ?>" data-doctype="matter" data-href="documents/not-used" href="javascript:;" style="display: none;"></a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; endforeach; ?>
                                            </tbody>
                                        </table>
                                        </div>
                                    </div>

                                    <div class="grid_data miggriddata" style="display:none;">
                                        <?php foreach ($visaDocCatList as $catVal):
                                            $id = $catVal->id;
                                            $documents = collect();
                                            if ($documentsTableReady) {
                                                $documents = \App\Models\Document::where('client_id', $fetchedData->id)
                                                    ->whereNull('not_used_doc')
                                                    ->whereIn('doc_type', ['matter', 'visa'])
                                                    ->where('folder_name', $id)
                                                    ->where('type', 'client')
                                                    ->orderBy('updated_at', 'DESC')
                                                    ->get();
                                            }
                                            foreach ($documents as $fetch):
                                                if ($fetch->myfile):
                                                    $admin = \App\Models\Staff::where('id', $fetch->user_id)->first();
                                                    $gridPreviewUrl = url('/documents/preview/' . $fetch->id);
                                                    $gridDownloadFilename = $fetch->myfile_key ?: trim(($fetch->file_name ?? '') . '.' . ($fetch->filetype ?? ''), '.');
                                                    $gridExt = strtolower((string) ($fetch->filetype ?? ''));
                                                    if (in_array($gridExt, ['xls', 'xlsx', 'csv', 'ods'], true)) {
                                                        $gridFileIcon = 'fa-file-excel';
                                                    } elseif (in_array($gridExt, ['doc', 'docx', 'rtf', 'odt'], true)) {
                                                        $gridFileIcon = 'fa-file-word';
                                                    } elseif ($gridExt === 'pdf') {
                                                        $gridFileIcon = 'fa-file-pdf';
                                                    } else {
                                                        $gridFileIcon = 'fa-file-image';
                                                    }
                                                    ?>
                                                    <div class="grid_list" id="gid_<?= $fetch->id ?>">
                                                        <div class="grid_col">
                                                            <div class="grid_icon">
                                                                <i class="fa-solid <?= $gridFileIcon ?>"></i>
                                                            </div>
                                                            <div class="grid_content">
                                                                <span id="grid_<?= $fetch->id ?>" class="gridfilename"><?= \App\Support\DocumentLabel::forDisplay($fetch->file_name) ?></span>
                                                                <div class="dropdown d-inline dropdown_ellipsis_icon">
                                                                    <a class="dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="fa-solid fa-ellipsis-vertical"></i></a>
                                                                    <div class="dropdown-menu">
                                                                        <a href="javascript:void(0);" class="dropdown-item" onclick='previewFile(<?= json_encode($fetch->filetype ?? 'pdf') ?>, <?= json_encode($gridPreviewUrl) ?>, <?= json_encode('preview-container-matter-' . $fetch->folder_name) ?>)'>Preview</a>
                                                                        <a href="#" class="dropdown-item download-file" data-id="<?= $fetch->id ?>" data-document-id="<?= $fetch->id ?>" data-filename="<?= e($gridDownloadFilename) ?>">Download</a>
                                                                        <a data-id="<?= $fetch->id ?>" class="dropdown-item notuseddoc" data-doctype="matter" data-href="notuseddoc" href="javascript:;">Not Used</a>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                            <div class="clearfix"></div>
                                        <?php endforeach; ?>
                                    </div>

                                    <div class="preview-pane file-preview-container <?= $matterPreviewContainerId ?> matter-preview-pane client-doc-preview-pane">
                                        <div class="client-doc-preview-empty">
                                            <i class="fa-solid fa-file-lines client-doc-preview-empty-icon" aria-hidden="true"></i>
                                            <p class="preview-placeholder-text"><strong>Document Preview</strong></p>
                                            <p class="preview-placeholder-text">Select a file from the list to preview it here</p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

            <!-- Custom context menu for matter documents (must stay inside .tab-pane for lazy-tab load) -->
            <div id="visaFileContextMenu" class="context-menu matter-docs-context-menu crm-doc-context-menu" style="display: none; position: fixed; z-index: 10050; min-width: 220px;" role="menu" aria-label="Document actions">
                <div id="visa-context-send-signature" class="context-menu-item" role="menuitem" onclick="handleVisaContextAction('send-for-signature')" style="display: none;">
                    <i class="fa-solid fa-pen-fancy" aria-hidden="true"></i><span>Place Signature Fields</span>
                </div>
                <div class="context-menu-item" role="menuitem" onclick="handleVisaContextAction('rename-checklist')">
                    <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i><span>Rename Checklist</span>
                </div>
                <div class="context-menu-item" role="menuitem" onclick="handleVisaContextAction('rename-doc')">
                    <i class="fa-solid fa-file-pen" aria-hidden="true"></i><span>Rename File Name</span>
                </div>
                <div class="context-menu-item" role="menuitem" onclick="handleVisaContextAction('move')">
                    <i class="fa-solid fa-up-down-left-right" aria-hidden="true"></i><span>Move Document</span>
                </div>
                <div class="context-menu-item" role="menuitem" onclick="handleVisaContextAction('preview')">
                    <i class="fa-solid fa-eye" aria-hidden="true"></i><span>Preview</span>
                </div>
                <div id="visa-context-pdf-option" class="context-menu-item" role="menuitem" onclick="handleVisaContextAction('pdf')" style="display: none;">
                    <i class="fa-solid fa-file-pdf" aria-hidden="true"></i><span>PDF</span>
                </div>
                <div class="context-menu-item" role="menuitem" onclick="handleVisaContextAction('download')">
                    <i class="fa-solid fa-download" aria-hidden="true"></i><span>Download</span>
                </div>
                <div class="context-menu-item" role="menuitem" onclick="handleVisaContextAction('not-used')">
                    <i class="fa-solid fa-trash" aria-hidden="true"></i><span>Not Used</span>
                </div>
            </div>

            <!-- Move document modal (shared with personal docs) -->
            <div class="modal fade" id="moveVisaDocumentModal" tabindex="-1" role="dialog" aria-labelledby="moveVisaDocumentModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="moveVisaDocumentModalLabel">Move Document</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="form-group">
                                <label>Move to:</label>
                                <select id="moveVisaTargetType" class="form-control" style="margin-bottom: 15px;">
                                    <option value="">-- Select Destination --</option>
                                    <option value="personal">Personal Documents</option>
                                    <option value="matter">Matter Documents</option>
                                </select>
                            </div>
                            
                            <!-- For Personal Documents: show folders -->
                            <div class="form-group" id="moveVisaPersonalCategoryContainer" style="display: none;">
                                <label>Select Personal Folder:</label>
                                <select id="moveVisaPersonalCategoryId" class="form-control">
                                    <option value="">-- Select Folder --</option>
                                </select>
                            </div>
                            
                            <!-- For matter documents: show folders -->
                            <div class="form-group" id="moveVisaVisaCategoryContainer" style="display: none;">
                                <label>Select matter document folder:</label>
                                <select id="moveVisaVisaCategoryId" class="form-control">
                                    <option value="">-- Select Folder --</option>
                                </select>
                            </div>
                            
                            <div id="moveVisaDocumentError" class="alert alert-danger" style="display: none; margin-top: 10px;"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" id="confirmMoveVisaDocument">Move Document</button>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                let currentVisaContextFile = null;
                let currentVisaContextData = {};

                function showVisaFileContextMenu(event, fileId, fileType, fileUrl, categoryId, fileStatus) {
                    event.preventDefault();
                    event.stopPropagation();
                    
                    currentVisaContextFile = fileId;
                    currentVisaContextData = {
                        fileId: fileId,
                        fileType: fileType,
                        fileUrl: fileUrl,
                        categoryId: categoryId,
                        fileStatus: fileStatus
                    };

                    const menu = document.getElementById('visaFileContextMenu');
                    if (!menu) {
                        return;
                    }
                    // Tab panes use overflow:hidden — mount on body so the menu is not clipped
                    if (menu.parentElement !== document.body) {
                        document.body.appendChild(menu);
                    }
                    
                    // Show/hide PDF option based on file type
                    const pdfOption = document.getElementById('visa-context-pdf-option');
                    const sendSigOption = document.getElementById('visa-context-send-signature');
                    const fileExt = String(fileType || 'pdf').toLowerCase();
                    if (pdfOption) {
                        if (['jpg', 'png', 'jpeg'].includes(fileExt) && !window.__CRM_CLOSED_MATTER_VIEW__) {
                            pdfOption.style.display = 'block';
                        } else {
                            pdfOption.style.display = 'none';
                        }
                    }
                    // For PDFs, either place fields first or send once fields are already placed.
                    if (sendSigOption) {
                        if (fileExt === 'pdf' && fileStatus !== 'signed' && !window.__CRM_CLOSED_MATTER_VIEW__) {
                            sendSigOption.style.display = 'block';
                            if (fileStatus === 'placed') {
                                sendSigOption.innerHTML = '<i class="fa-solid fa-paper-plane" aria-hidden="true"></i><span>Send for Signature</span>';
                            } else if (fileStatus === 'sent') {
                                sendSigOption.innerHTML = '<i class="fa-solid fa-pen-to-square" aria-hidden="true"></i><span>Revise Signature Fields</span>';
                            } else {
                                sendSigOption.innerHTML = '<i class="fa-solid fa-pen-fancy" aria-hidden="true"></i><span>Place Signature Fields</span>';
                            }
                        } else {
                            sendSigOption.style.display = 'none';
                        }
                    }

                    if (typeof window.applyClosedMatterContextMenuFilter === 'function') {
                        window.applyClosedMatterContextMenuFilter(menu);
                    }

                    // Measure after closed-matter filtering so single-item menus position correctly
                    menu.style.visibility = 'hidden';
                    menu.style.display = 'block';
                    const menuWidth = menu.offsetWidth || 220;
                    const menuHeight = menu.offsetHeight || 48;
                    menu.style.display = 'none';
                    menu.style.visibility = 'visible';

                    // Position menu at cursor (position: fixed uses viewport coordinates)
                    const viewportWidth = window.innerWidth;
                    const viewportHeight = window.innerHeight;
                    const offset = 4;
                    
                    let menuLeft = event.clientX + offset;
                    let menuTop = event.clientY + offset;
                    
                    if (menuLeft + menuWidth > viewportWidth - offset) {
                        menuLeft = event.clientX - menuWidth - offset;
                    }
                    
                    if (menuTop + menuHeight > viewportHeight - offset) {
                        menuTop = event.clientY - menuHeight - offset;
                    }
                    
                    menuLeft = Math.max(offset, menuLeft);
                    menuTop = Math.max(offset, menuTop);
                    
                    menu.style.left = menuLeft + 'px';
                    menu.style.top = menuTop + 'px';

                    menu.style.display = 'block';

                    // Hide menu when clicking elsewhere
                    setTimeout(() => {
                        document.addEventListener('click', hideVisaContextMenu);
                    }, 100);
                }

                function hideVisaContextMenu() {
                    const menu = document.getElementById('visaFileContextMenu');
                    if (menu) {
                        menu.style.display = 'none';
                    }
                    document.removeEventListener('click', hideVisaContextMenu);
                }

                function handleVisaContextAction(action) {
                    if (!currentVisaContextFile) return;

                    hideVisaContextMenu();

                    if (window.__CRM_CLOSED_MATTER_VIEW__ && action !== 'preview') {
                        return;
                    }

                    switch(action) {
                        case 'send-for-signature':
                            if (currentVisaContextData.fileStatus === 'placed') {
                                if (typeof $ !== 'undefined') {
                                    $.ajax({
                                        url: '{{ url("/signatures") }}/' + currentVisaContextFile + '/send',
                                        method: 'POST',
                                        data: { _token: '{{ csrf_token() }}' },
                                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                                    }).done(function(resp) {
                                        crmAlert((resp && resp.message) || 'Document sent for signature.');
                                        location.reload();
                                    }).fail(function(xhr) {
                                        var message = 'Failed to send for signature.';
                                        if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                                            message = xhr.responseJSON.message;
                                        }
                                        crmAlert(message);
                                    });
                                }
                            } else if (typeof $ !== 'undefined') {
                                $(document).trigger('openSignaturePlacementModal', { documentId: currentVisaContextFile });
                            }
                            break;
                        case 'rename-checklist':
                            $('.renamechecklist[data-id="' + currentVisaContextFile + '"]').click();
                            break;
                        case 'rename-doc':
                            $('.renamedoc[data-id="' + currentVisaContextFile + '"]').click();
                            break;
                        case 'move':
                            openMoveVisaDocumentModal(currentVisaContextFile, 'matter');
                            break;
                        case 'preview':
                            if (currentVisaContextData.fileUrl) {
                                window.open(currentVisaContextData.fileUrl, '_blank', 'noopener');
                            }
                            break;
                        case 'pdf':
                            const pdfUrl = '{{ URL::to('/document/download/pdf') }}/' + currentVisaContextFile;
                            window.open(pdfUrl, '_blank');
                            break;
                        case 'download':
                            // Prefer finding by document ID so we use the current link (updated after rename); fallback to filelink match
                            let $downloadBtn = $('.download-file[data-id="' + currentVisaContextFile + '"]');
                            if ($downloadBtn.length === 0) {
                                $downloadBtn = $('.download-file[data-filelink="' + currentVisaContextData.fileUrl + '"]');
                            }
                            if ($downloadBtn.length > 0) {
                                $downloadBtn.first().click();
                            } else {
                                console.error('Download button not found for file ID:', currentVisaContextFile);
                                crmAlert('Download link not found. Please refresh the page and try again.');
                            }
                            break;
                        case 'not-used':
                            $('.notuseddoc[data-id="' + currentVisaContextFile + '"]').click();
                            break;
                    }
                }

                // ============================================================================
                // MOVE VISA DOCUMENT FUNCTIONALITY
                // ============================================================================
                let currentMoveVisaDocumentId = null;
                let currentMoveVisaDocumentType = null;

                function appendMatterDocModalToBody(selector) {
                    const $modal = $(selector);
                    if ($modal.length && !$modal.parent().is('body')) {
                        $modal.appendTo('body');
                    }
                }

                appendMatterDocModalToBody('#moveVisaDocumentModal');

                function openMoveVisaDocumentModal(documentId, currentType) {
                    currentMoveVisaDocumentId = documentId;
                    currentMoveVisaDocumentType = currentType;

                    appendMatterDocModalToBody('#moveVisaDocumentModal');
                    
                    // Reset modal
                    $('#moveVisaTargetType').val('');
                    $('#moveVisaPersonalCategoryContainer').hide();
                    $('#moveVisaVisaCategoryContainer').hide();
                    $('#moveVisaPersonalCategoryId').empty().append('<option value="">-- Select Folder --</option>');
                    $('#moveVisaVisaCategoryId').empty().append('<option value="">-- Select Folder --</option>');
                    $('#moveVisaDocumentError').hide();
                    
                    // Show modal
                    $('#moveVisaDocumentModal').modal('show');
                }

                // Handle target type change for visa documents
                $(document).on('change', '#moveVisaTargetType', function() {
                    const targetType = $(this).val();
                    
                    // Hide all containers first
                    $('#moveVisaPersonalCategoryContainer').hide();
                    $('#moveVisaVisaCategoryContainer').hide();
                    $('#moveVisaDocumentError').hide();
                    
                    if (!targetType) {
                        return;
                    }
                    
                    if (targetType === 'personal') {
                        // Load personal document categories from DOM (like personal tab)
                        const categories = [];
                        $('.subtab2-button').each(function() {
                            const catId = $(this).data('subtab2');
                            const catTitle = $(this).text().trim();
                            if (catId && catTitle) {
                                categories.push({ id: catId, title: catTitle });
                            }
                        });
                        
                        $('#moveVisaPersonalCategoryId').empty().append('<option value="">-- Select Folder --</option>');
                        if (categories.length > 0) {
                            categories.forEach(cat => {
                                $('#moveVisaPersonalCategoryId').append(`<option value="${cat.id}">${cat.title}</option>`);
                            });
                        } else {
                            $('#moveVisaPersonalCategoryId').append('<option value="">No folders found</option>');
                        }
                        $('#moveVisaPersonalCategoryContainer').show();
                        
                    } else if (targetType === 'matter') {
                        // Load visa document categories from DOM (like personal - same UX)
                        const categories = [];
                        $('.subtab6-button').each(function() {
                            const catId = $(this).data('subtab6');
                            const catTitle = $(this).text().trim();
                            if (catId && catTitle) {
                                categories.push({ id: catId, title: catTitle });
                            }
                        });
                        
                        $('#moveVisaVisaCategoryId').empty().append('<option value="">-- Select Folder --</option>');
                        if (categories.length > 0) {
                            categories.forEach(cat => {
                                $('#moveVisaVisaCategoryId').append(`<option value="${cat.id}">${cat.title}</option>`);
                            });
                        } else {
                            $('#moveVisaVisaCategoryId').append('<option value="">No folders found</option>');
                        }
                        $('#moveVisaVisaCategoryContainer').show();
                    }
                });

                // --- Visa Signature Action Bar: Send, Revise, Remove, Reminder ---
                $(document).on('click', '.visa-sig-send-btn', function() {
                    var docId = $(this).data('doc-id');
                    if (!docId) return;
                    var $btn = $(this);
                    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Sending...');
                    $.post('{{ url("/signatures") }}/' + docId + '/send', { _token: '{{ csrf_token() }}' })
                        .done(function() { location.reload(); })
                        .fail(function(xhr) { crmAlert(xhr.responseJSON?.message || 'Failed to send'); $btn.prop('disabled', false).html('<i class="fa-solid fa-paper-plane me-1"></i> Send'); });
                });
                $(document).on('click', '.visa-sig-revise-btn', function() {
                    var docId = $(this).data('doc-id');
                    if (docId) $(document).trigger('openSignaturePlacementModal', { documentId: docId });
                });
                $(document).on('click', '.visa-sig-remove-btn', function() {
                    if (!confirm('Remove signature request? The client will no longer be able to sign this document.')) return;
                    var $bar = $(this).closest('.visa-sig-action-bar');
                    var docId = $bar.data('doc-id');
                    var signerId = $bar.data('signer-id');
                    if (!docId || !signerId) { crmAlert('Unable to remove.'); return; }
                    $.post('{{ url("/signatures") }}/' + docId + '/cancel', { _token: '{{ csrf_token() }}', signer_id: signerId })
                        .done(function() { location.reload(); })
                        .fail(function(xhr) { crmAlert(xhr.responseJSON?.message || 'Failed to remove'); });
                });
                $(document).on('click', '.visa-sig-reminder-btn', function() {
                    var docId = $(this).data('doc-id');
                    var signerId = $(this).data('signer-id');
                    if (!docId || !signerId) return;
                    $.post('{{ url("/signatures") }}/' + docId + '/reminder', { _token: '{{ csrf_token() }}', signer_id: signerId })
                        .done(function() { crmAlert('Reminder sent.'); location.reload(); })
                        .fail(function(xhr) { crmAlert(xhr.responseJSON?.message || 'Failed to send reminder'); });
                });

                // Handle move visa document confirmation
                $(document).on('click', '#confirmMoveVisaDocument', function() {
                    const targetType = $('#moveVisaTargetType').val();
                    let targetId = null;
                    const $error = $('#moveVisaDocumentError');
                    const $btn = $(this);
                    
                    // Validate based on target type
                    if (!targetType) {
                        $error.text('Please select a destination type').show();
                        return;
                    }
                    
                    if (targetType === 'personal') {
                        targetId = $('#moveVisaPersonalCategoryId').val();
                        if (!targetId) {
                            $error.text('Please select a personal folder').show();
                            return;
                        }
                    } else if (targetType === 'matter') {
                        targetId = $('#moveVisaVisaCategoryId').val();
                        if (!targetId) {
                            $error.text('Please select a matter document folder').show();
                            return;
                        }
                    }
                    
                    // Disable button and show loading
                    $btn.prop('disabled', true).text('Moving...');
                    $error.hide();
                    
                    // Make AJAX request
                    $.ajax({
                        url: '{{ URL::to('/documents/move') }}',
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            document_id: currentMoveVisaDocumentId,
                            target_type: targetType,
                            target_id: targetId
                        },
                        success: function(response) {
                            if (response.status) {
                                // Close modal
                                $('#moveVisaDocumentModal').modal('hide');
                                
                                // Show success message using alert
                                crmAlert(response.message || 'Document moved successfully');
                                
                                // Reload the page to refresh document lists
                                location.reload();
                            } else {
                                $error.text(response.message || 'Failed to move document').show();
                                $btn.prop('disabled', false).text('Move Document');
                            }
                        },
                        error: function(xhr) {
                            let errorMsg = 'An error occurred while moving the document';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMsg = xhr.responseJSON.message;
                            }
                            $error.text(errorMsg).show();
                            $btn.prop('disabled', false).text('Move Document');
                        }
                    });
                });

                // Reset button state when modal is closed
                $('#moveVisaDocumentModal').on('hidden.bs.modal', function() {
                    $('#confirmMoveVisaDocument').prop('disabled', false).text('Move Document');
                });

                // Hide context menu on escape key
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        hideVisaContextMenu();
                    }
                });

                // Inline oncontextmenu handlers need globals (incl. lazy-tab globalEval)
                window.showVisaFileContextMenu = showVisaFileContextMenu;
                window.hideVisaContextMenu = hideVisaContextMenu;
                window.handleVisaContextAction = handleVisaContextAction;

            </script>

            <script>
                // ============================================================================
                // VISA DOCUMENTS - DRAG AND DROP INITIALIZATION
                // ============================================================================
                
                function initVisaDocDragDrop() {
                    
                    // Check each drop zone
                    $('.visa-doc-drag-zone').each(function(index) {
                        var $zone = $(this);
                        var fileid = $zone.data('fileid');
                        var formid = $zone.data('formid');
                        var isVisible = $zone.is(':visible');
                    });
                    
                    // IMPORTANT: Remove ALL handlers (including those from detail-main.js)
                    $('.visa-doc-drag-zone').off('click');
                    $('.visa-doc-drag-zone').off('dragenter');
                    $('.visa-doc-drag-zone').off('dragover');
                    $('.visa-doc-drag-zone').off('dragleave');
                    $('.visa-doc-drag-zone').off('drop');
                    
                    // Also remove delegated event handlers
                    $(document).off('click', '.visa-doc-drag-zone');
                    $(document).off('dragenter', '.visa-doc-drag-zone');
                    $(document).off('dragover', '.visa-doc-drag-zone');
                    $(document).off('dragleave', '.visa-doc-drag-zone');
                    $(document).off('drop', '.visa-doc-drag-zone');
                    
                    // Attach handlers DIRECTLY to each drop zone element
                    $('.visa-doc-drag-zone').each(function() {
                        var $zone = $(this);
                        
                        // Click handler
                        $zone.on('click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            e.stopImmediatePropagation();
                            
                            var fileid = $(this).data('fileid');
                            var formid = $(this).data('formid');
                            
                            var fileInput = $('#' + formid).find('.migdocupload');
                            
                            if (fileInput.length > 0) {
                                fileInput[0].click();
                            } else {
                                console.error('❌ File input not found for fileid:', fileid);
                            }
                            
                            return false;
                        });
                        
                        // Dragenter handler
                        $zone.on('dragenter', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            e.stopImmediatePropagation();
                            $(this).addClass('drag_over');
                            return false;
                        });
                        
                        // Dragover handler
                        $zone.on('dragover', function(e) {
                            var event = e.originalEvent || e;
                            event.preventDefault();
                            event.stopPropagation();
                            
                            if (event.dataTransfer) {
                                event.dataTransfer.dropEffect = 'copy';
                            }
                            
                            $(this).addClass('drag_over');
                            return false;
                        });
                        
                        // Dragleave handler
                        $zone.on('dragleave', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            
                            var rect = this.getBoundingClientRect();
                            var x = e.originalEvent.clientX;
                            var y = e.originalEvent.clientY;
                            
                            if (x <= rect.left || x >= rect.right || y <= rect.top || y >= rect.bottom) {
                                $(this).removeClass('drag_over');
                            }
                            return false;
                        });
                        
                        // Drop handler
                        $zone.on('drop', function(e) {
                            var event = e.originalEvent || e;
                            event.preventDefault();
                            event.stopPropagation();
                            event.stopImmediatePropagation();
                            
                            $(this).removeClass('drag_over');
                            
                            var files = event.dataTransfer ? event.dataTransfer.files : null;
                            if (files && files.length > 0) {
                                
                                var fileid = $(this).data('fileid');
                                var formid = $(this).data('formid');
                                var fileInput = $('#' + formid).find('.migdocupload')[0];
                                
                                if (fileInput) {
                                    try {
                                        var dataTransfer = new DataTransfer();
                                        dataTransfer.items.add(files[0]);
                                        fileInput.files = dataTransfer.files;
                                    } catch(err) {
                                        console.warn('⚠️ Fallback to direct assignment');
                                        try {
                                            fileInput.files = files;
                                        } catch(err2) {
                                            console.error('❌ Could not assign file:', err2);
                                        }
                                    }
                                    
                                    $(fileInput).trigger('change');
                                } else {
                                    console.error('❌ File input not found');
                                }
                            }
                            return false;
                        });
                    });
                    
                    // Prevent default drag behavior on document
                    $(document).off('dragover.visadoc').on('dragover.visadoc', function(e) {
                        if ($(e.target).closest('.visa-doc-drag-zone').length > 0) {
                            return;
                        }
                        e.preventDefault();
                    });
                    
                    $(document).off('drop.visadoc').on('drop.visadoc', function(e) {
                        if ($(e.target).closest('.visa-doc-drag-zone').length > 0) {
                            return;
                        }
                        e.preventDefault();
                    });
                    
                }
                
                // Initialize on DOM ready
                $(document).ready(function() {
                    initVisaDocDragDrop();
                });
                
                // Re-initialize when Matter Documents tab is shown
                $(document).on('click', '.client-nav-button[data-tab="matterdocuments"]', function() {
                    setTimeout(function() {
                        initVisaDocDragDrop();
                        if (typeof scheduleClientDocumentsPanelHeightAdjust === 'function') {
                            scheduleClientDocumentsPanelHeightAdjust();
                        } else if (typeof adjustPreviewContainers === 'function') {
                            adjustPreviewContainers();
                        } else if (typeof adjustClientDocumentsPanelHeight === 'function') {
                            adjustClientDocumentsPanelHeight();
                        }
                    }, 200);
                });
                
                // Also check if tab is already active (e.g., direct URL navigation)
                if ($('#matterdocuments-tab').hasClass('active')) {
                    setTimeout(function() {
                        initVisaDocDragDrop();
                        if (typeof scheduleClientDocumentsPanelHeightAdjust === 'function') {
                            scheduleClientDocumentsPanelHeightAdjust();
                        } else if (typeof adjustPreviewContainers === 'function') {
                            adjustPreviewContainers();
                        } else if (typeof adjustClientDocumentsPanelHeight === 'function') {
                            adjustClientDocumentsPanelHeight();
                        }
                    }, 500);
                }
                
                // ============================================================================
                // VISA BULK UPLOAD FUNCTIONALITY
                // ============================================================================

                function formatFileSize(bytes) {
                    if (bytes === 0) return '0 Bytes';
                    const k = 1024;
                    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                    const i = Math.floor(Math.log(bytes) / Math.log(k));
                    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
                }

                function escapeHtml(text) {
                    const map = {
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#039;'
                    };
                    return String(text ?? '').replace(/[&<>"']/g, function(m) { return map[m]; });
                }

                // Keep apostrophes, parentheses, and other display characters from the original filename.
                function extractChecklistNameFromFile(fileName) {
                    let name = String(fileName || '').replace(/\.[^/.]+$/, '');
                    name = name.replace(/_\d{10,}$/, '');
                    name = name.replace(/_/g, ' ');
                    name = name.replace(/\s+/g, ' ').trim();
                    if (!name) {
                        return 'Document';
                    }
                    // Title-case on spaces/hyphens only (do not capitalize after apostrophes).
                    return name.replace(/(^|[\s-])([A-Za-z])/g, function(_, sep, ch) {
                        return sep + ch.toUpperCase();
                    });
                }
                window.extractChecklistNameFromFile = extractChecklistNameFromFile;
                
                let bulkUploadVisaFiles = {};
                let currentVisaCategoryId = null;
                let currentVisaMatterId = <?= $client_selected_matter_id1 ?? 'null' ?>;
                let currentVisaClientId = <?= $fetchedData->id ?>;

                function resetVisaBulkUploadFileInput(categoryId) {
                    const input = document.querySelector(
                        '#bulk-upload-visa-' + categoryId + ' .bulk-upload-file-input-visa[data-categoryid="' + categoryId + '"]'
                    );
                    if (input) {
                        input.value = '';
                    }
                }

                function resetVisaBulkUploadSelection(categoryId) {
                    bulkUploadVisaFiles[categoryId] = [];
                    resetVisaBulkUploadFileInput(categoryId);
                    const container = $('#bulk-upload-visa-' + categoryId);
                    container.find('.bulk-upload-file-list-visa').hide();
                    container.find('.bulk-upload-files-container-visa').empty();
                    container.find('.file-count-visa').text('0');
                }
                
                // Toggle bulk upload dropzone for visa
                $(document).on('click', '.bulk-upload-toggle-btn-visa', function() {
                    const categoryId = $(this).data('categoryid');
                    const matterId = $(this).data('matterid');
                    const dropzoneContainer = $('#bulk-upload-visa-' + categoryId);
                    
                    // Hide all other dropzones first
                    $('.bulk-upload-dropzone-container-visa').not('#bulk-upload-visa-' + categoryId).slideUp();
                    $('.bulk-upload-toggle-btn-visa').not(this).html('<i class="fa-solid fa-upload"></i> Bulk Upload');
                    
                    if (dropzoneContainer.is(':visible')) {
                        dropzoneContainer.slideUp(200, function() {
                            if (typeof scheduleClientDocumentsPanelHeightAdjust === 'function') {
                                scheduleClientDocumentsPanelHeightAdjust();
                            } else if (typeof adjustClientDocumentsPanelHeight === 'function') {
                                adjustClientDocumentsPanelHeight();
                            }
                        });
                        $(this).html('<i class="fa-solid fa-upload"></i> Bulk Upload');
                        resetVisaBulkUploadSelection(categoryId);
                        if (typeof window.hideBulkUploadModal === 'function') {
                            window.hideBulkUploadModal();
                        }
                    } else {
                        if (typeof window.hideBulkUploadModal === 'function') {
                            window.hideBulkUploadModal();
                        }
                        dropzoneContainer.slideDown(200, function() {
                            if (typeof scheduleClientDocumentsPanelHeightAdjust === 'function') {
                                scheduleClientDocumentsPanelHeightAdjust();
                            } else if (typeof adjustClientDocumentsPanelHeight === 'function') {
                                adjustClientDocumentsPanelHeight();
                            }
                        });
                        $(this).html('<i class="fa-solid fa-xmark"></i> Close');
                        currentVisaCategoryId = categoryId;
                        currentVisaMatterId = matterId || null;
                    }
                });
                
                // Initialize bulk upload files array for each visa category
                $('.bulk-upload-dropzone-visa').each(function() {
                    const categoryId = $(this).data('categoryid');
                    if (!bulkUploadVisaFiles[categoryId]) {
                        bulkUploadVisaFiles[categoryId] = [];
                    }
                });
                
                // Click to browse files for visa
                $(document).on('click', '.bulk-upload-dropzone-visa', function(e) {
                    if (!$(e.target).is('input')) {
                        const categoryId = $(this).data('categoryid');
                        $(this).closest('.bulk-upload-dropzone-container-visa').find('.bulk-upload-file-input-visa[data-categoryid="' + categoryId + '"]').click();
                    }
                });
                
                // File input change for visa
                $(document).on('change', '.bulk-upload-file-input-visa', function() {
                    const categoryId = $(this).data('categoryid');
                    const matterId = $(this).data('matterid');
                    const files = this.files;
                    
                    if (files.length > 0) {
                        handleBulkVisaFilesSelected(categoryId, matterId, files);
                    }
                });
                
                // Attach DIRECT handlers to visa bulk upload dropzones for highest priority
                function initVisaBulkUploadDragDrop() {
                    
                    $('.bulk-upload-dropzone-visa').each(function() {
                        var $zone = $(this);
                        var elem = this;
                        
                        // Remove old native listeners if they exist
                        if (elem._visaBulkDragOver) {
                            elem.removeEventListener('dragover', elem._visaBulkDragOver);
                        }
                        if (elem._visaBulkDrop) {
                            elem.removeEventListener('drop', elem._visaBulkDrop);
                        }
                        if (elem._visaBulkDragEnter) {
                            elem.removeEventListener('dragenter', elem._visaBulkDragEnter);
                        }
                        if (elem._visaBulkDragLeave) {
                            elem.removeEventListener('dragleave', elem._visaBulkDragLeave);
                        }
                        
                        // Dragover handler (REQUIRED for drop to work)
                        elem._visaBulkDragOver = function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            e.dataTransfer.dropEffect = 'copy';
                            $zone.addClass('drag_over');
                        };
                        elem.addEventListener('dragover', elem._visaBulkDragOver);
                        
                        // Dragenter handler
                        elem._visaBulkDragEnter = function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            $zone.addClass('drag_over');
                        };
                        elem.addEventListener('dragenter', elem._visaBulkDragEnter);
                        
                        // Dragleave handler
                        elem._visaBulkDragLeave = function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            
                            var rect = elem.getBoundingClientRect();
                            if (e.clientX <= rect.left || e.clientX >= rect.right || 
                                e.clientY <= rect.top || e.clientY >= rect.bottom) {
                                $zone.removeClass('drag_over');
                            }
                        };
                        elem.addEventListener('dragleave', elem._visaBulkDragLeave);
                        
                        // Drop handler
                        elem._visaBulkDrop = function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            $zone.removeClass('drag_over');
                            
                            var files = e.dataTransfer ? e.dataTransfer.files : null;
                            
                            if (files && files.length > 0) {
                                var categoryId = $zone.data('categoryid');
                                var matterId = $zone.data('matterid');
                                handleBulkVisaFilesSelected(categoryId, matterId, files);
                            } else {
                                console.error('❌ No files in visa drop event');
                            }
                        };
                        elem.addEventListener('drop', elem._visaBulkDrop);
                        
                    });
                }
                
                // Initialize visa bulk upload drag-drop when container becomes visible
                $(document).on('click', '.bulk-upload-toggle-btn-visa', function() {
                    setTimeout(function() {
                        initVisaBulkUploadDragDrop();
                    }, 300); // Wait for slideDown animation
                });
                
                // Also initialize on DOM ready for any visible dropzones
                $(document).ready(function() {
                    initVisaBulkUploadDragDrop();
                });
                
                // Keep delegated handlers as fallback
                $(document).on('dragover', '.bulk-upload-dropzone-visa', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $(this).addClass('drag_over');
                    if (e.originalEvent && e.originalEvent.dataTransfer) {
                        e.originalEvent.dataTransfer.dropEffect = 'copy';
                    }
                    return false;
                });
                
                $(document).on('dragenter', '.bulk-upload-dropzone-visa', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $(this).addClass('drag_over');
                    return false;
                });
                
                $(document).on('dragleave', '.bulk-upload-dropzone-visa', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var rect = this.getBoundingClientRect();
                    var x = e.originalEvent.clientX;
                    var y = e.originalEvent.clientY;
                    if (x <= rect.left || x >= rect.right || y <= rect.top || y >= rect.bottom) {
                        $(this).removeClass('drag_over');
                    }
                    return false;
                });
                
                $(document).on('drop', '.bulk-upload-dropzone-visa', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $(this).removeClass('drag_over');
                    
                    const categoryId = $(this).data('categoryid');
                    const matterId = $(this).data('matterid');
                    const files = e.originalEvent && e.originalEvent.dataTransfer ? e.originalEvent.dataTransfer.files : null;
                    
                    
                    if (files && files.length > 0) {
                        handleBulkVisaFilesSelected(categoryId, matterId, files);
                    } else {
                        console.error('❌ No files in visa drop event');
                    }
                    return false;
                });
                
                // Handle visa files selected
                function handleBulkVisaFilesSelected(categoryId, matterId, files) {
                    if (!bulkUploadVisaFiles[categoryId]) {
                        bulkUploadVisaFiles[categoryId] = [];
                    }
                    
                    // Validate and add files to array (aligned with personal documents)
                    const invalidFiles = [];
                    const maxFileMb = (typeof window.__CRM_DOC_MAX_FILE_MB__ === 'number' && window.__CRM_DOC_MAX_FILE_MB__ > 0) ? window.__CRM_DOC_MAX_FILE_MB__ : 100;
                    const maxVideoMb = (typeof window.__CRM_DOC_MAX_VIDEO_MB__ === 'number' && window.__CRM_DOC_MAX_VIDEO_MB__ > 0) ? window.__CRM_DOC_MAX_VIDEO_MB__ : 300;
                    const maxSize = maxFileMb * 1024 * 1024;
                    const maxVideoSize = maxVideoMb * 1024 * 1024;
                    const videoExtensions = ['mp4', 'webm', 'mov', 'm4v', 'avi', 'mkv', 'vob'];
                    const allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'mp3'].concat(videoExtensions);
                    
                    Array.from(files).forEach(file => {
                        const ext = file.name.split('.').pop().toLowerCase();
                        const isVideo = (typeof isPersonalDocVideoFile === 'function' && isPersonalDocVideoFile(file))
                            || videoExtensions.includes(ext)
                            || String(file.type || '').indexOf('video/') === 0;
                        const sizeLimit = isVideo ? maxVideoSize : maxSize;
                        const sizeLabel = isVideo ? (maxVideoMb + 'MB') : (maxFileMb + 'MB');

                        // Check file size
                        if (file.size > sizeLimit) {
                            invalidFiles.push(file.name + ' (exceeds ' + sizeLabel + ')');
                            return;
                        }
                        
                        // Check file extension / Teams video MIME
                        if (!isVideo && !allowedExtensions.includes(ext)) {
                            invalidFiles.push(file.name + ' (invalid file type)');
                            return;
                        }
                        
                        // Stored names are generated; allow Teams meeting titles (colons, apostrophes, etc.)
                        if (!file.name || /[\/\\]/.test(file.name)) {
                            invalidFiles.push(file.name + ' (invalid characters in name)');
                            return;
                        }
                        
                        // Check if file already exists
                        const exists = bulkUploadVisaFiles[categoryId].some(f => f.name === file.name && f.size === file.size);
                        if (!exists) {
                            bulkUploadVisaFiles[categoryId].push(file);
                        }
                    });
                    
                    if (invalidFiles.length > 0) {
                        crmAlert('The following files were skipped:\n' + invalidFiles.join('\n'));
                    }
                    
                    if (bulkUploadVisaFiles[categoryId].length === 0) {
                        crmAlert('No valid files selected. Please select PDF, JPG, PNG, DOC, DOCX, XLS, XLSX, CSV, MP3, videos (MP4, WebM, MOV, VOB, etc.), or MS Teams recordings under the size limit.');
                        return;
                    }
                    
                    // Show file list
                    const container = $('#bulk-upload-visa-' + categoryId);
                    container.find('.bulk-upload-file-list-visa').show();
                    container.find('.file-count-visa').text(bulkUploadVisaFiles[categoryId].length);
                    
                    // Show mapping interface
                    showBulkVisaUploadMapping(categoryId, matterId);
                }
                
                // Show visa mapping interface
                function showBulkVisaUploadMapping(categoryId, matterId) {
                    currentVisaCategoryId = categoryId;
                    currentVisaMatterId = matterId || null;
                    const files = bulkUploadVisaFiles[categoryId];
                    
                    if (files.length === 0) {
                        return;
                    }
                    
                    // Get existing checklists for this visa category
                    getExistingVisaChecklists(categoryId, function(checklists) {
                        // Call backend to get auto-matches
                        getAutoVisaChecklistMatches(categoryId, files, checklists, function(matches) {
                            displayVisaMappingInterface(files, checklists, matches);
                        });
                    });
                }
                
                // Get existing visa checklists
                function getExistingVisaChecklists(categoryId, callback) {
                    const checklists = [];
                    const checklistNames = new Set();
                    
                    $('.migdocumnetlist_' + categoryId + ' .visachecklist-row').each(function() {
                        const checklistName = String($(this).data('visachecklistname') || '').trim();
                        const checklistId = $(this).closest('tr').attr('id').replace('id_', '');
                        
                        if (checklistName && !checklistNames.has(checklistName)) {
                            checklistNames.add(checklistName);
                            checklists.push({
                                id: checklistId,
                                name: checklistName
                            });
                        }
                    });
                    
                    callback(checklists);
                }
                
                // Get auto-checklist matches for visa from backend
                function getAutoVisaChecklistMatches(categoryId, files, checklists, callback) {
                    const fileData = Array.from(files).map(file => ({
                        name: file.name,
                        size: file.size,
                        type: file.type
                    }));
                    
                    const checklistNames = checklists.map(function(c) { return String(c.name || ''); });
                    
                    $.ajax({
                        url: '{{ route("clients.documents.getAutoChecklistMatches") }}',
                        method: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            clientid: currentVisaClientId,
                            categoryid: categoryId,
                            files: fileData,
                            checklists: checklistNames
                        },
                        success: function(response) {
                            if (response.status) {
                                callback(response.matches || {});
                            } else {
                                callback({});
                            }
                        },
                        error: function() {
                            callback({});
                        }
                    });
                }
                
                // Display visa mapping interface (reuse the same modal)
                function displayVisaMappingInterface(files, checklists, matches) {
                    const modal = $('#bulk-upload-mapping-modal');
                    const tableContainer = $('#bulk-upload-mapping-table');
                    
                    let html = '<table class="table table-bordered checklist-table" style="width: 100%;">';
                    html += '<thead><tr><th style="width: 25%;">File Name</th><th style="width: 45%;">Checklist Assignment</th><th style="width: 20%;">Status</th><th style="width: 10%; text-align: center;">Actions</th></tr></thead>';
                    html += '<tbody>';
                    
                    Array.from(files).forEach((file, index) => {
                        const fileName = file.name;
                        const fileSize = formatFileSize(file.size);
                        const match = matches[fileName] || null;
                        const autoCreateDefault = $('#auto-create-unmatched').is(':checked');
                        
                        let selectedChecklist = '';
                        let statusClass = 'manual';
                        let statusText = 'Manual selection';
                        let preferNewChecklist = false;
                        
                        if (match && match.checklist != null && match.checklist !== '') {
                            selectedChecklist = String(match.checklist);
                            statusClass = match.confidence === 'high' ? 'auto-matched' : 'manual';
                            statusText = match.confidence === 'high' ? 'Auto-matched' : 'Suggested';
                        } else if (autoCreateDefault) {
                            preferNewChecklist = true;
                            statusClass = 'new-checklist';
                            statusText = 'New checklist';
                        }
                        
                        html += '<tr class="bulk-upload-file-item" data-file-index="' + index + '" data-file-name="' + escapeHtml(fileName) + '">';
                        html += '<td>';
                        html += '<div class="file-info">';
                        html += '<i class="fa-solid fa-file matter-bulk-file-icon"></i>';
                        html += '<div>';
                        html += '<div class="file-name">' + escapeHtml(fileName) + '</div>';
                        html += '<div class="file-size">' + fileSize + '</div>';
                        html += '</div>';
                        html += '</div>';
                        html += '</td>';
                        html += '<td>';
                        html += '<select class="form-control checklist-select" data-file-index="' + index + '" data-file-name="' + escapeHtml(fileName) + '">';
                        html += '<option value="">-- Select Checklist --</option>';
                        html += '<option value="__NEW__"' + (preferNewChecklist ? ' selected' : '') + '>+ Create New Checklist</option>';
                        checklists.forEach(function(checklist) {
                            const checklistName = String(checklist.name || '');
                            const selected = !preferNewChecklist && selectedChecklist === checklistName ? 'selected' : '';
                            html += '<option value="' + escapeHtml(checklistName) + '" ' + selected + '>' + escapeHtml(checklistName) + '</option>';
                        });
                        html += '</select>';
                        html += '<input type="text" class="form-control mt-2 new-checklist-input" data-file-index="' + index + '" placeholder="Enter new checklist name" value="' + escapeHtml(extractChecklistNameFromFile(fileName)) + '"' + (preferNewChecklist ? '' : ' style="display: none;"') + '>';
                        html += '</td>';
                        html += '<td>';
                        html += '<span class="match-status ' + statusClass + '">' + statusText + '</span>';
                        html += '</td>';
                        html += '<td style="text-align: center;">';
                        html += '<button type="button" class="btn btn-sm btn-danger remove-bulk-file" data-file-index="' + index + '" title="Remove file">';
                        html += '<i class="fa-solid fa-trash-can"></i>';
                        html += '</button>';
                        html += '</td>';
                        html += '</tr>';
                    });
                    
                    html += '</tbody></table>';
                    tableContainer.html(html);
                    
                    // Handle new checklist option
                    $(document).off('change.bulkUploadMapping', '.checklist-select').on('change.bulkUploadMapping', '.checklist-select', function() {
                        const fileIndex = $(this).data('file-index');
                        const value = $(this).val();
                        const $row = $(this).closest('tr');
                        const newInput = $row.find('.new-checklist-input[data-file-index="' + fileIndex + '"]');
                        const originalFileName = $row.attr('data-file-name') || '';
                        
                        if (value === '__NEW__') {
                            if (!String(newInput.val() || '').trim()) {
                                newInput.val(extractChecklistNameFromFile(originalFileName));
                            }
                            newInput.show();
                            newInput.attr('required', true);
                            $row.find('.match-status').removeClass('auto-matched manual').addClass('new-checklist').text('New checklist');
                        } else {
                            newInput.hide();
                            newInput.removeAttr('required');
                            if (value) {
                                $row.find('.match-status').removeClass('new-checklist').addClass('manual').text('Manual selection');
                            }
                        }
                    });
                    
                    // Handle remove file button for visa documents
                    $(document).off('click.bulkUploadRemove', '.remove-bulk-file').on('click.bulkUploadRemove', '.remove-bulk-file', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        const $row = $(this).closest('tr');
                        const fileName = $row.data('file-name');
                        const categoryId = currentVisaCategoryId;
                        
                        // Confirm before removing
                        if (!confirm('Are you sure you want to remove "' + fileName + '" from the upload list?')) {
                            return;
                        }
                        
                        // Find and remove the file from the array by matching file name
                        const fileArray = bulkUploadVisaFiles[categoryId] || [];
                        const fileIndex = fileArray.findIndex(f => f.name === fileName);
                        
                        if (fileIndex > -1) {
                            fileArray.splice(fileIndex, 1);
                        }
                        
                        // Remove the row
                        $row.remove();
                        
                        // Update file count
                        const remainingCount = fileArray.length;
                        const container = $('#bulk-upload-visa-' + categoryId);
                        container.find('.file-count-visa').text(remainingCount);
                        
                        // If no files left, hide the file list and modal
                        if (remainingCount === 0) {
                            if (typeof window.hideBulkUploadModal === 'function') {
                                window.hideBulkUploadModal();
                            }
                            resetVisaBulkUploadSelection(categoryId);
                            crmAlert('All files have been removed. Please select files again to upload.');
                        } else {
                            // Reindex remaining rows to maintain correct file indices
                            $('#bulk-upload-mapping-table tbody tr').each(function(newIndex) {
                                $(this).attr('data-file-index', newIndex);
                                $(this).find('.checklist-select').attr('data-file-index', newIndex);
                                $(this).find('.new-checklist-input').attr('data-file-index', newIndex);
                                $(this).find('.remove-bulk-file').attr('data-file-index', newIndex);
                            });
                        }
                    });
                    
                    window._bulkUploadConfirmFn = confirmVisaBulkUpload;
                    window._bulkUploadOnCancel = function() {
                        if (currentVisaCategoryId) {
                            resetVisaBulkUploadSelection(currentVisaCategoryId);
                        }
                    };
                    
                    modal.show();
                }
                
                // Confirm visa bulk upload
                function confirmVisaBulkUpload() {
                    const categoryId = currentVisaCategoryId;
                    const matterId = $('#sel_matter_id_client_detail').val() || currentVisaMatterId;
                    const files = bulkUploadVisaFiles[categoryId] || [];
                    if (!files.length) {
                        crmAlert('No files selected. Please select files to upload.');
                        return;
                    }
                    const mappings = [];
                    const autoCreate = $('#auto-create-unmatched').is(':checked');
                    
                    // Collect mappings in order of files
                    Array.from(files).forEach((file, fileIndex) => {
                        const fileName = file.name;
                        const selectElement = $('.checklist-select[data-file-index="' + fileIndex + '"]');
                        
                        if (selectElement.length === 0) {
                            mappings.push(null);
                            return;
                        }
                        
                        const checklist = selectElement.val();
                        
                        let mapping = null;
                        
                        if (checklist === '__NEW__') {
                            const newChecklistName = selectElement.closest('tr').find('.new-checklist-input').val();
                            if (newChecklistName) {
                                mapping = {
                                    type: 'new',
                                    name: newChecklistName.trim()
                                };
                            } else if (autoCreate) {
                                mapping = {
                                    type: 'new',
                                    name: extractChecklistNameFromFile(fileName)
                                };
                            }
                        } else if (checklist) {
                            mapping = {
                                type: 'existing',
                                name: checklist
                            };
                        } else if (autoCreate) {
                            mapping = {
                                type: 'new',
                                name: extractChecklistNameFromFile(fileName)
                            };
                        }
                        
                        if (!mapping) {
                            const matchStatus = selectElement.closest('tr').find('.match-status');
                            if (matchStatus.hasClass('auto-matched') || matchStatus.hasClass('manual')) {
                                const selectedOption = selectElement.find('option:selected');
                                if (selectedOption.val() && selectedOption.val() !== '__NEW__') {
                                    mapping = {
                                        type: 'existing',
                                        name: selectedOption.val()
                                    };
                                }
                            }
                        }
                        
                        mappings.push(mapping);
                    });
                    
                    // Validate all files have mappings
                    const unmappedFiles = [];
                    mappings.forEach((mapping, index) => {
                        if (!mapping || !mapping.name) {
                            unmappedFiles.push(files[index].name);
                        }
                    });
                    
                    if (unmappedFiles.length > 0 && !autoCreate) {
                        crmAlert('Please map all files to checklists or enable "Auto-create checklist for unmatched files"');
                        return;
                    }
                    
                    // Fill in any missing mappings with auto-create
                    mappings.forEach((mapping, index) => {
                        if (!mapping || !mapping.name) {
                            mappings[index] = {
                                type: 'new',
                                name: extractChecklistNameFromFile(files[index].name)
                            };
                        }
                    });
                    
                    // Upload files
                    uploadBulkVisaFiles(categoryId, matterId, files, mappings);
                }

                function showBulkUploadSuccessToast(message) {
                    if (typeof crmNotify !== 'undefined') {
                        crmNotify.success({
                            title: 'Success',
                            message: message,
                            position: 'topRight',
                            transitionIn: 'fadeInDown',
                            transitionOut: 'fadeOutUp'
                        });
                    }
                }

                function refreshMatterDocumentFolder(categoryId, matterId) {
                    return $.ajax({
                        url: '{{ route("clients.documents.reloadFolderList") }}',
                        method: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            clientid: currentVisaClientId,
                            folder_name: String(categoryId),
                            doctype: 'matter',
                            type: 'client'
                        }
                    }).done(function(result) {
                        if (result.status && result.data !== undefined) {
                            $('.migdocumnetlist_' + categoryId).html(result.data);
                            if (result.griddata !== undefined) {
                                $('#' + categoryId + '-subtab6 .miggriddata').html(result.griddata);
                            }
                            if (typeof initVisaDocDragDrop === 'function') {
                                initVisaDocDragDrop();
                            }
                            var activeMatterId = $('#sel_matter_id_client_detail').val();
                            if (window.SidebarTabs) {
                                window.SidebarTabs.selectedMatter = activeMatterId;
                            }
                            if (window.SidebarTabs && typeof window.SidebarTabs.filtermatterdocumentsByMatter === 'function') {
                                window.SidebarTabs.filtermatterdocumentsByMatter(activeMatterId);
                            }
                        }
                    });
                }
                window.refreshMatterDocumentFolder = refreshMatterDocumentFolder;

                function finishBulkVisaUploadUi(categoryId) {
                    if (typeof window.hideBulkUploadModal === 'function') {
                        window.hideBulkUploadModal();
                    }
                    resetVisaBulkUploadSelection(categoryId);
                    $('#bulk-upload-progress').hide();
                    $('#confirm-bulk-upload').prop('disabled', false);
                    if (typeof getallactivities === 'function') {
                        getallactivities();
                    }
                }
                
                // Upload bulk visa files
                function uploadBulkVisaFiles(categoryId, matterId, files, mappings) {
                    const formData = new FormData();
                    formData.append('_token', '{{ csrf_token() }}');
                    formData.append('clientid', currentVisaClientId);
                    formData.append('categoryid', categoryId);
                    formData.append('matterid', matterId || '');
                    formData.append('doctype', 'visa');
                    formData.append('type', 'client');

                    const fileList = Array.from(files);
                    const videoFiles = fileList.filter(function(file) {
                        if (typeof isPersonalDocVideoFile === 'function') {
                            return isPersonalDocVideoFile(file);
                        }
                        const ext = (file.name.split('.').pop() || '').toLowerCase();
                        return ['mp4', 'webm', 'mov', 'm4v', 'avi', 'mkv', 'vob'].includes(ext)
                            || String(file.type || '').indexOf('video/') === 0;
                    });
                    const hasVideos = videoFiles.length > 0;
                    
                    // Add files
                    fileList.forEach((file, index) => {
                        formData.append('files[]', file);
                        const mapping = mappings[index] || { type: 'new', name: extractChecklistNameFromFile(file.name) };
                        formData.append('mappings[]', JSON.stringify(mapping));
                    });
                    
                    // Show progress
                    $('#confirm-bulk-upload').prop('disabled', true);
                    if (hasVideos && typeof showPersonalVideoUploadLoader === 'function') {
                        if (typeof window.hideBulkUploadModal === 'function') {
                            window.hideBulkUploadModal();
                        }
                        showPersonalVideoUploadLoader({
                            title: videoFiles.length === fileList.length ? 'Uploading Videos' : 'Uploading Files',
                            filename: videoFiles.length === 1 ? videoFiles[0].name : (videoFiles.length + ' video(s) in batch'),
                            fileSize: videoFiles.reduce(function(sum, f) { return sum + (f.size || 0); }, 0),
                            message: 'Uploading files to server…'
                        });
                    } else {
                        $('#bulk-upload-progress').show();
                        $('#bulk-upload-progress-bar').css('width', '0%').text('0%');
                    }
                    
                    $.ajax({
                        url: '{{ route("clients.documents.bulkUploadMatterDocuments") }}',
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        timeout: hasVideos ? 0 : undefined,
                        xhr: function() {
                            const xhr = new window.XMLHttpRequest();
                            xhr.upload.addEventListener('progress', function(e) {
                                if (e.lengthComputable) {
                                    const percentComplete = (e.loaded / e.total) * 100;
                                    if (hasVideos && typeof updatePersonalVideoUploadLoader === 'function') {
                                        const overallPct = Math.round((e.loaded / e.total) * 45);
                                        updatePersonalVideoUploadLoader(
                                            'upload',
                                            overallPct,
                                            'Uploading… ' + Math.round(percentComplete) + '%'
                                        );
                                        if (percentComplete >= 100 && typeof startPersonalVideoProcessingPulse === 'function') {
                                            startPersonalVideoProcessingPulse(
                                                'processing',
                                                48,
                                                88,
                                                'Saving video(s) to cloud storage…'
                                            );
                                        }
                                    } else {
                                        $('#bulk-upload-progress-bar').css('width', percentComplete + '%').text(Math.round(percentComplete) + '%');
                                    }
                                }
                            }, false);
                            return xhr;
                        },
                        success: function(response) {
                            if (response.status) {
                                var queuedVideos = response.queued_videos || [];
                                var tokens = queuedVideos.map(function(item) {
                                    return item.token;
                                }).filter(Boolean);

                                var afterVideos = function() {
                                    let message = response.message || 'Files uploaded successfully!';
                                    if (response.errors && response.errors.length > 0) {
                                        message += '\n\nWarnings:\n' + response.errors.join('\n');
                                    }
                                    if (typeof hidePersonalVideoUploadLoader === 'function') {
                                        hidePersonalVideoUploadLoader(700);
                                    }
                                    showBulkUploadSuccessToast(message);
                                    refreshMatterDocumentFolder(categoryId, matterId).always(function() {
                                        finishBulkVisaUploadUi(categoryId);
                                    });
                                };

                                if (tokens.length > 0 && typeof waitForPersonalVideoUploads === 'function') {
                                    if (hasVideos && typeof updatePersonalVideoUploadLoader === 'function') {
                                        updatePersonalVideoUploadLoader('queued', 44, 'Upload complete. Processing video(s) in queue…');
                                    }
                                    waitForPersonalVideoUploads(tokens, function(success, message) {
                                        if (typeof showPersonalDocVideoToast === 'function') {
                                            showPersonalDocVideoToast(success, message);
                                        }
                                        afterVideos();
                                    });
                                    return;
                                }

                                afterVideos();
                            } else {
                                let errorMsg = response.message || 'Upload failed';
                                if (response.errors && response.errors.length > 0) {
                                    errorMsg += '\n\nDetails:\n' + response.errors.join('\n');
                                }
                                if (typeof hidePersonalVideoUploadLoader === 'function') {
                                    hidePersonalVideoUploadLoader(0);
                                }
                                if (typeof crmNotify !== 'undefined') {
                                    crmNotify.error({
                                        title: 'Error',
                                        message: errorMsg,
                                        position: 'topRight',
                                        timeout: 8000,
                                        transitionIn: 'fadeInDown',
                                        transitionOut: 'fadeOutUp'
                                    });
                                }
                                $('#bulk-upload-progress').hide();
                                $('#confirm-bulk-upload').prop('disabled', false);
                                resetVisaBulkUploadFileInput(categoryId);
                            }
                        },
                        error: function(xhr) {
                            if (typeof hidePersonalVideoUploadLoader === 'function') {
                                hidePersonalVideoUploadLoader(0);
                            }
                            let errorMsg = 'Upload failed';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMsg = xhr.responseJSON.message;
                            } else if (xhr.responseJSON && xhr.responseJSON.errors && xhr.responseJSON.errors.length) {
                                errorMsg = xhr.responseJSON.errors.join('\n');
                            } else if (xhr.status === 413) {
                                errorMsg = 'Upload failed: file(s) too large for the server.';
                            } else if (xhr.status === 419) {
                                errorMsg = 'Upload failed: session expired. Please refresh and try again.';
                            } else if (xhr.status) {
                                errorMsg = 'Upload failed (HTTP ' + xhr.status + '). Special characters in file names are allowed — please try again.';
                            }
                            if (typeof crmNotify !== 'undefined') {
                                crmNotify.error({
                                    title: 'Error',
                                    message: errorMsg,
                                    position: 'topRight',
                                    timeout: 8000,
                                    transitionIn: 'fadeInDown',
                                    transitionOut: 'fadeOutUp'
                                });
                            }
                            $('#bulk-upload-progress').hide();
                            $('#confirm-bulk-upload').prop('disabled', false);
                            resetVisaBulkUploadFileInput(categoryId);
                        }
                    });
                }
            </script>

            <style>
                /* theme.md tokens via crm-theme.css :root */
                .context-menu-item:hover {
                    background-color: var(--sidebar-bg, #ddeaf8);
                }

                /* Bulk Upload Dropzone Styles for Visa */
                .bulk-upload-dropzone-visa {
                    position: relative;
                }
                
                /* Make all child elements transparent to pointer events so drag events reach the dropzone */
                .bulk-upload-dropzone-visa * {
                    pointer-events: none;
                }
                
                .bulk-upload-dropzone-visa.drag_over {
                    border-color: var(--success, #1e7a52);
                    background-color: rgba(30, 122, 82, 0.08);
                }

                /* Drag and Drop Zone Styles */
                .document-drag-drop-zone {
                    border: 2px dashed var(--border, #c8dcef);
                    border-radius: 4px;
                    padding: 15px 20px;
                    text-align: center;
                    background-color: var(--page-bg, #f0f6ff);
                    cursor: pointer !important;
                    transition: all 0.3s ease;
                    min-height: 60px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 5px 0;
                    position: relative;
                    z-index: 1;
                }
                
                /* Make all child elements transparent to pointer events so drag events reach the dropzone */
                .document-drag-drop-zone * {
                    pointer-events: none;
                }

                .document-drag-drop-zone:hover {
                    border-color: var(--sidebar-active, #3a6fa8);
                    background-color: var(--sidebar-bg, #ddeaf8);
                }

                .document-drag-drop-zone.drag_over {
                    border-color: var(--success, #1e7a52);
                    background-color: rgba(30, 122, 82, 0.08);
                    border-width: 3px;
                }

                .drag-zone-inner {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    color: var(--text-dark, #1a2c40);
                }

                .drag-zone-inner i {
                    font-size: 20px;
                    color: var(--sidebar-active, #3a6fa8);
                }

                .drag-zone-text {
                    font-size: 14px;
                    color: inherit;
                }

                .document-drag-drop-zone.uploading {
                    pointer-events: none;
                    opacity: 0.6;
                }

                .document-drag-drop-zone.uploading .drag-zone-text::after {
                    content: ' Uploading...';
                    font-weight: bold;
                    color: var(--sidebar-active, #3a6fa8);
                }

                /* Bulk Upload File List Styles */
                #bulk-upload-mapping-table table tbody tr {
                    border-bottom: 1px solid #dee2e6;
                }

                #bulk-upload-mapping-table table tbody tr td {
                    padding: 15px 10px !important;
                }

                .bulk-upload-file-item {
                    vertical-align: top;
                }

                .bulk-upload-file-item td {
                    padding: 12px 8px !important;
                    vertical-align: top !important;
                }

                .bulk-upload-file-item .file-info {
                    display: flex;
                    align-items: flex-start;
                    gap: 10px;
                    min-height: 40px;
                }

                .bulk-upload-file-item .file-info > div {
                    flex: 1;
                    display: flex;
                    flex-direction: column;
                    gap: 4px;
                }

                .bulk-upload-file-item .file-name {
                    font-weight: 500;
                    color: #333;
                    word-break: break-word;
                    overflow-wrap: break-word;
                    white-space: normal;
                    line-height: 1.4;
                    display: block;
                }

                .bulk-upload-file-item .file-size {
                    font-size: 12px;
                    color: #4b5563;
                }

                .bulk-upload-file-item .checklist-select {
                    min-width: 200px;
                }

                .bulk-upload-file-item .match-status {
                    font-size: 12px;
                    padding: 2px 8px;
                    border-radius: 3px;
                }

                .match-status.auto-matched {
                    background-color: #d4edda;
                    color: #155724;
                }

                .match-status.manual {
                    background-color: #fff3cd;
                    color: #856404;
                }

                .match-status.new-checklist {
                    background-color: #cce5ff;
                    color: #004085;
                }

                .remove-bulk-file {
                    padding: 4px 8px;
                    font-size: 14px;
                    transition: all 0.2s ease;
                }

                .remove-bulk-file:hover {
                    background-color: #c82333;
                    border-color: #bd2130;
                    transform: scale(1.1);
                }

                .remove-bulk-file i {
                    pointer-events: none;
                }
            </style>
            </div>{{-- /#matterdocuments-tab --}}

