           <!-- Not Used Documents Tab (Shared - Client Level) -->
           <div class="tab-pane{{ strtolower((string) ($activeTab ?? '')) === 'notuseddocuments' ? ' active' : '' }}" id="notuseddocuments-tab">
                <div class="card full-width documentalls-container not-used-documents-page">
                    <?php
                    $notUsedDocs = collect();
                    if (\Illuminate\Support\Facades\Schema::hasTable('documents')
                        && \Illuminate\Support\Facades\Schema::hasColumn('documents', 'client_id')
                        && \Illuminate\Support\Facades\Schema::hasColumn('documents', 'not_used_doc')
                        && \Illuminate\Support\Facades\Schema::hasColumn('documents', 'doc_type')
                        && \Illuminate\Support\Facades\Schema::hasColumn('documents', 'type')
                        && \Illuminate\Support\Facades\Schema::hasColumn('documents', 'checklist')
                        && \Illuminate\Support\Facades\Schema::hasColumn('documents', 'user_id')) {
                        $notUsedDocs = \App\Models\Document::where('client_id', $fetchedData->id)
                            ->where('not_used_doc', 1)
                            ->where('type', 'client')
                            ->where(function ($query) {
                                $query->orWhere('doc_type', 'personal')
                                    ->orWhereIn('doc_type', ['matter', 'visa']);
                            })
                            ->orderByDesc('updated_at')
                            ->get();
                    }

                    $folderTitles = \Illuminate\Support\Facades\Schema::hasTable('personal_document_types')
                        ? \App\Models\PersonalDocumentType::pluck('title', 'id')
                        : collect();

                    $notUsedPersonalCount = $notUsedDocs->where('doc_type', 'personal')->count();
                    $notUsedMatterCount = $notUsedDocs->whereIn('doc_type', ['matter', 'visa'])->count();
                    $notUsedTotalCount = $notUsedDocs->count();

                    $notUsedDocTypeLabel = function ($docType) {
                        if ($docType === 'personal') {
                            return 'Personal';
                        }
                        return 'Matter';
                    };

                    $notUsedFileIcon = function ($filetype) {
                        $ext = strtolower((string) $filetype);
                        if (in_array($ext, ['mp4', 'webm', 'mov', 'm4v', 'avi', 'mkv'], true)) {
                            return 'fa-file-video';
                        }
                        if (in_array($ext, ['pdf'], true)) {
                            return 'fa-file-pdf';
                        }
                        if (in_array($ext, ['doc', 'docx'], true)) {
                            return 'fa-file-word';
                        }
                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                            return 'fa-file-image';
                        }
                        return 'fa-file-lines';
                    };
                    ?>

                    <div class="not-used-layout">
                        <div class="not-used-main">
                            <div class="not-used-header">
                                <div class="not-used-header-text">
                                    <h3><i class="fa-solid fa-folder-minus"></i> Not Used Documents</h3>
                                    <p>Documents removed from Personal or Matter folders appear here. Use <strong>Revert</strong> to restore a file to its original folder.</p>
                                </div>
                                <div class="not-used-header-actions">
                                    <button type="button" class="btn btn-secondary client-nav-button" data-tab="personaldocuments">
                                        <i class="fa-solid fa-arrow-left"></i> Back to Personal Documents
                                    </button>
                                </div>
                            </div>

                            <div class="not-used-toolbar">
                                <div class="not-used-stats">
                                    <span class="not-used-stat-chip not-used-stat-total">
                                        <i class="fa-solid fa-layer-group"></i> {{ $notUsedTotalCount }} total
                                    </span>
                                    <span class="not-used-stat-chip not-used-stat-personal">
                                        <i class="fa-solid fa-user"></i> {{ $notUsedPersonalCount }} personal
                                    </span>
                                    <span class="not-used-stat-chip not-used-stat-matter">
                                        <i class="fa-solid fa-briefcase"></i> {{ $notUsedMatterCount }} matter
                                    </span>
                                </div>
                                <div class="not-used-search-wrap">
                                    <i class="fa-solid fa-search"></i>
                                    <input type="search" id="notUsedDocsSearch" class="not-used-search" placeholder="Search checklist, file name, or type…" autocomplete="off">
                                </div>
                            </div>

                            @if ($notUsedTotalCount === 0)
                                <div class="not-used-empty-state" id="notUsedEmptyState">
                                    <div class="not-used-empty-icon"><i class="fa-solid fa-inbox"></i></div>
                                    <h4>No documents in Not Used</h4>
                                    <p>When you mark a document as "Not Used" from Personal or Matter Documents, it will appear here for review or revert.</p>
                                    <button type="button" class="btn btn-primary client-nav-button" data-tab="personaldocuments">
                                        <i class="fa-solid fa-folder-open"></i> Go to Personal Documents
                                    </button>
                                </div>
                            @endif

                            <div class="checklist-table-container not-used-table-wrap" id="notUsedTableWrap" @if ($notUsedTotalCount === 0) style="display: none;" @endif>
                                    <div class="checklist-table-scroll">
                                        <table class="checklist-table not-used-table">
                                            <thead>
                                                <tr>
                                                    <th>Checklist</th>
                                                    <th>Type</th>
                                                    <th>Original folder</th>
                                                    <th>File</th>
                                                    <th class="not-used-actions-col">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody class="tdata notuseddocumnetlist" id="notUsedDocsTableBody">
                                                @foreach ($notUsedDocs as $fetch)
                                                    @php
                                                        $admin = \App\Models\Staff::where('id', $fetch->user_id)->first();
                                                        $previewUrl = url('/documents/preview/' . $fetch->id);
                                                        $downloadFilename = $fetch->myfile_key ?: trim(($fetch->file_name ?? '') . '.' . ($fetch->filetype ?? ''), '.');
                                                        $uploadMeta = 'Uploaded by: ' . ($admin->first_name ?? 'NA') . ' on ' . date('d/m/Y H:i', strtotime($fetch->created_at));
                                                        $typeLabel = $notUsedDocTypeLabel($fetch->doc_type);
                                                        $typeClass = $fetch->doc_type === 'personal' ? 'personal' : 'matter';
                                                        $folderLabel = $fetch->doc_type === 'personal'
                                                            ? ($folderTitles[$fetch->folder_name] ?? 'Personal folder')
                                                            : '—';
                                                        $searchBlob = strtolower(trim(($fetch->checklist ?? '') . ' ' . ($fetch->file_name ?? '') . ' ' . ($fetch->filetype ?? '') . ' ' . $typeLabel . ' ' . $folderLabel));
                                                        $fileIcon = $notUsedFileIcon($fetch->filetype ?? '');
                                                    @endphp
                                                    <tr class="drow not-used-row" id="id_{{ $fetch->id }}" data-search="{{ e($searchBlob) }}">
                                                        <td>
                                                            <div class="not-used-checklist" title="{{ $uploadMeta }}">
                                                                <span class="not-used-checklist-name">{{ $fetch->checklist ?: 'N/A' }}</span>
                                                                <span class="not-used-meta">{{ $uploadMeta }}</span>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="not-used-type-badge not-used-type-{{ $typeClass }}">{{ $typeLabel }}</span>
                                                        </td>
                                                        <td>
                                                            <span class="not-used-folder-label">{{ $folderLabel }}</span>
                                                        </td>
                                                        <td>
                                                            @if (!empty($fetch->file_name))
                                                                <div data-id="{{ $fetch->id }}" data-name="{{ $fetch->file_name }}" data-uploaded-at="{{ date('d/m/Y H:i', strtotime($fetch->created_at)) }}" class="doc-row not-used-file-link" title="{{ $uploadMeta }}" oncontextmenu="showNotUsedFileContextMenu(event, {{ (int) $fetch->id }}, {{ json_encode($fetch->filetype) }}, {{ json_encode($previewUrl) }}, {{ json_encode($fetch->doc_type) }}, {{ json_encode($fetch->status ?? 'draft') }}); return false;">
                                                                    <a href="javascript:void(0);" onclick="previewFile({{ json_encode($fetch->filetype) }}, {{ json_encode($previewUrl) }}, {{ json_encode('preview-container-notuseddocumnetlist') }})">
                                                                        <i class="fa-solid {{ $fileIcon }}"></i>
                                                                        <span>{{ $fetch->file_name . '.' . $fetch->filetype }}</span>
                                                                    </a>
                                                                </div>
                                                            @else
                                                                <span class="text-muted">N/A</span>
                                                            @endif
                                                        </td>
                                                        <td class="not-used-actions-col">
                                                            @if ($fetch->myfile)
                                                                <a class="download-file" data-document-id="{{ $fetch->id }}" data-id="{{ $fetch->id }}" data-filename="{{ e($downloadFilename) }}" href="#" style="display: none;"></a>
                                                            @endif
                                                            <a data-id="{{ $fetch->id }}" class="deletenote" data-doccategory="{{ $fetch->doc_type }}" data-href="deletedocs" href="javascript:;" style="display: none;"></a>
                                                            <a data-id="{{ $fetch->id }}" class="backtodoc" data-doctype="{{ $fetch->doc_type }}" data-doccategory="{{ $fetch->folder_name }}" data-href="backtodoc" href="javascript:;" style="display: none;"></a>
                                                            <div class="not-used-actions">
                                                                @if (!empty($fetch->file_name))
                                                                    <button type="button" class="btn-not-used-action btn-not-used-preview" title="Preview" onclick="previewFile({{ json_encode($fetch->filetype) }}, {{ json_encode($previewUrl) }}, {{ json_encode('preview-container-notuseddocumnetlist') }})">
                                                                        <i class="fa-solid fa-eye"></i>
                                                                    </button>
                                                                @endif
                                                                @if ($fetch->myfile)
                                                                    <button type="button" class="btn-not-used-action btn-not-used-download download-file" data-document-id="{{ $fetch->id }}" data-id="{{ $fetch->id }}" data-filename="{{ e($downloadFilename) }}" title="Download">
                                                                        <i class="fa-solid fa-download"></i>
                                                                    </button>
                                                                @endif
                                                                <button type="button" class="btn-not-used-action btn-not-used-revert backtodoc" data-id="{{ $fetch->id }}" data-doctype="{{ $fetch->doc_type }}" data-doccategory="{{ $fetch->folder_name }}" title="Revert to original folder">
                                                                    <i class="fa-solid fa-undo"></i> Revert
                                                                </button>
                                                                <button type="button" class="btn-not-used-action btn-not-used-delete" title="Delete permanently" onclick="$('.deletenote[data-id=\'{{ $fetch->id }}\']').trigger('click');">
                                                                    <i class="fa-solid fa-trash-can"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="not-used-no-results" id="notUsedNoResults" style="display: none;">
                                        <i class="fa-solid fa-search"></i>
                                        <p>No documents match your search.</p>
                                    </div>
                            </div>
                        </div>

                        <div class="preview-pane file-preview-container preview-container-notuseddocumnetlist not-used-preview-pane client-doc-preview-pane">
                            <div class="not-used-preview-placeholder">
                                <i class="fa-solid fa-file-lines"></i>
                                <p>Select a file to preview it here</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Custom Context Menu for Not Used Documents -->
            <div id="notUsedFileContextMenu" class="context-menu not-used-context-menu">
                <div class="context-menu-item" onclick="handleNotUsedContextAction('preview')">
                    <i class="fa-solid fa-eye"></i> Preview
                </div>
                <div class="context-menu-item" onclick="handleNotUsedContextAction('download')">
                    <i class="fa-solid fa-download"></i> Download
                </div>
                <div class="context-menu-item" onclick="handleNotUsedContextAction('back-to-doc')">
                    <i class="fa-solid fa-undo"></i> Revert
                </div>
                <div class="context-menu-item context-menu-item-danger" onclick="handleNotUsedContextAction('delete')">
                    <i class="fa-solid fa-trash"></i> Delete
                </div>
            </div>

            <script>
                let currentNotUsedContextFile = null;
                let currentNotUsedContextData = {};

                function showNotUsedFileContextMenu(event, fileId, fileType, fileUrl, docType, fileStatus) {
                    event.preventDefault();
                    event.stopPropagation();

                    currentNotUsedContextFile = fileId;
                    currentNotUsedContextData = {
                        fileId: fileId,
                        fileType: fileType,
                        fileUrl: fileUrl,
                        docType: docType,
                        fileStatus: fileStatus
                    };

                    const menu = document.getElementById('notUsedFileContextMenu');
                    const MENU_WIDTH = 200;
                    const MENU_HEIGHT = 168;
                    const viewportWidth = window.innerWidth;
                    const viewportHeight = window.innerHeight;
                    const offset = 5;

                    let menuLeft = event.clientX + offset;
                    let menuTop = event.clientY + offset;

                    if (menuLeft + MENU_WIDTH > viewportWidth) {
                        menuLeft = event.clientX - MENU_WIDTH - offset;
                    }
                    if (menuTop + MENU_HEIGHT > viewportHeight) {
                        menuTop = event.clientY - MENU_HEIGHT - offset;
                    }
                    menuLeft = Math.max(offset, menuLeft);
                    menuTop = Math.max(offset, menuTop);

                    menu.style.left = menuLeft + 'px';
                    menu.style.top = menuTop + 'px';
                    menu.style.display = 'block';

                    setTimeout(function() {
                        document.addEventListener('click', hideNotUsedContextMenu);
                    }, 100);
                }

                function hideNotUsedContextMenu() {
                    const menu = document.getElementById('notUsedFileContextMenu');
                    menu.style.display = 'none';
                    document.removeEventListener('click', hideNotUsedContextMenu);
                }

                function handleNotUsedContextAction(action) {
                    if (!currentNotUsedContextFile) return;

                    hideNotUsedContextMenu();

                    switch (action) {
                        case 'preview':
                            if (typeof previewFile === 'function' && currentNotUsedContextData.fileUrl) {
                                previewFile(
                                    currentNotUsedContextData.fileType || 'pdf',
                                    currentNotUsedContextData.fileUrl,
                                    'preview-container-notuseddocumnetlist'
                                );
                            }
                            break;
                        case 'download':
                            $('.download-file[data-id="' + currentNotUsedContextFile + '"]').first().trigger('click');
                            break;
                        case 'delete':
                            $('.deletenote[data-id="' + currentNotUsedContextFile + '"]').first().trigger('click');
                            break;
                        case 'back-to-doc':
                            $('.backtodoc[data-id="' + currentNotUsedContextFile + '"]').first().trigger('click');
                            break;
                    }
                }

                function filterNotUsedDocuments(query) {
                    var q = (query || '').trim().toLowerCase();
                    var visible = 0;
                    $('#notUsedDocsTableBody .not-used-row').each(function() {
                        var hay = $(this).attr('data-search') || '';
                        var show = !q || hay.indexOf(q) !== -1;
                        $(this).toggle(show);
                        if (show) visible++;
                    });
                    $('#notUsedNoResults').toggle(q.length > 0 && visible === 0);
                }

                $(document).on('input', '#notUsedDocsSearch', function() {
                    filterNotUsedDocuments($(this).val());
                });

                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        hideNotUsedContextMenu();
                    }
                });
            </script>

            <style>
                #notuseddocuments-tab .not-used-layout {
                    display: flex;
                    gap: 20px;
                    padding: 16px 18px 20px;
                    align-items: flex-start;
                }

                #notuseddocuments-tab .not-used-main {
                    flex: 1;
                    min-width: 0;
                }

                #notuseddocuments-tab .not-used-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                    gap: 16px;
                    margin-bottom: 18px;
                    flex-wrap: wrap;
                }

                #notuseddocuments-tab .not-used-header h3 {
                    margin: 0 0 6px;
                    color: var(--navy, #1e3d60);
                    font-weight: 700;
                    font-size: 1.25rem;
                }

                #notuseddocuments-tab .not-used-header h3 i {
                    color: var(--sidebar-active, #3a6fa8);
                    margin-right: 8px;
                }

                #notuseddocuments-tab .not-used-header p {
                    margin: 0;
                    color: var(--text-muted, #5e7a90);
                    max-width: 640px;
                    line-height: 1.5;
                }

                #notuseddocuments-tab .not-used-toolbar {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    gap: 12px;
                    flex-wrap: wrap;
                    margin-bottom: 16px;
                }

                #notuseddocuments-tab .not-used-stats {
                    display: flex;
                    gap: 8px;
                    flex-wrap: wrap;
                }

                #notuseddocuments-tab .not-used-stat-chip {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    padding: 6px 12px;
                    border-radius: 999px;
                    font-size: 12px;
                    font-weight: 600;
                    border: 1px solid var(--border, #c8dcef);
                    background: var(--page-bg, #f0f6ff);
                    color: var(--text-dark, #1a2c40);
                }

                #notuseddocuments-tab .not-used-stat-personal {
                    background: rgba(58, 111, 168, 0.1);
                    border-color: rgba(58, 111, 168, 0.25);
                    color: var(--navy, #1e3d60);
                }

                #notuseddocuments-tab .not-used-stat-matter {
                    background: rgba(30, 122, 82, 0.1);
                    border-color: rgba(30, 122, 82, 0.25);
                    color: var(--success, #1e7a52);
                }

                #notuseddocuments-tab .not-used-search-wrap {
                    position: relative;
                    min-width: 240px;
                    flex: 1;
                    max-width: 360px;
                }

                #notuseddocuments-tab .not-used-search-wrap i {
                    position: absolute;
                    left: 12px;
                    top: 50%;
                    transform: translateY(-50%);
                    color: var(--text-muted, #5e7a90);
                    font-size: 13px;
                }

                #notuseddocuments-tab .not-used-search {
                    width: 100%;
                    padding: 9px 12px 9px 36px;
                    border: 1px solid var(--border, #c8dcef);
                    border-radius: 8px;
                    background: #fff;
                    color: var(--text-dark, #1a2c40);
                    font-size: 14px;
                }

                #notuseddocuments-tab .not-used-search:focus {
                    outline: none;
                    border-color: var(--sidebar-active, #3a6fa8);
                    box-shadow: 0 0 0 3px rgba(58, 111, 168, 0.15);
                }

                #notuseddocuments-tab .not-used-table-wrap {
                    border: 1px solid var(--border, #c8dcef);
                    border-radius: 10px;
                    overflow: hidden;
                    background: #fff;
                }

                #notuseddocuments-tab .not-used-table tbody tr:hover {
                    background: rgba(221, 234, 248, 0.45);
                }

                #notuseddocuments-tab .not-used-checklist {
                    display: flex;
                    flex-direction: column;
                    gap: 3px;
                }

                #notuseddocuments-tab .not-used-checklist-name {
                    font-weight: 600;
                    color: var(--text-dark, #1a2c40);
                }

                #notuseddocuments-tab .not-used-meta {
                    font-size: 11px;
                    color: var(--text-muted, #5e7a90);
                }

                #notuseddocuments-tab .not-used-type-badge {
                    display: inline-block;
                    padding: 4px 10px;
                    border-radius: 999px;
                    font-size: 11px;
                    font-weight: 700;
                    letter-spacing: 0.02em;
                    text-transform: uppercase;
                }

                #notuseddocuments-tab .not-used-type-personal {
                    background: rgba(58, 111, 168, 0.12);
                    color: var(--navy, #1e3d60);
                }

                #notuseddocuments-tab .not-used-type-matter {
                    background: rgba(30, 122, 82, 0.12);
                    color: var(--success, #1e7a52);
                }

                #notuseddocuments-tab .not-used-folder-label {
                    font-size: 13px;
                    color: var(--text-dark, #1a2c40);
                }

                #notuseddocuments-tab .not-used-file-link a {
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    color: var(--sidebar-active, #3a6fa8);
                    font-weight: 500;
                    text-decoration: none;
                }

                #notuseddocuments-tab .not-used-file-link a:hover {
                    text-decoration: underline;
                }

                #notuseddocuments-tab .not-used-actions-col {
                    width: 1%;
                    white-space: nowrap;
                }

                #notuseddocuments-tab .not-used-actions {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    flex-wrap: wrap;
                }

                #notuseddocuments-tab .btn-not-used-action {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    gap: 5px;
                    padding: 6px 10px;
                    border-radius: 6px;
                    border: 1px solid var(--border, #c8dcef);
                    background: #fff;
                    color: var(--text-dark, #1a2c40);
                    font-size: 12px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.15s ease;
                }

                #notuseddocuments-tab .btn-not-used-action:hover {
                    border-color: var(--sidebar-active, #3a6fa8);
                    color: var(--sidebar-active, #3a6fa8);
                    background: rgba(221, 234, 248, 0.5);
                }

                #notuseddocuments-tab .btn-not-used-revert {
                    background: rgba(30, 122, 82, 0.1);
                    border-color: rgba(30, 122, 82, 0.35);
                    color: var(--success, #1e7a52);
                }

                #notuseddocuments-tab .btn-not-used-revert:hover {
                    background: var(--success, #1e7a52);
                    border-color: var(--success, #1e7a52);
                    color: #fff;
                }

                #notuseddocuments-tab .btn-not-used-delete:hover {
                    background: var(--danger, #a83020);
                    border-color: var(--danger, #a83020);
                    color: #fff;
                }

                #notuseddocuments-tab .not-used-preview-pane {
                    width: min(420px, 36vw);
                    min-width: 280px;
                    flex-shrink: 0;
                    margin-top: 0 !important;
                    border: 1px solid var(--border, #c8dcef);
                    border-radius: 10px;
                    background: var(--page-bg, #f0f6ff);
                    min-height: 360px;
                    padding: 16px;
                }

                #notuseddocuments-tab .not-used-preview-placeholder {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    text-align: center;
                    min-height: 320px;
                    color: var(--text-muted, #5e7a90);
                    gap: 10px;
                }

                #notuseddocuments-tab .not-used-preview-placeholder i {
                    font-size: 42px;
                    opacity: 0.45;
                }

                #notuseddocuments-tab .not-used-empty-state {
                    text-align: center;
                    padding: 48px 24px;
                    border: 1px dashed var(--border, #c8dcef);
                    border-radius: 12px;
                    background: var(--page-bg, #f0f6ff);
                }

                #notuseddocuments-tab .not-used-empty-icon {
                    font-size: 48px;
                    color: var(--sidebar-active, #3a6fa8);
                    opacity: 0.5;
                    margin-bottom: 12px;
                }

                #notuseddocuments-tab .not-used-empty-state h4 {
                    margin: 0 0 8px;
                    color: var(--navy, #1e3d60);
                }

                #notuseddocuments-tab .not-used-empty-state p {
                    margin: 0 auto 18px;
                    max-width: 420px;
                    color: var(--text-muted, #5e7a90);
                }

                #notuseddocuments-tab .not-used-no-results {
                    text-align: center;
                    padding: 28px;
                    color: var(--text-muted, #5e7a90);
                }

                #notuseddocuments-tab .not-used-no-results i {
                    font-size: 24px;
                    margin-bottom: 8px;
                    display: block;
                    opacity: 0.6;
                }

                .not-used-context-menu {
                    display: none;
                    position: fixed;
                    background: #fff;
                    border: 1px solid var(--border, #c8dcef);
                    border-radius: 8px;
                    box-shadow: 0 8px 24px rgba(30, 61, 96, 0.15);
                    z-index: 10000;
                    min-width: 190px;
                    overflow: hidden;
                    padding: 4px 0;
                }

                .not-used-context-menu .context-menu-item {
                    padding: 10px 14px;
                    cursor: pointer;
                    font-size: 13px;
                    color: var(--text-dark, #1a2c40);
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }

                .not-used-context-menu .context-menu-item:hover {
                    background: var(--sidebar-bg, #ddeaf8);
                }

                .not-used-context-menu .context-menu-item-danger {
                    color: var(--danger, #a83020);
                }

                .not-used-context-menu .context-menu-item-danger:hover {
                    background: rgba(168, 48, 32, 0.08);
                }

                @media (max-width: 1100px) {
                    #notuseddocuments-tab .not-used-layout {
                        flex-direction: column;
                    }

                    #notuseddocuments-tab .not-used-preview-pane {
                        width: 100%;
                        min-height: 280px;
                    }
                }
            </style>
