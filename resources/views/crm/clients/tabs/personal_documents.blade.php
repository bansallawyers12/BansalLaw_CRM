           <!-- Personal Documents Tab (Client-Level) -->
           <div class="tab-pane client-documents-tab{{ strtolower((string) ($activeTab ?? '')) === 'personaldocuments' ? ' active' : '' }}" id="personaldocuments-tab">
                <div class="card full-width documentalls-container">
                    <?php
                    $clientId = $fetchedData->id ?? null;
                    $_pdViewer = \Illuminate\Support\Facades\Auth::user();
                    $isSuperAdmin = $_pdViewer instanceof \App\Models\Staff && $_pdViewer->hasEffectiveSuperAdminPrivileges();
                    $persDocCatList = \Illuminate\Support\Facades\Schema::hasTable('personal_document_types')
                        ? \App\Models\PersonalDocumentType::select('id', 'title', 'client_id')
                            ->where('status', 1)
                            ->where(function ($query) use ($clientId) {
                                $query->whereNull('client_id')
                                    ->orWhere('client_id', $clientId);
                            })
                            ->orderBy('id', 'ASC')
                            ->get()
                        : collect();
                    $documentsTableReadyPersonal = \Illuminate\Support\Facades\Schema::hasTable('documents')
                        && \Illuminate\Support\Facades\Schema::hasColumn('documents', 'client_id')
                        && \Illuminate\Support\Facades\Schema::hasColumn('documents', 'not_used_doc')
                        && \Illuminate\Support\Facades\Schema::hasColumn('documents', 'folder_name')
                        && \Illuminate\Support\Facades\Schema::hasColumn('documents', 'doc_type')
                        && \Illuminate\Support\Facades\Schema::hasColumn('documents', 'type');
                    ?>

                    @if (! \Illuminate\Support\Facades\Schema::hasTable('personal_document_types'))
                        <div class="alert alert-warning" style="margin: 15px;">
                            Personal document categories are unavailable: the <code>personal_document_types</code> table is missing.
                            Run <code>php artisan migrate</code> on the server, then reload this page.
                        </div>
                    @endif

                    <!-- Personal Documents Content -->
                    <div class="personal-documents-content" id="personal-documents-content">
                        <!-- Document Type Subtabs Container -->
                        <div class="subtab-header-container pd-folder-bar">
                            <nav class="subtabs2 pd-folder-tabs">
                                <?php foreach ($persDocCatList as $catVal): ?>
                                    <?php
                                    $id = $catVal->id;
                                    $isActive = $id == 1 ? 'active' : '';
                                    $isClientGenerated = $catVal->client_id !== null;
                                    ?>
                                    <div class="button-container pd-folder-tab-wrap">
                                        <button type="button" class="subtab2-button <?= $isActive ?>" data-subtab2="<?= $id ?>">
                                            <?= \App\Support\DocumentLabel::forDisplay($catVal->title) ?>
                                        </button>
                                        <?php if ($isClientGenerated || $isSuperAdmin): ?>
                                            <div class="action-buttons pd-folder-tab-actions">
                                                <?php if ($isClientGenerated): ?>
                                                    <button type="button" class="btn btn-sm btn-warning update-personal-cat-title" data-id="<?= $id ?>" title="Rename folder"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i></button>
                                                <?php endif; ?>
                                                <?php if ($isSuperAdmin): ?>
                                                    <button type="button" class="btn btn-sm btn-danger delete-personal-cat-title" data-id="<?= $id ?>" data-title="<?= \App\Support\DocumentLabel::forDisplay($catVal->title) ?>" title="Delete folder"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </nav>
                            <div class="pd-header-actions">
                                <button type="button" class="btn pd-btn pd-btn-light add_personal_doc_cat-btn add_personal_doc_cat" data-type="personal" data-categoryid="">
                                    <i class="fa-solid fa-plus"></i> Add Folder
                                </button>
                                <button type="button" class="btn pd-btn pd-btn-ghost pd-not-used-btn client-nav-button" data-tab="notuseddocuments">
                                    <i class="fa-solid fa-folder-minus"></i> Not Used Documents
                                </button>
                            </div>
                        </div>

                        <!-- Subtab2 Contents -->
                        <div class="subtab2-content">
                            <?php foreach ($persDocCatList as $catVal): ?>
                                <?php
                                $id = $catVal->id;
                                $isActive = $id == 1 ? 'active' : '';
                                $folderName = $id;
                                ?>

                                <div class="subtab2-pane <?= $isActive ?>" id="<?= $id ?>-subtab2">
                                    <div class="checklist-table-container">
                                        <div class="subtab2-header pd-section-header">
                                            <h3><i class="fa-solid fa-file-lines"></i> <?= \App\Support\DocumentLabel::forDisplay($catVal->title) ?> Documents</h3>
                                            <div class="pd-section-actions">
                                                <button type="button" class="btn pd-btn pd-btn-primary add-checklist-btn add_education_doc" data-type="personal" data-categoryid="<?= $id ?>">
                                                    <i class="fa-solid fa-plus"></i> Add Checklist
                                                </button>
                                                <button type="button" class="btn pd-btn pd-btn-outline bulk-upload-toggle-btn" data-categoryid="<?= $id ?>" data-categoryname="<?= \App\Support\DocumentLabel::forDisplay($catVal->title) ?>">
                                                    <i class="fa-solid fa-upload"></i> Bulk Upload
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <!-- Bulk Upload Dropzone (Hidden by default) -->
                                        <div class="bulk-upload-dropzone-container" id="bulk-upload-<?= $id ?>" style="display: none; margin: 15px 0; padding: 20px;">
                                            <div class="bulk-upload-dropzone" data-categoryid="<?= $id ?>" style="text-align: center; padding: 30px; cursor: pointer;">
                                                <i class="fa-solid fa-cloud-arrow-up bulk-upload-icon"></i>
                                                <p class="bulk-upload-lead">
                                                    <strong>Drag and drop files here</strong> or <strong>click to browse</strong>
                                                </p>
                                                <p class="bulk-upload-hint">PDF, images, Word, Excel (XLS/XLSX/CSV), audio (MP3), videos (MP4, WebM, MOV, VOB, etc.), and MS Teams recordings — up to {{ (int) config('crm.document_upload.max_file_size_mb', 100) }}MB ({{ (int) config('crm.personal_video_upload.max_size_mb', 300) }}MB for videos)</p>
                                                <input type="file" class="bulk-upload-file-input" data-categoryid="<?= $id ?>" multiple style="display: none;" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.csv,.mp3,.mp4,.webm,.mov,.m4v,.avi,.mkv,.vob,audio/mpeg,audio/mp3,video/mp4,video/webm,video/quicktime,video/mpeg,video/*">
                                            </div>
                                            <div class="bulk-upload-file-list" style="display: none; margin-top: 20px;">
                                                <h5 style="margin-bottom: 15px;">Files Selected: <span class="file-count">0</span></h5>
                                                <div class="bulk-upload-files-container"></div>
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
                                            <tbody class="tdata persdocumnetlist documnetlist_<?= $id ?>">
                                                <?php
                                                $documents = collect();
                                                if ($documentsTableReadyPersonal) {
                                                    $documents = \App\Models\Document::with('staff')->where('client_id', $clientId)
                                                        ->whereNull('not_used_doc')
                                                        ->where('doc_type', 'personal')
                                                        ->where('folder_name', $folderName)
                                                        ->where('type', 'client')
                                                        ->orderBy('created_at', 'DESC')
                                                        ->get();
                                                }
                                                ?>
                                                <?php foreach ($documents as $docKey => $fetch): ?>
                                                    <?php
                                                    $admin = $fetch->staff;
                                                    
                                                    // Private S3: use app preview route (presigned redirect), not a direct bucket URL
                                                    $previewUrl = url('/documents/preview/' . $fetch->id);
                                                    $fileExt = strtolower((string) ($fetch->filetype ?? ''));
                                                    if (in_array($fileExt, ['mp4', 'webm', 'mov', 'm4v', 'avi', 'mkv', 'vob'], true)) {
                                                        $fileIcon = 'fa-file-video';
                                                    } elseif (in_array($fileExt, ['xls', 'xlsx', 'csv', 'ods'], true)) {
                                                        $fileIcon = 'fa-file-excel';
                                                    } elseif (in_array($fileExt, ['doc', 'docx', 'rtf', 'odt'], true)) {
                                                        $fileIcon = 'fa-file-word';
                                                    } elseif ($fileExt === 'pdf') {
                                                        $fileIcon = 'fa-file-pdf';
                                                    } else {
                                                        $fileIcon = 'fa-file-image';
                                                    }
                                                    ?>
                                                    <tr class="drow" id="id_<?= $fetch->id ?>">
                                                        <td style="white-space: initial;">
                                                            <div data-id="<?= $fetch->id ?>" data-personalchecklistname="<?= \App\Support\DocumentLabel::forDisplay($fetch->checklist) ?>" class="personalchecklist-row" title="Uploaded by: <?= \App\Support\DocumentLabel::forDisplay($admin->first_name ?? 'NA') ?> on <?= date('d/m/Y H:i', strtotime($fetch->created_at)) ?>" style="display: flex; align-items: center; gap: 8px;">
                                                                <span style="flex: 1;"><?= \App\Support\DocumentLabel::forDisplay($fetch->checklist) ?></span>
                                                                <div class="checklist-actions" style="display: flex; gap: 5px;">
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
                                                                <div data-id="<?= $fetch->id ?>" data-name="<?= \App\Support\DocumentLabel::forDisplay($fetch->file_name) ?>" data-uploaded-at="<?= date('d/m/Y H:i', strtotime($fetch->created_at)) ?>" class="doc-row" title="Uploaded by: <?= \App\Support\DocumentLabel::forDisplay($admin->first_name ?? 'NA') ?> on <?= date('d/m/Y H:i', strtotime($fetch->created_at)) ?>" oncontextmenu='showFileContextMenu(event, <?= (int) $fetch->id ?>, <?= json_encode($fetch->filetype) ?>, <?= json_encode($previewUrl) ?>, <?= json_encode((string) $id) ?>, <?= json_encode($fetch->status ?? 'draft') ?>); return false;'>
                                                                    <a href="javascript:void(0);" onclick='previewFile(<?= json_encode($fetch->filetype) ?>, <?= json_encode($previewUrl) ?>, <?= json_encode('preview-container-' . $id) ?>)'>
                                                                        <i class="fa-solid <?= $fileIcon ?>"></i> <span><?= \App\Support\DocumentLabel::forDisplay($fetch->file_name . '.' . $fetch->filetype) ?></span>
                                                                    </a>
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="upload_document" style="display: inline-block;">
                                                                    <form method="POST" enctype="multipart/form-data" id="upload_form_<?= $fetch->id ?>">
                                                                        @csrf
                                                                        <input type="hidden" name="clientid" value="<?= $clientId ?>">
                                                                        <input type="hidden" name="fileid" value="<?= $fetch->id ?>">
                                                                        <input type="hidden" name="type" value="client">
                                                                        <input type="hidden" name="doctype" value="personal">
                                                                        <input type="hidden" name="doccategory" value="<?= $catVal->title ?>">

                                                                        <!-- Drag and Drop Zone -->
                                                                        <div class="document-drag-drop-zone personal-doc-drag-zone" 
                                                                             data-fileid="<?= $fetch->id ?>" 
                                                                             data-doccategory="<?= $id ?>"
                                                                             data-formid="upload_form_<?= $fetch->id ?>">
                                                                            <div class="drag-zone-inner">
                                                                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                                                                <span class="drag-zone-text">Drag file here or <strong>click to browse</strong></span>
                                                                            </div>
                                                                        </div>
                                                                        
                                                                        <!-- Keep existing file input (hidden, used as fallback) -->
                                                                        <input class="docupload d-none" 
                                                                               data-fileid="<?= $fetch->id ?>" 
                                                                               data-doccategory="<?= $id ?>" 
                                                                               type="file" 
                                                                               name="document_upload"
                                                                               accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.csv,.mp3,.mp4,.webm,.mov,.m4v,.avi,.mkv,.vob,audio/mpeg,audio/mp3,video/mp4,video/webm,video/quicktime,video/mpeg,video/*"
                                                                               style="display: none;"/>
                                                                    </form>
                                                                </div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <!-- Hidden elements for context menu actions -->
                                                            <?php if ($fetch->myfile): ?>
                                                                <a class="renamechecklist" data-id="<?= $fetch->id ?>" href="javascript:;" style="display: none;"></a>
                                                                <a class="renamedoc" data-id="<?= $fetch->id ?>" href="javascript:;" style="display: none;"></a>
                                                                <a class="download-file" data-filelink="" data-document-id="<?= $fetch->id ?>" data-filename="<?= \App\Support\DocumentLabel::forDisplay($fetch->myfile_key ?: basename(parse_url($fetch->myfile, PHP_URL_PATH) ?: $fetch->myfile)) ?>" data-id="<?= $fetch->id ?>" href="#" style="display: none;"></a>
                                                                <a class="notuseddoc" data-id="<?= $fetch->id ?>" data-doctype="personal" data-doccategory="<?= $catVal->title ?>" data-href="documents/not-used" href="javascript:;" style="display: none;"></a>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                        </div>
                                    </div>

                                    <div class="grid_data griddata_<?= $id ?>" style="display: none;">
                                        <?php foreach ($documents as $fetch): ?>
                                            <?php if ($fetch->myfile): ?>
                                                <?php
                                                $previewUrlGrid = url('/documents/preview/' . $fetch->id);
                                                $dlFilenameGrid = $fetch->myfile_key ?: basename(parse_url((string) $fetch->myfile, PHP_URL_PATH) ?: (string) $fetch->myfile);
                                                $gridExt = strtolower((string) ($fetch->filetype ?? ''));
                                                if (in_array($gridExt, ['mp4', 'webm', 'mov', 'm4v', 'avi', 'mkv', 'vob'], true)) {
                                                    $gridFileIcon = 'fa-file-video';
                                                } elseif (in_array($gridExt, ['xls', 'xlsx', 'csv', 'ods'], true)) {
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
                                                                    <a href="javascript:void(0);" class="dropdown-item" onclick='previewFile(<?= json_encode($fetch->filetype ?? 'pdf') ?>, <?= json_encode($previewUrlGrid) ?>, <?= json_encode('preview-container-' . $id) ?>)'>Preview</a>
                                                                    <a href="#" class="dropdown-item download-file" data-document-id="<?= $fetch->id ?>" data-filename="<?= \App\Support\DocumentLabel::forDisplay($dlFilenameGrid) ?>">Download</a>
                                                                    <a data-id="<?= $fetch->id ?>" class="dropdown-item notuseddoc" data-doctype="personal" data-doccategory="<?= $catVal->title ?>" data-href="notuseddoc" href="javascript:;">Not Used</a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                        <div class="clearfix"></div>
                                    </div>

                                    <div class="preview-pane file-preview-container preview-container-<?= $id ?> client-doc-preview-pane personal-preview-pane">
                                        <p class="preview-placeholder-text">Click on a file to preview it here.</p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Custom Context Menu -->
            <div id="fileContextMenu" class="context-menu personal-docs-context-menu" style="display: none; position: fixed; z-index: 10000; min-width: 180px;">
                <div class="context-menu-item" onclick="handleContextAction('rename-checklist')" style="padding: 8px 12px; cursor: pointer;">
                    <i class="fa-solid fa-pen-to-square" style="margin-right: 8px;"></i> Rename Checklist
                </div>
                <div class="context-menu-item" onclick="handleContextAction('rename-doc')" style="padding: 8px 12px; cursor: pointer;">
                    <i class="fa-solid fa-file-lines" style="margin-right: 8px;"></i> Rename File Name
                </div>
                <div class="context-menu-item" onclick="handleContextAction('move')" style="padding: 8px 12px; cursor: pointer;">
                    <i class="fa-solid fa-up-down-left-right" style="margin-right: 8px;"></i> Move Document
                </div>
                <div class="context-menu-item" onclick="handleContextAction('preview')" style="padding: 8px 12px; cursor: pointer;">
                    <i class="fa-solid fa-eye" style="margin-right: 8px;"></i> Preview
                </div>
                <div id="context-pdf-option" class="context-menu-item" onclick="handleContextAction('pdf')" style="padding: 8px 12px; cursor: pointer; display: none;">
                    <i class="fa-solid fa-file-pdf" style="margin-right: 8px;"></i> PDF
                </div>
                <div class="context-menu-item" onclick="handleContextAction('download')" style="padding: 8px 12px; cursor: pointer;">
                    <i class="fa-solid fa-download" style="margin-right: 8px;"></i> Download
                </div>
                <div class="context-menu-item" onclick="handleContextAction('not-used')" style="padding: 8px 12px; cursor: pointer;">
                    <i class="fa-solid fa-trash" style="margin-right: 8px;"></i> Not Used
                </div>
            </div>

            <!-- Move Document Modal -->
            <div class="modal fade" id="moveDocumentModal" tabindex="-1" role="dialog" aria-labelledby="moveDocumentModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="moveDocumentModalLabel">Move Document</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="form-group">
                                <label>Move to:</label>
                                <select id="moveTargetType" class="form-control" style="margin-bottom: 15px;">
                                    <option value="">-- Select Destination --</option>
                                    <option value="personal">Personal Documents</option>
                                    <option value="matter">Matter Documents</option>
                                </select>
                            </div>
                            
                            <!-- For Personal Documents: show folders -->
                            <div class="form-group" id="movePersonalCategoryContainer" style="display: none;">
                                <label>Select Personal Folder:</label>
                                <select id="movePersonalCategoryId" class="form-control">
                                    <option value="">-- Select Folder --</option>
                                </select>
                            </div>
                            
                            <!-- For matter documents: show matters first, then folders -->
                            <div class="form-group" id="moveVisaMatterContainer" style="display: none;">
                                <label>Select matter:</label>
                                <select id="moveVisaMatterId" class="form-control" style="margin-bottom: 15px;">
                                    <option value="">-- Select Matter --</option>
                                </select>
                            </div>
                            
                            <div class="form-group" id="moveVisaCategoryContainer" style="display: none;">
                                <label>Select matter document folder:</label>
                                <select id="moveVisaCategoryId" class="form-control">
                                    <option value="">-- Select Folder --</option>
                                </select>
                            </div>
                            
                            <div id="moveDocumentError" class="alert alert-danger" style="display: none; margin-top: 10px;"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" id="confirmMoveDocument">Move Document</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Video Upload Folder Selection Modal -->
            <div class="modal fade" id="videoUploadFolderModal" tabindex="-1" role="dialog" aria-labelledby="videoUploadFolderModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="videoUploadFolderModalLabel">Select Folder for Video</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted" style="margin-bottom: 12px;">Choose which personal document folder this video should be saved in.</p>
                            <div class="form-group">
                                <label for="videoUploadFolderSelect">Personal Document Folder</label>
                                <select id="videoUploadFolderSelect" class="form-control">
                                    <option value="">-- Select Folder --</option>
                                </select>
                            </div>
                            <div id="videoUploadFolderError" class="alert alert-danger" style="display: none; margin-top: 10px;"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="cancelVideoUploadFolder">Cancel</button>
                            <button type="button" class="btn btn-primary" id="confirmVideoUploadFolder">Continue Upload</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Video Upload Progress Overlay -->
            <div id="personalVideoUploadOverlay" class="personal-video-upload-overlay" aria-hidden="true">
                <div class="personal-video-upload-panel" role="dialog" aria-labelledby="pvuTitle" aria-live="polite">
                    <div class="pvu-icon-wrap">
                        <i class="fa-solid fa-file-video pvu-main-icon"></i>
                        <span class="pvu-spinner-ring"></span>
                    </div>
                    <h4 id="pvuTitle" class="pvu-title">Uploading Video</h4>
                    <p class="pvu-filename" id="pvuFilename">video.mp4</p>
                    <p class="pvu-meta" id="pvuMeta"></p>
                    <p class="pvu-status" id="pvuStatusMessage">Preparing upload…</p>
                    <div class="pvu-progress-wrap">
                        <div class="pvu-progress-track">
                            <div class="pvu-progress-bar" id="pvuProgressBar"></div>
                        </div>
                        <span class="pvu-percent" id="pvuPercent">0%</span>
                    </div>
                    <ol class="pvu-timeline">
                        <li class="pvu-step" data-step="upload">
                            <span class="pvu-step-marker"><i class="fa-solid fa-cloud-arrow-up"></i></span>
                            <span class="pvu-step-label">Upload</span>
                        </li>
                        <li class="pvu-step" data-step="queued">
                            <span class="pvu-step-marker"><i class="fa-solid fa-layer-group"></i></span>
                            <span class="pvu-step-label">Queued</span>
                        </li>
                        <li class="pvu-step" data-step="processing">
                            <span class="pvu-step-marker"><i class="fa-solid fa-gear"></i></span>
                            <span class="pvu-step-label">Processing</span>
                        </li>
                        <li class="pvu-step" data-step="complete">
                            <span class="pvu-step-marker"><i class="fa-solid fa-check"></i></span>
                            <span class="pvu-step-label">Done</span>
                        </li>
                    </ol>
                </div>
            </div>

            <script>
                // ============================================================================
                // PERSONAL DOCUMENTS - DRAG AND DROP INITIALIZATION (HYBRID APPROACH)
                // ============================================================================
                // This uses a DUAL-LAYER strategy to ensure handlers work:
                // 1. DIRECT handlers on existing elements (fire FIRST, highest priority)
                // 2. DELEGATED handlers for dynamic elements (fallback)
                // Both use stopImmediatePropagation() to prevent detail-main.js handlers
                // from interfering, while keeping them as a safety fallback.
                // ============================================================================
                
                function initPersonalDocDragDrop() {
                    
                    // Check each drop zone
                    $('.personal-doc-drag-zone').each(function(index) {
                        var $zone = $(this);
                        var fileid = $zone.data('fileid');
                        var formid = $zone.data('formid');
                        var isVisible = $zone.is(':visible');
                    });
                    
                    // Remove only our own handlers to prevent duplicates
                    $(document).off('dragenter.personaldoclocal', '.personal-doc-drag-zone');
                    $(document).off('dragover.personaldoclocal', '.personal-doc-drag-zone');
                    $(document).off('dragleave.personaldoclocal', '.personal-doc-drag-zone');
                    $(document).off('drop.personaldoclocal', '.personal-doc-drag-zone');
                    $(document).off('click.personaldoclocal', '.personal-doc-drag-zone');
                    
                    // ALSO attach direct handlers to existing elements for IMMEDIATE priority
                    // These will fire BEFORE delegated handlers
                    $('.personal-doc-drag-zone').each(function() {
                        var $zone = $(this);
                        
                        // Remove any existing direct handlers first
                        $zone.off('click.personaldocdirect');
                        $zone.off('dragenter.personaldocdirect');
                        $zone.off('dragover.personaldocdirect');
                        $zone.off('dragleave.personaldocdirect');
                        $zone.off('drop.personaldocdirect');
                        
                        // Attach direct handlers with stopImmediatePropagation
                        $zone.on('click.personaldocdirect', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            e.stopImmediatePropagation();
                            
                            var fileid = $(this).data('fileid');
                            var formid = $(this).data('formid');
                            var fileInput = $('#' + formid).find('.docupload');
                            
                            if (fileInput.length > 0) {
                                fileInput[0].click();
                            }
                            return false;
                        });
                        
                        $zone.on('dragenter.personaldocdirect', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            e.stopImmediatePropagation();
                            $(this).addClass('drag_over');
                            return false;
                        });
                        
                        $zone.on('dragover.personaldocdirect', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            e.stopImmediatePropagation();
                            
                            if (e.originalEvent && e.originalEvent.dataTransfer) {
                                e.originalEvent.dataTransfer.dropEffect = 'copy';
                            }
                            
                            $(this).addClass('drag_over');
                            return false;
                        });
                        
                        $zone.on('dragleave.personaldocdirect', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            e.stopImmediatePropagation();
                            
                            var rect = this.getBoundingClientRect();
                            var x = e.originalEvent.clientX;
                            var y = e.originalEvent.clientY;
                            
                            if (x <= rect.left || x >= rect.right || y <= rect.top || y >= rect.bottom) {
                                $(this).removeClass('drag_over');
                            }
                            return false;
                        });
                        
                        $zone.on('drop.personaldocdirect', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            e.stopImmediatePropagation();
                            
                            $(this).removeClass('drag_over');
                            
                            var files = e.originalEvent && e.originalEvent.dataTransfer ? e.originalEvent.dataTransfer.files : null;
                            if (files && files.length > 0) {
                                var $zone = $(this);
                                var file = files[0];
                                var fileid = $zone.data('fileid');
                                var doccategory = $zone.data('doccategory');
                                var formId = $zone.data('formid');
                                var form = $('#' + formId);
                                
                                if (!form.length) {
                                    console.error('❌ Form not found:', formId);
                                    alert('Error: Upload form not found. Please refresh the page.');
                                    return false;
                                }
                                
                                // Validate filename
                                if (!file.name || /[\/\\]/.test(file.name)) {
                                    alert("File name cannot contain slashes. Please rename the file and try again.");
                                    return false;
                                }

                                if (typeof uploadPersonalDocFromZone === 'function') {
                                    uploadPersonalDocFromZone($zone, file);
                                }
                            }
                            return false;
                        });
                    });
                    
                    // Use DELEGATED event handlers with HIGH PRIORITY (these work for dynamically loaded content)
                    // The .personaldoclocal namespace ensures we can remove/re-attach without affecting detail-main.js
                    
                    // Click handler - for browse functionality
                    $(document).on('click.personaldoclocal', '.personal-doc-drag-zone', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation(); // Stop detail-main.js handler

                        var fileid = $(this).data('fileid');
                        var formid = $(this).data('formid');

                        var $form = $('#' + formid);
                        if (!$form.length) {
                            console.error('❌ Form not found:', formid);
                            alert('Error: Upload form not found. Please refresh the page.');
                            return false;
                        }

                        var fileInput = $form.find('.docupload');

                        if (fileInput.length > 0) {
                            // Use native click to ensure it works
                            var nativeInput = fileInput[0];
                            if (nativeInput && typeof nativeInput.click === 'function') {
                                nativeInput.click();
                            } else {
                                console.error('❌ File input element not accessible');
                                alert('Error: File input not accessible. Please refresh the page.');
                            }
                        } else {
                            console.error('❌ File input not found for formid:', formid);
                            alert('Error: File input not found. Please refresh the page.');
                        }

                        return false;
                    });
                    
                    // Dragenter - initial entry into zone
                    $(document).on('dragenter.personaldoclocal', '.personal-doc-drag-zone', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation(); // Stop detail-main.js handler
                        $(this).addClass('drag_over');
                        return false;
                    });
                    
                    // Dragover - continuous while dragging over zone (REQUIRED for drop to work!)
                    $(document).on('dragover.personaldoclocal', '.personal-doc-drag-zone', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation(); // Stop detail-main.js handler
                        
                        // Set dropEffect to indicate this is a valid drop zone
                        if (e.originalEvent && e.originalEvent.dataTransfer) {
                            e.originalEvent.dataTransfer.dropEffect = 'copy';
                        }
                        
                        $(this).addClass('drag_over');
                        return false;
                    });
                    
                    // Dragleave - when dragging out of zone
                    $(document).on('dragleave.personaldoclocal', '.personal-doc-drag-zone', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation(); // Stop detail-main.js handler
                        
                        // Only remove highlight if actually leaving the zone
                        var rect = this.getBoundingClientRect();
                        var x = e.originalEvent.clientX;
                        var y = e.originalEvent.clientY;
                        
                        if (x <= rect.left || x >= rect.right || y <= rect.top || y >= rect.bottom) {
                            $(this).removeClass('drag_over');
                        }
                        return false;
                    });
                    
                    // Drop - when file is dropped
                    $(document).on('drop.personaldoclocal', '.personal-doc-drag-zone', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation(); // Stop detail-main.js handler from firing
                        
                        $(this).removeClass('drag_over');
                        
                        var files = e.originalEvent && e.originalEvent.dataTransfer ? e.originalEvent.dataTransfer.files : null;
                        if (files && files.length > 0) {
                            
                            var $zone = $(this);
                            var file = files[0];
                            var fileid = $zone.data('fileid');
                            var doccategory = $zone.data('doccategory');
                            var formId = $zone.data('formid');
                            var form = $('#' + formId);
                            
                            
                            if (!form.length) {
                                console.error('❌ Form not found:', formId);
                                alert('Error: Upload form not found. Please refresh the page.');
                                return false;
                            }
                            
                            // Validate filename
                            if (!file.name || /[\/\\]/.test(file.name)) {
                                alert("File name cannot contain slashes. Please rename the file and try again.");
                                return false;
                            }

                            if (typeof uploadPersonalDocFromZone === 'function') {
                                uploadPersonalDocFromZone($zone, file);
                            }
                        } else {
                            console.error('❌ No files in drop event');
                        }
                        return false;
                    });
                    
                }
                
                // CRITICAL: Initialize IMMEDIATELY (before detail-main.js loads)
                // This ensures our handlers are attached first and can use stopImmediatePropagation()
                initPersonalDocDragDrop();
                
                // Also initialize on DOM ready (in case elements weren't ready above)
                $(document).ready(function() {
                    initPersonalDocDragDrop();
                });
                
                // Re-initialize when Personal Documents tab is shown
                // Listen for tab clicks using the sidebar-tabs.js system
                $(document).on('click', '.client-nav-button[data-tab="personaldocuments"]', function() {
                    setTimeout(function() {
                        initPersonalDocDragDrop();
                        if (typeof scheduleClientDocumentsPanelHeightAdjust === 'function') {
                            scheduleClientDocumentsPanelHeightAdjust();
                        } else if (typeof adjustClientDocumentsPanelHeight === 'function') {
                            adjustClientDocumentsPanelHeight();
                        }
                    }, 200);
                });
                
                if ($('#personaldocuments-tab').hasClass('active')) {
                    setTimeout(function() {
                        initPersonalDocDragDrop();
                        if (typeof scheduleClientDocumentsPanelHeightAdjust === 'function') {
                            scheduleClientDocumentsPanelHeightAdjust();
                        } else if (typeof adjustClientDocumentsPanelHeight === 'function') {
                            adjustClientDocumentsPanelHeight();
                        }
                    }, 500);
                }
                
                let currentContextFile = null;
                let currentContextData = {};

                function showFileContextMenu(event, fileId, fileType, fileUrl, categoryId, fileStatus) {
                    event.preventDefault();
                    event.stopPropagation();
                    
                    currentContextFile = fileId;
                    currentContextData = {
                        fileId: fileId,
                        fileType: fileType,
                        fileUrl: fileUrl,
                        categoryId: categoryId,
                        fileStatus: fileStatus
                    };

                    const menu = document.getElementById('fileContextMenu');
                    
                    // Show/hide PDF option based on file type
                    const pdfOption = document.getElementById('context-pdf-option');
                    const fileExt = fileType.toLowerCase();
                    if (['jpg', 'png', 'jpeg'].includes(fileExt)) {
                        pdfOption.style.display = 'block';
                    } else {
                        pdfOption.style.display = 'none';
                    }

                    // Measure actual menu dimensions dynamically
                    menu.style.visibility = 'hidden';
                    menu.style.display = 'block';
                    const menuWidth = menu.offsetWidth;
                    const menuHeight = menu.offsetHeight;
                    menu.style.display = 'none';
                    menu.style.visibility = 'visible';

                    // Position menu at cursor (position: fixed uses viewport coordinates)
                    const MIN_PADDING = 5;
                    const CURSOR_OFFSET = 2;
                    const viewportWidth = window.innerWidth;
                    const viewportHeight = window.innerHeight;
                    const cursorX = event.clientX;
                    const cursorY = event.clientY;

                    let menuLeft = cursorX + CURSOR_OFFSET;
                    let menuTop = cursorY + CURSOR_OFFSET;

                    // Check right edge - show to the left of cursor if needed
                    if (menuLeft + menuWidth > viewportWidth - MIN_PADDING) {
                        menuLeft = cursorX - menuWidth - CURSOR_OFFSET;
                    }
                    // Check bottom edge - show above cursor if needed
                    if (menuTop + menuHeight > viewportHeight - MIN_PADDING) {
                        menuTop = cursorY - menuHeight - CURSOR_OFFSET;
                    }
                    // Keep inside viewport (left/top edges)
                    menuLeft = Math.max(MIN_PADDING, Math.min(menuLeft, viewportWidth - menuWidth - MIN_PADDING));
                    menuTop = Math.max(MIN_PADDING, Math.min(menuTop, viewportHeight - menuHeight - MIN_PADDING));

                    menu.style.left = menuLeft + 'px';
                    menu.style.top = menuTop + 'px';
                    menu.style.display = 'block';

                    // Hide menu when clicking elsewhere
                    setTimeout(() => {
                        document.addEventListener('click', hideContextMenu);
                    }, 100);
                }

                function hideContextMenu() {
                    const menu = document.getElementById('fileContextMenu');
                    menu.style.display = 'none';
                    document.removeEventListener('click', hideContextMenu);
                }

                function handleContextAction(action) {
                    if (!currentContextFile) return;

                    hideContextMenu();

                    switch(action) {
                        case 'rename-checklist':
                            $('.renamechecklist[data-id="' + currentContextFile + '"]').click();
                            break;
                        case 'rename-doc':
                            $('.renamedoc[data-id="' + currentContextFile + '"]').click();
                            break;
                        case 'move':
                            openMoveDocumentModal(currentContextFile, 'personal');
                            break;
                        case 'preview':
                            window.open(currentContextData.fileUrl, '_blank');
                            break;
                        case 'pdf':
                            const pdfUrl = '{{ URL::to('/document/download/pdf') }}/' + currentContextFile;
                            window.open(pdfUrl, '_blank');
                            break;
                        case 'download':
                            // Prefer document ID (private S3); fallback to legacy filelink match
                            let $downloadBtn = $('.download-file[data-id="' + currentContextFile + '"]');
                            if ($downloadBtn.length === 0) {
                                $downloadBtn = $('.download-file[data-filelink="' + currentContextData.fileUrl + '"]');
                            }
                            if ($downloadBtn.length > 0) {
                                $downloadBtn.first().click();
                            } else {
                                console.error('Download button not found for file ID:', currentContextFile);
                                alert('Download link not found. Please refresh the page and try again.');
                            }
                            break;
                        case 'not-used':
                            $('.notuseddoc[data-id="' + currentContextFile + '"]').click();
                            break;
                    }
                }

                // ============================================================================
                // MOVE DOCUMENT FUNCTIONALITY
                // ============================================================================
                let currentMoveDocumentId = null;
                let currentMoveDocumentType = null;

                function appendPersonalDocModalToBody(selector) {
                    const $modal = $(selector);
                    if ($modal.length && !$modal.parent().is('body')) {
                        $modal.appendTo('body');
                    }
                }

                // Bootstrap modals must be direct children of body; tab panes use overflow:hidden.
                appendPersonalDocModalToBody('#moveDocumentModal');
                appendPersonalDocModalToBody('#videoUploadFolderModal');

                function openMoveDocumentModal(documentId, currentType) {
                    currentMoveDocumentId = documentId;
                    currentMoveDocumentType = currentType;

                    appendPersonalDocModalToBody('#moveDocumentModal');
                    
                    // Reset modal
                    $('#moveTargetType').val('');
                    $('#movePersonalCategoryContainer').hide();
                    $('#moveVisaMatterContainer').hide();
                    $('#moveVisaCategoryContainer').hide();
                    $('#movePersonalCategoryId').empty().append('<option value="">-- Select Folder --</option>');
                    $('#moveVisaMatterId').empty().append('<option value="">-- Select Matter --</option>');
                    $('#moveVisaCategoryId').empty().append('<option value="">-- Select Folder --</option>');
                    $('#moveDocumentError').hide();
                    
                    // Show modal
                    $('#moveDocumentModal').modal('show');
                }

                // Handle target type change
                $(document).on('change', '#moveTargetType', function() {
                    const targetType = $(this).val();
                    
                    // Hide all containers first
                    $('#movePersonalCategoryContainer').hide();
                    $('#moveVisaMatterContainer').hide();
                    $('#moveVisaCategoryContainer').hide();
                    $('#moveDocumentError').hide();
                    
                    if (!targetType) {
                        return;
                    }
                    
                    if (targetType === 'personal') {
                        // Load personal document categories
                        const categories = [];
                        $('.subtab2-button').each(function() {
                            const catId = $(this).data('subtab2');
                            const catTitle = $(this).text().trim();
                            if (catId && catTitle) {
                                categories.push({ id: catId, title: catTitle });
                            }
                        });
                        
                        $('#movePersonalCategoryId').empty().append('<option value="">-- Select Folder --</option>');
                        categories.forEach(cat => {
                            $('#movePersonalCategoryId').append(`<option value="${cat.id}">${cat.title}</option>`);
                        });
                        $('#movePersonalCategoryContainer').show();
                        
                    } else if (targetType === 'matter') {
                        // Load visa matters first
                        const clientId = '<?= $clientId ?? "" ?>';
                        if (!clientId) {
                            $('#moveDocumentError').text('Error: Client ID not found').show();
                            return;
                        }
                        
                        $('#moveVisaMatterId').empty().append('<option value="">Loading...</option>');
                        $('#moveVisaMatterContainer').show();
                        
                        // Fetch matters via AJAX
                        $.ajax({
                            url: '{{ URL::to('/get-client-matters') }}/' + clientId,
                            type: 'GET',
                            success: function(response) {
                                $('#moveVisaMatterId').empty().append('<option value="">-- Select Matter --</option>');
                                const matters = response && response.matters ? response.matters : [];
                                if (matters.length > 0) {
                                    matters.forEach(matter => {
                                        const matterLabel = matter.client_unique_matter_no || ('Matter #' + matter.id);
                                        $('#moveVisaMatterId').append(`<option value="${matter.id}">${matterLabel}</option>`);
                                    });
                                } else {
                                    $('#moveVisaMatterId').append('<option value="">No matters found</option>');
                                }
                            },
                            error: function() {
                                $('#moveVisaMatterId').empty().append('<option value="">Error loading matters</option>');
                            }
                        });
                    }
                });

                // Handle visa matter selection - load categories for that matter
                $(document).on('change', '#moveVisaMatterId', function() {
                    const matterId = $(this).val();
                    $('#moveVisaCategoryContainer').hide();
                    $('#moveDocumentError').hide();
                    
                    if (!matterId) {
                        return;
                    }
                    
                    const clientId = '<?= $clientId ?? "" ?>';
                    $('#moveVisaCategoryId').empty().append('<option value="">Loading...</option>');
                    $('#moveVisaCategoryContainer').show();
                    
                    // Fetch visa categories for this matter via AJAX
                    $.ajax({
                        url: '{{ URL::to('/get-visa-categories') }}',
                        type: 'GET',
                        data: {
                            client_id: clientId,
                            matter_id: matterId
                        },
                        success: function(response) {
                            $('#moveVisaCategoryId').empty().append('<option value="">-- Select Folder --</option>');
                            const categories = Array.isArray(response) ? response : [];
                            if (categories.length > 0) {
                                categories.forEach(category => {
                                    $('#moveVisaCategoryId').append(`<option value="${category.id}">${category.title}</option>`);
                                });
                            } else {
                                $('#moveVisaCategoryId').append('<option value="">No folders found</option>');
                            }
                        },
                        error: function() {
                            $('#moveVisaCategoryId').empty().append('<option value="">Error loading folders</option>');
                        }
                    });
                });

                // Handle move document confirmation
                $(document).on('click', '#confirmMoveDocument', function() {
                    const targetType = $('#moveTargetType').val();
                    let targetId = null;
                    const $error = $('#moveDocumentError');
                    const $btn = $(this);
                    
                    // Validate based on target type
                    if (!targetType) {
                        $error.text('Please select a destination type').show();
                        return;
                    }
                    
                    if (targetType === 'personal') {
                        targetId = $('#movePersonalCategoryId').val();
                        if (!targetId) {
                            $error.text('Please select a personal folder').show();
                            return;
                        }
                    } else if (targetType === 'matter') {
                        targetId = $('#moveVisaCategoryId').val();
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
                            document_id: currentMoveDocumentId,
                            target_type: targetType,
                            target_id: targetId
                        },
                        success: function(response) {
                            if (response.status) {
                                // Close modal
                                $('#moveDocumentModal').modal('hide');
                                
                                // Show success message using alert
                                alert(response.message || 'Document moved successfully');
                                
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
                $('#moveDocumentModal').on('hidden.bs.modal', function() {
                    $('#confirmMoveDocument').prop('disabled', false).text('Move Document');
                });

                // Hide context menu on escape key
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        hideContextMenu();
                    }
                });
            </script>

            <style>
                /* Personal documents — colours from docs/theme.md (CSS variables in crm-theme.css) */
                .personal-docs-context-menu .context-menu-item:hover {
                    background-color: var(--sidebar-bg, #ddeaf8);
                }

                .document-drag-drop-zone {
                    border: 2px dashed var(--border, #c8dcef);
                    border-radius: 8px;
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
                
                .document-drag-drop-zone * {
                    pointer-events: none;
                }

                .document-drag-drop-zone:hover {
                    border-color: var(--sidebar-active, #3a6fa8);
                    background-color: rgba(221, 234, 248, 0.6);
                }

                .document-drag-drop-zone.drag_over {
                    border-color: var(--success, #1e7a52);
                    background-color: rgba(30, 122, 82, 0.1);
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

                .bulk-upload-dropzone {
                    position: relative;
                }
                
                .bulk-upload-dropzone * {
                    pointer-events: none;
                }
                
                .bulk-upload-dropzone.drag_over {
                    border-color: var(--success, #1e7a52);
                    background-color: rgba(30, 122, 82, 0.08);
                }

                #bulk-upload-mapping-table table tbody tr {
                    border-bottom: 1px solid var(--border, #c8dcef);
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
                    font-weight: 600;
                    color: var(--text-dark, #1a2c40);
                    word-break: break-word;
                    overflow-wrap: break-word;
                    white-space: normal;
                    line-height: 1.4;
                    display: block;
                }

                .bulk-upload-file-item .file-size {
                    font-size: 12px;
                    color: var(--text-muted, #5e7a90);
                }

                .bulk-upload-file-item .checklist-select {
                    min-width: 200px;
                }

                .bulk-upload-file-item .match-status {
                    font-size: 12px;
                    padding: 2px 8px;
                    border-radius: 4px;
                }

                .match-status.auto-matched {
                    background-color: rgba(30, 122, 82, 0.12);
                    color: var(--success, #1e7a52);
                    border: 1px solid rgba(30, 122, 82, 0.28);
                }

                .match-status.manual {
                    background-color: rgba(200, 153, 42, 0.15);
                    color: #7a5800;
                    border: 1px solid rgba(200, 153, 42, 0.35);
                }

                .match-status.new-checklist {
                    background-color: rgba(30, 61, 96, 0.1);
                    color: var(--navy, #1e3d60);
                    border: 1px solid rgba(58, 111, 168, 0.25);
                }

                .remove-bulk-file {
                    padding: 4px 8px;
                    font-size: 14px;
                    transition: all 0.2s ease;
                }

                .remove-bulk-file:hover {
                    background-color: var(--danger, #a83020);
                    border-color: var(--danger, #a83020);
                    transform: scale(1.05);
                }

                .remove-bulk-file i {
                    pointer-events: none;
                }

                /* Video upload progress overlay */
                .personal-video-upload-overlay {
                    display: none;
                    position: fixed;
                    inset: 0;
                    z-index: 10050;
                    background: rgba(15, 32, 52, 0.55);
                    backdrop-filter: blur(4px);
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }

                .personal-video-upload-overlay.is-visible {
                    display: flex;
                }

                .personal-video-upload-panel {
                    width: 100%;
                    max-width: 440px;
                    background: #fff;
                    border-radius: 14px;
                    box-shadow: 0 20px 50px rgba(15, 32, 52, 0.25);
                    padding: 28px 26px 24px;
                    text-align: center;
                    animation: pvuPanelIn 0.35s ease;
                }

                @keyframes pvuPanelIn {
                    from { opacity: 0; transform: translateY(12px) scale(0.98); }
                    to { opacity: 1; transform: translateY(0) scale(1); }
                }

                .pvu-icon-wrap {
                    position: relative;
                    width: 64px;
                    height: 64px;
                    margin: 0 auto 14px;
                }

                .pvu-main-icon {
                    font-size: 28px;
                    color: var(--sidebar-active, #3a6fa8);
                    line-height: 64px;
                }

                .pvu-spinner-ring {
                    position: absolute;
                    inset: 0;
                    border: 3px solid rgba(58, 111, 168, 0.15);
                    border-top-color: var(--sidebar-active, #3a6fa8);
                    border-radius: 50%;
                    animation: pvuSpin 1s linear infinite;
                }

                @keyframes pvuSpin {
                    to { transform: rotate(360deg); }
                }

                .pvu-title {
                    margin: 0 0 6px;
                    font-size: 1.15rem;
                    font-weight: 600;
                    color: var(--navy, #1e3d60);
                }

                .pvu-filename {
                    margin: 0 0 4px;
                    font-size: 14px;
                    font-weight: 600;
                    color: var(--text-dark, #1a2c40);
                    word-break: break-word;
                }

                .pvu-meta {
                    margin: 0 0 12px;
                    font-size: 12px;
                    color: var(--text-muted, #5e7a90);
                }

                .pvu-status {
                    margin: 0 0 16px;
                    font-size: 13px;
                    color: var(--text-muted, #5e7a90);
                    min-height: 20px;
                }

                .pvu-progress-wrap {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    margin-bottom: 22px;
                }

                .pvu-progress-track {
                    flex: 1;
                    height: 10px;
                    background: var(--page-bg, #eef4fb);
                    border-radius: 999px;
                    overflow: hidden;
                    border: 1px solid var(--border, #c8dcef);
                }

                .pvu-progress-bar {
                    height: 100%;
                    width: 0%;
                    border-radius: 999px;
                    background: linear-gradient(90deg, #3a6fa8 0%, #5a9fd4 100%);
                    transition: width 0.35s ease;
                }

                .pvu-percent {
                    font-size: 13px;
                    font-weight: 700;
                    color: var(--sidebar-active, #3a6fa8);
                    min-width: 38px;
                    text-align: right;
                }

                .pvu-timeline {
                    list-style: none;
                    margin: 0;
                    padding: 0;
                    display: flex;
                    justify-content: space-between;
                    position: relative;
                }

                .pvu-timeline::before {
                    content: '';
                    position: absolute;
                    top: 18px;
                    left: 12%;
                    right: 12%;
                    height: 3px;
                    background: var(--border, #c8dcef);
                    z-index: 0;
                }

                .pvu-step {
                    position: relative;
                    z-index: 1;
                    flex: 1;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    gap: 8px;
                }

                .pvu-step-marker {
                    width: 36px;
                    height: 36px;
                    border-radius: 50%;
                    background: #fff;
                    border: 2px solid var(--border, #c8dcef);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: var(--text-muted, #5e7a90);
                    font-size: 14px;
                    transition: all 0.3s ease;
                }

                .pvu-step-label {
                    font-size: 11px;
                    font-weight: 600;
                    color: var(--text-muted, #5e7a90);
                    text-transform: uppercase;
                    letter-spacing: 0.03em;
                }

                .pvu-step.active .pvu-step-marker {
                    border-color: var(--sidebar-active, #3a6fa8);
                    background: rgba(58, 111, 168, 0.1);
                    color: var(--sidebar-active, #3a6fa8);
                    box-shadow: 0 0 0 4px rgba(58, 111, 168, 0.12);
                }

                .pvu-step.active .pvu-step-label {
                    color: var(--sidebar-active, #3a6fa8);
                }

                .pvu-step.done .pvu-step-marker {
                    border-color: var(--success, #1e7a52);
                    background: var(--success, #1e7a52);
                    color: #fff;
                }

                .pvu-step.done .pvu-step-label {
                    color: var(--success, #1e7a52);
                }

                .pvu-step.error .pvu-step-marker {
                    border-color: #dc3545;
                    background: #dc3545;
                    color: #fff;
                }

                .pvu-step.error .pvu-step-label {
                    color: #dc3545;
                }

                .pvu-step.active[data-step="processing"] .pvu-step-marker i {
                    animation: pvuSpin 1.2s linear infinite;
                }

                .personal-video-upload-panel.is-success .pvu-spinner-ring {
                    border-top-color: var(--success, #1e7a52);
                    animation: none;
                    border-color: rgba(30, 122, 82, 0.25);
                }

                .personal-video-upload-panel.is-error .pvu-spinner-ring {
                    display: none;
                }
            </style>

            <script>
                // ============================================================================
                // BULK UPLOAD FUNCTIONALITY
                // ============================================================================
                
                let bulkUploadFiles = {};
                let currentCategoryId = null;
                let currentClientId = @json($clientId ?? null);

                function resetBulkUploadFileInput(categoryId) {
                    const input = document.querySelector(
                        '#bulk-upload-' + categoryId + ' .bulk-upload-file-input[data-categoryid="' + categoryId + '"]'
                    );
                    if (input) {
                        input.value = '';
                    }
                }

                function resetBulkUploadSelection(categoryId) {
                    bulkUploadFiles[categoryId] = [];
                    resetBulkUploadFileInput(categoryId);
                    const container = $('#bulk-upload-' + categoryId);
                    container.find('.bulk-upload-file-list').hide();
                    container.find('.bulk-upload-files-container').empty();
                    container.find('.file-count').text('0');
                }

                // Toggle bulk upload dropzone
                $(document).on('click', '.bulk-upload-toggle-btn', function() {
                    const categoryId = $(this).data('categoryid');
                    const dropzoneContainer = $('#bulk-upload-' + categoryId);
                    
                    // Hide all other dropzones first
                    $('.bulk-upload-dropzone-container').not('#bulk-upload-' + categoryId).slideUp();
                    $('.bulk-upload-toggle-btn').not(this).html('<i class="fa-solid fa-upload"></i> Bulk Upload');
                    
                    if (dropzoneContainer.is(':visible')) {
                        dropzoneContainer.slideUp(200, function() {
                            if (typeof adjustClientDocumentsPanelHeight === 'function') {
                                adjustClientDocumentsPanelHeight();
                            }
                        });
                        $(this).html('<i class="fa-solid fa-upload"></i> Bulk Upload');
                        resetBulkUploadSelection(categoryId);
                        window.hideBulkUploadModal();
                    } else {
                        window.hideBulkUploadModal();
                        dropzoneContainer.slideDown(200, function() {
                            if (typeof adjustClientDocumentsPanelHeight === 'function') {
                                adjustClientDocumentsPanelHeight();
                            }
                        });
                        $(this).html('<i class="fa-solid fa-xmark"></i> Close');
                        currentCategoryId = categoryId;
                    }
                });
                
                // Initialize bulk upload files array for each category
                $('.bulk-upload-dropzone').each(function() {
                    const categoryId = $(this).data('categoryid');
                    if (!bulkUploadFiles[categoryId]) {
                        bulkUploadFiles[categoryId] = [];
                    }
                });
                
                // Click to browse files
                $(document).on('click', '.bulk-upload-dropzone', function(e) {
                    if (!$(e.target).is('input')) {
                        const categoryId = $(this).data('categoryid');
                        $(this).closest('.bulk-upload-dropzone-container').find('.bulk-upload-file-input[data-categoryid="' + categoryId + '"]').click();
                    }
                });
                
                // File input change
                $(document).on('change', '.bulk-upload-file-input', function() {
                    const categoryId = $(this).data('categoryid');
                    const files = this.files;
                    
                    if (files.length > 0) {
                        handleBulkFilesSelected(categoryId, files);
                    }
                });
                
                // Attach DIRECT handlers to bulk upload dropzones for highest priority
                function initBulkUploadDragDrop() {
                    
                    $('.bulk-upload-dropzone').each(function() {
                        var $zone = $(this);
                        
                        // Remove any existing handlers first
                        $zone.off('dragenter.bulkdirect dragover.bulkdirect dragleave.bulkdirect drop.bulkdirect');
                        
                        // Use native event listeners for maximum compatibility
                        var elem = this;
                        
                        // Remove old native listeners if they exist
                        if (elem._bulkDragOver) {
                            elem.removeEventListener('dragover', elem._bulkDragOver);
                        }
                        if (elem._bulkDrop) {
                            elem.removeEventListener('drop', elem._bulkDrop);
                        }
                        if (elem._bulkDragEnter) {
                            elem.removeEventListener('dragenter', elem._bulkDragEnter);
                        }
                        if (elem._bulkDragLeave) {
                            elem.removeEventListener('dragleave', elem._bulkDragLeave);
                        }
                        
                        // Dragover handler (REQUIRED for drop to work)
                        elem._bulkDragOver = function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            e.dataTransfer.dropEffect = 'copy';
                            $zone.addClass('drag_over');
                        };
                        elem.addEventListener('dragover', elem._bulkDragOver);
                        
                        // Dragenter handler
                        elem._bulkDragEnter = function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            $zone.addClass('drag_over');
                        };
                        elem.addEventListener('dragenter', elem._bulkDragEnter);
                        
                        // Dragleave handler
                        elem._bulkDragLeave = function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            
                            var rect = elem.getBoundingClientRect();
                            if (e.clientX <= rect.left || e.clientX >= rect.right || 
                                e.clientY <= rect.top || e.clientY >= rect.bottom) {
                                $zone.removeClass('drag_over');
                            }
                        };
                        elem.addEventListener('dragleave', elem._bulkDragLeave);
                        
                        // Drop handler
                        elem._bulkDrop = function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            $zone.removeClass('drag_over');
                            
                            var files = e.dataTransfer ? e.dataTransfer.files : null;
                            
                            if (files && files.length > 0) {
                                var categoryId = $zone.data('categoryid');
                                handleBulkFilesSelected(categoryId, files);
                            } else {
                                console.error('❌ No files in drop event');
                            }
                        };
                        elem.addEventListener('drop', elem._bulkDrop);
                        
                    });
                }
                
                // Initialize bulk upload drag-drop when container becomes visible
                $(document).on('click', '.bulk-upload-toggle-btn', function() {
                    setTimeout(function() {
                        initBulkUploadDragDrop();
                    }, 300); // Wait for slideDown animation
                });
                
                // Also initialize on DOM ready for any visible dropzones
                $(document).ready(function() {
                    initBulkUploadDragDrop();
                });
                
                // Keep delegated handlers as fallback
                $(document).on('dragover', '.bulk-upload-dropzone', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $(this).addClass('drag_over');
                    if (e.originalEvent && e.originalEvent.dataTransfer) {
                        e.originalEvent.dataTransfer.dropEffect = 'copy';
                    }
                    return false;
                });
                
                $(document).on('dragenter', '.bulk-upload-dropzone', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $(this).addClass('drag_over');
                    return false;
                });
                
                $(document).on('dragleave', '.bulk-upload-dropzone', function(e) {
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
                
                $(document).on('drop', '.bulk-upload-dropzone', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $(this).removeClass('drag_over');
                    
                    const categoryId = $(this).data('categoryid');
                    const files = e.originalEvent && e.originalEvent.dataTransfer ? e.originalEvent.dataTransfer.files : null;
                    
                    
                    if (files && files.length > 0) {
                        handleBulkFilesSelected(categoryId, files);
                    } else {
                        console.error('❌ No files in drop event');
                    }
                    return false;
                });
                
                function ensureBulkUploadOpenForCategory(categoryId) {
                    const $container = $('#bulk-upload-' + categoryId);
                    if (!$container.is(':visible')) {
                        $('#personaldocuments-tab .bulk-upload-toggle-btn[data-categoryid="' + categoryId + '"]').trigger('click');
                    }
                    currentCategoryId = categoryId;
                }

                // Handle files selected
                function handleBulkFilesSelected(categoryId, files) {
                    const fileArray = Array.from(files);
                    const hasVideo = fileArray.some(function(file) {
                        return typeof isPersonalDocVideoFile === 'function' && isPersonalDocVideoFile(file);
                    });

                    if (hasVideo && typeof promptPersonalVideoUploadFolder === 'function') {
                        promptPersonalVideoUploadFolder(categoryId, function(selectedCategoryId) {
                            if (String(selectedCategoryId) !== String(categoryId)) {
                                resetBulkUploadSelection(categoryId);
                                ensureBulkUploadOpenForCategory(selectedCategoryId);
                                if (typeof activatePersonalDocumentFolder === 'function') {
                                    activatePersonalDocumentFolder(selectedCategoryId);
                                }
                            }
                            processBulkFilesSelected(selectedCategoryId, fileArray);
                        }, function() {
                            resetBulkUploadFileInput(categoryId);
                        });
                        return;
                    }

                    processBulkFilesSelected(categoryId, fileArray);
                }

                function processBulkFilesSelected(categoryId, files) {
                    if (!bulkUploadFiles[categoryId]) {
                        bulkUploadFiles[categoryId] = [];
                    }
                    
                    // Validate and add files to array
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
                        const exists = bulkUploadFiles[categoryId].some(f => f.name === file.name && f.size === file.size);
                        if (!exists) {
                            bulkUploadFiles[categoryId].push(file);
                        }
                    });
                    
                    if (invalidFiles.length > 0) {
                        alert('The following files were skipped:\n' + invalidFiles.join('\n'));
                    }
                    
                    if (bulkUploadFiles[categoryId].length === 0) {
                        alert('No valid files selected. Please select PDF, JPG, PNG, DOC, DOCX, XLS, XLSX, CSV, MP3, videos (MP4, WebM, MOV, VOB, etc.), or MS Teams recordings under the size limit.');
                        return;
                    }
                    
                    // Show file list
                    const container = $('#bulk-upload-' + categoryId);
                    container.find('.bulk-upload-file-list').show();
                    container.find('.file-count').text(bulkUploadFiles[categoryId].length);
                    
                    // Show mapping interface
                    showBulkUploadMapping(categoryId);
                }
                
                // Show mapping interface
                function showBulkUploadMapping(categoryId) {
                    currentCategoryId = categoryId;
                    const files = bulkUploadFiles[categoryId];
                    
                    if (files.length === 0) {
                        return;
                    }
                    
                    // Get existing checklists for this category
                    getExistingChecklists(categoryId, function(checklists) {
                        // Call backend to get auto-matches
                        getAutoChecklistMatches(categoryId, files, checklists, function(matches) {
                            displayMappingInterface(files, checklists, matches);
                        });
                    });
                }
                
                // Get existing checklists
                function getExistingChecklists(categoryId, callback) {
                    const checklists = [];
                    const checklistNames = new Set();
                    
                    $('.documnetlist_' + categoryId + ' .personalchecklist-row').each(function() {
                        const checklistName = $(this).data('personalchecklistname');
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
                
                // Get auto-checklist matches from backend
                function getAutoChecklistMatches(categoryId, files, checklists, callback) {
                    const fileData = Array.from(files).map(file => ({
                        name: file.name,
                        size: file.size,
                        type: file.type
                    }));
                    
                    const checklistNames = checklists.map(c => c.name);
                    
                    $.ajax({
                        url: '{{ route("clients.documents.getAutoChecklistMatches") }}',
                        method: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            clientid: currentClientId,
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
                
                // Display mapping interface
                function displayMappingInterface(files, checklists, matches) {
                    const modal = $('#bulk-upload-mapping-modal');
                    const tableContainer = $('#bulk-upload-mapping-table');
                    
                    let html = '<table class="table table-bordered" style="width: 100%;">';
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
                        
                        if (match && match.checklist) {
                            selectedChecklist = match.checklist;
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
                        html += '<i class="fa-solid fa-file personal-doc-file-icon"></i>';
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
                        checklists.forEach(checklist => {
                            const selected = !preferNewChecklist && selectedChecklist === checklist.name ? 'selected' : '';
                            html += '<option value="' + escapeHtml(checklist.name) + '" ' + selected + '>' + escapeHtml(checklist.name) + '</option>';
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
                    
                    window._bulkUploadConfirmFn = confirmPersonalBulkUpload;
                    window._bulkUploadOnCancel = function() {
                        if (currentCategoryId) {
                            resetBulkUploadSelection(currentCategoryId);
                        }
                    };

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
                    
                    // Handle remove file button
                    $(document).off('click.bulkUploadRemove', '.remove-bulk-file').on('click.bulkUploadRemove', '.remove-bulk-file', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        const $row = $(this).closest('tr');
                        const fileName = $row.data('file-name');
                        const categoryId = currentCategoryId;
                        
                        // Confirm before removing
                        if (!confirm('Are you sure you want to remove "' + fileName + '" from the upload list?')) {
                            return;
                        }
                        
                        // Find and remove the file from the array by matching file name
                        const fileArray = bulkUploadFiles[categoryId] || [];
                        const fileIndex = fileArray.findIndex(f => f.name === fileName);
                        
                        if (fileIndex > -1) {
                            fileArray.splice(fileIndex, 1);
                        }
                        
                        // Remove the row
                        $row.remove();
                        
                        // Update file count
                        const remainingCount = fileArray.length;
                        const container = $('#bulk-upload-' + categoryId);
                        container.find('.file-count').text(remainingCount);
                        
                        // If no files left, hide the file list and modal
                        if (remainingCount === 0) {
                            window.hideBulkUploadModal();
                            resetBulkUploadSelection(categoryId);
                            alert('All files have been removed. Please select files again to upload.');
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
                    
                    modal.show();
                }
                
                // Confirm bulk upload
                function confirmPersonalBulkUpload() {
                    const categoryId = currentCategoryId;
                    const files = bulkUploadFiles[categoryId] || [];
                    if (!files.length) {
                        alert('No files selected. Please select files to upload.');
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
                                // Auto-create from filename
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
                            // Auto-create for unmatched
                            mapping = {
                                type: 'new',
                                name: extractChecklistNameFromFile(fileName)
                            };
                        }
                        
                        if (!mapping) {
                            // Try to get from auto-match if available
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
                        alert('Please map all files to checklists or enable "Auto-create checklist for unmatched files"');
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
                    uploadBulkFiles(categoryId, files, mappings);
                }
                
                // Extract checklist name from filename (keep apostrophes, parentheses, etc.)
                function extractChecklistNameFromFile(fileName) {
                    let name = String(fileName || '').replace(/\.[^/.]+$/, '');
                    // Remove timestamps
                    name = name.replace(/_\d{10,}$/, '');
                    // Replace underscores with spaces
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
                
                // Upload bulk files
                function uploadBulkFiles(categoryId, files, mappings) {
                    const formData = new FormData();
                    formData.append('_token', '{{ csrf_token() }}');
                    formData.append('clientid', currentClientId);
                    formData.append('categoryid', categoryId);
                    formData.append('doctype', 'personal');
                    formData.append('type', 'client');

                    const fileList = Array.from(files);
                    const videoFiles = fileList.filter(function(file) {
                        return typeof isPersonalDocVideoFile === 'function' && isPersonalDocVideoFile(file);
                    });
                    const hasVideos = videoFiles.length > 0;
                    
                    // Add files
                    fileList.forEach((file, index) => {
                        formData.append('files[]', file);
                        const mapping = mappings[index] || { type: 'new', name: extractChecklistNameFromFile(file.name) };
                        formData.append('mappings[]', JSON.stringify(mapping));
                    });
                    
                    $('#confirm-bulk-upload').prop('disabled', true);

                    if (hasVideos && typeof showPersonalVideoUploadLoader === 'function') {
                        $('#bulk-upload-mapping-modal').hide();
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
                        url: '{{ route("clients.documents.bulkUploadPersonalDocuments") }}',
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

                                var finishBulkUpload = function(reloadPage) {
                                    if (typeof hidePersonalVideoUploadLoader === 'function') {
                                        hidePersonalVideoUploadLoader(reloadPage ? 700 : 0);
                                    }
                                    window.hideBulkUploadModal();
                                    resetBulkUploadSelection(categoryId);
                                    $('#bulk-upload-progress').hide();
                                    $('#confirm-bulk-upload').prop('disabled', false);
                                    if (reloadPage) {
                                        location.reload();
                                    }
                                };

                                if (tokens.length > 0 && typeof waitForPersonalVideoUploads === 'function') {
                                    if (hasVideos && typeof updatePersonalVideoUploadLoader === 'function') {
                                        updatePersonalVideoUploadLoader('queued', 44, 'Upload complete. Processing video(s) in queue…');
                                    }
                                    waitForPersonalVideoUploads(tokens, function(success, message) {
                                        if (typeof showPersonalDocVideoToast === 'function') {
                                            showPersonalDocVideoToast(success, message);
                                        } else {
                                            alert(message);
                                        }
                                        finishBulkUpload(success);
                                    }, { skipLoader: hasVideos });
                                    return;
                                }

                                if (hasVideos && typeof updatePersonalVideoUploadLoader === 'function') {
                                    if (typeof clearPersonalVideoProcessingPulse === 'function') {
                                        clearPersonalVideoProcessingPulse();
                                    }
                                    updatePersonalVideoUploadLoader('complete', 100, 'Upload complete!');
                                }

                                if (typeof showPersonalDocVideoToast === 'function') {
                                    showPersonalDocVideoToast(true, response.message || 'Files uploaded successfully!');
                                } else {
                                    alert(response.message || 'Files uploaded successfully!');
                                }
                                finishBulkUpload(true);
                            } else {
                                let errorMsg = 'Error: ' + (response.message || 'Upload failed');
                                if (response.errors && response.errors.length > 0) {
                                    errorMsg += '\n\nDetails:\n' + response.errors.join('\n');
                                }
                                if (hasVideos && typeof hidePersonalVideoUploadLoader === 'function') {
                                    updatePersonalVideoUploadLoader('error', 0, errorMsg);
                                    hidePersonalVideoUploadLoader(900);
                                }
                                alert(errorMsg);
                                $('#bulk-upload-progress').hide();
                                $('#confirm-bulk-upload').prop('disabled', false);
                                resetBulkUploadFileInput(categoryId);
                            }
                        },
                        error: function(xhr) {
                            let errorMsg = 'Upload failed';
                            if (xhr.statusText === 'timeout') {
                                errorMsg = 'Upload timed out. Large videos can take several minutes — please keep this tab open and try again on a stable connection.';
                            } else if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMsg = xhr.responseJSON.message;
                            }
                            if (hasVideos && typeof hidePersonalVideoUploadLoader === 'function') {
                                updatePersonalVideoUploadLoader('error', 0, errorMsg);
                                hidePersonalVideoUploadLoader(900);
                            }
                            alert('Error: ' + errorMsg);
                            $('#bulk-upload-progress').hide();
                            $('#confirm-bulk-upload').prop('disabled', false);
                            resetBulkUploadFileInput(categoryId);
                        }
                    });
                }
                
                // Format file size
                function formatFileSize(bytes) {
                    if (bytes === 0) return '0 Bytes';
                    const k = 1024;
                    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                    const i = Math.floor(Math.log(bytes) / Math.log(k));
                    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
                }
                
                // Escape HTML
                function escapeHtml(text) {
                    const map = {
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#039;'
                    };
                    return text.replace(/[&<>"']/g, m => map[m]);
                }
            </script>

