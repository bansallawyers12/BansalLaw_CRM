(function () {
    'use strict';

    var config = window.SmartEmailImportConfig || {};
    var state = {
        batchToken: null,
        items: [],
        selectedFiles: [],
        clientSelects: {},
        matterSelects: {},
        isConfirming: false,
        isAnalyzing: false,
    };

    function $(id) {
        return document.getElementById(id);
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatEmailSectionDate(dateString) {
        if (!dateString) {
            return '';
        }
        if (/^\d{2}\/\d{2}\/\d{4}/.test(dateString)) {
            return dateString;
        }
        var date = new Date(dateString);
        if (isNaN(date.getTime())) {
            return String(dateString);
        }
        var day = String(date.getDate()).padStart(2, '0');
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var year = date.getFullYear();
        var hours = date.getHours();
        var minutes = String(date.getMinutes()).padStart(2, '0');
        var ampm = hours >= 12 ? 'pm' : 'am';
        hours = hours % 12;
        if (hours === 0) {
            hours = 12;
        }
        return day + '/' + month + '/' + year + ' ' + String(hours).padStart(2, '0') + ':' + minutes + ' ' + ampm;
    }

    function sanitizeSmartImportUploadFilename(filename) {
        if (typeof window.crmSanitizeEmailUploadFilename === 'function') {
            return window.crmSanitizeEmailUploadFilename(filename);
        }
        if (!filename || typeof filename !== 'string') {
            return 'email_' + Date.now() + '.msg';
        }
        var lastDot = filename.lastIndexOf('.');
        var extension = lastDot >= 0 ? filename.slice(lastDot + 1).toLowerCase().replace(/[^a-z0-9]/g, '') : '';
        var nameWithoutExt = lastDot >= 0 ? filename.slice(0, lastDot) : filename;
        var sanitizedName = nameWithoutExt.replace(/[^a-zA-Z0-9_-]/g, '_');
        sanitizedName = sanitizedName.replace(/_+/g, '_').replace(/^_+|_+$/g, '');
        if (!sanitizedName) {
            sanitizedName = 'email_' + Date.now();
        }
        var sanitizedFilename = extension ? sanitizedName + '.' + extension : sanitizedName;
        if (sanitizedFilename.length > 255) {
            var maxNameLength = 255 - extension.length - (extension ? 1 : 0);
            if (maxNameLength > 0) {
                sanitizedName = sanitizedName.slice(0, maxNameLength);
                sanitizedFilename = extension ? sanitizedName + '.' + extension : sanitizedName;
            } else {
                sanitizedFilename = 'email_' + Date.now() + (extension ? '.' + extension : '');
            }
        }
        return sanitizedFilename;
    }

    function confidenceClass(confidence) {
        if (confidence >= 80) return 'confidence-high';
        if (confidence >= 50) return 'confidence-medium';
        return 'confidence-low';
    }

    function confidenceLabel(confidence) {
        if (!confidence) return 'No match';
        return confidence + '%';
    }

    function matchedByLabel(keys) {
        if (!keys || !keys.length) return '';
        var labels = {
            matter_reference: 'Matter ref',
            client_reference: 'Client ref',
            email_address: 'Email',
        };
        return keys.map(function (key) {
            return labels[key] || key;
        }).join(', ');
    }

    function suggestionOptionLabel(suggestion) {
        var parts = [
            suggestion.client_name || suggestion.client_ref || ('Client #' + suggestion.client_id),
            suggestion.matter_no || '',
            suggestion.confidence ? suggestion.confidence + '%' : '',
        ].filter(Boolean);
        return parts.join(' · ');
    }

    function buildSuggestionPickerHtml(index, item) {
        var suggestions = item.suggestions || [];
        if (!suggestions.length) {
            return '';
        }
        var options = suggestions.map(function (s, sIdx) {
            return '<option value="' + sIdx + '">' + escapeHtml(suggestionOptionLabel(s)) + '</option>';
        }).join('');
        return (
            '<div class="smart-import-suggestion-pick">' +
                '<label class="smart-import-field-label">Use suggestion</label>' +
                '<select class="form-control form-control-sm suggestion-pick-select" data-index="' + index + '">' +
                    '<option value="">— Pick a suggestion —</option>' +
                    options +
                '</select>' +
            '</div>'
        );
    }

    function setStatus(el, message, type) {
        if (!el) return;
        el.textContent = message || '';
        el.className = 'smart-import-status mt-2';
        if (type === 'error') {
            el.style.color = '#c0392b';
        } else if (type === 'success') {
            el.style.color = '#1b7a3f';
        } else {
            el.style.color = '#5a6b7d';
        }
    }

    function updateAnalyzeButton() {
        var btn = $('smart-import-analyze-btn');
        if (btn) {
            btn.disabled = state.selectedFiles.length === 0 || state.isAnalyzing || state.isConfirming;
        }
    }

    function updateSmartImportLoading(title, message, filesLabel, progressPercent) {
        var titleEl = $('smartImportLoadingTitle');
        var messageEl = $('smartImportLoadingMessage');
        var filesEl = $('smartImportLoadingFiles');
        var barEl = $('smartImportLoadingProgressBar');

        if (titleEl && title) {
            titleEl.textContent = title;
        }
        if (messageEl && message) {
            messageEl.textContent = message;
        }
        if (filesEl) {
            filesEl.textContent = filesLabel || '';
        }
        if (barEl) {
            var pct = Math.max(0, Math.min(100, Number(progressPercent) || 0));
            barEl.style.width = pct + '%';
        }
    }

    function showSmartImportLoading(title, message, filesLabel, progressPercent) {
        var overlay = $('smartImportLoadingOverlay');
        if (!overlay) {
            return;
        }
        updateSmartImportLoading(title, message, filesLabel, progressPercent);
        overlay.classList.add('active');
        overlay.setAttribute('aria-hidden', 'false');
        overlay.setAttribute('aria-busy', 'true');
        document.body.classList.add('smart-import-busy');
    }

    function hideSmartImportLoading() {
        var overlay = $('smartImportLoadingOverlay');
        if (!overlay) {
            return;
        }
        overlay.classList.remove('active');
        overlay.setAttribute('aria-hidden', 'true');
        overlay.setAttribute('aria-busy', 'false');
        document.body.classList.remove('smart-import-busy');
        var barEl = $('smartImportLoadingProgressBar');
        if (barEl) {
            barEl.style.width = '0%';
        }
    }

    function formatSelectedFilesLabel(files, maxNames) {
        maxNames = maxNames || 3;
        if (!files || !files.length) {
            return '';
        }
        var names = files.slice(0, maxNames).map(function (file) {
            return file.name;
        });
        var label = names.join(', ');
        if (files.length > maxNames) {
            label += ' +' + (files.length - maxNames) + ' more';
        }
        return label;
    }

    function bindUploadPanel() {
        var dropzone = $('smart-import-dropzone');
        var fileInput = $('smart-import-files');
        var analyzeBtn = $('smart-import-analyze-btn');

        if (!dropzone || !fileInput) return;

        dropzone.addEventListener('click', function () {
            if (state.isAnalyzing || state.isConfirming) {
                return;
            }
            fileInput.click();
        });

        dropzone.addEventListener('dragover', function (e) {
            e.preventDefault();
            dropzone.classList.add('dragover');
        });

        dropzone.addEventListener('dragleave', function () {
            dropzone.classList.remove('dragover');
        });

        dropzone.addEventListener('drop', function (e) {
            e.preventDefault();
            dropzone.classList.remove('dragover');
            if (state.isAnalyzing || state.isConfirming) {
                return;
            }
            handleFiles(e.dataTransfer.files);
        });

        fileInput.addEventListener('change', function () {
            if (state.isAnalyzing || state.isConfirming) {
                return;
            }
            handleFiles(fileInput.files);
        });

        if (analyzeBtn) {
            analyzeBtn.addEventListener('click', analyzeFiles);
        }
    }

    function handleFiles(fileList) {
        var files = Array.from(fileList || []);
        var valid = [];
        var rejected = [];

        files.forEach(function (file) {
            var allowed = (typeof window.crmIsAllowedEmailUploadFilename === 'function')
                ? window.crmIsAllowedEmailUploadFilename(file.name)
                : /\.(msg|eml)$/i.test(file.name);
            if (!allowed) {
                rejected.push(file.name + ' (not an Outlook email file)');
                return;
            }
            valid.push(file);
        });

        if (valid.length > (config.maxFiles || 10)) {
            setStatus($('smart-import-upload-status'), 'Maximum ' + (config.maxFiles || 10) + ' files allowed.', 'error');
            valid = valid.slice(0, config.maxFiles || 10);
        }

        state.selectedFiles = valid;
        updateAnalyzeButton();

        var allowedLabel = (typeof window.crmEmailUploadExtensionsLabel === 'function')
            ? window.crmEmailUploadExtensionsLabel()
            : '.msg, .eml';
        var message = valid.length
            ? valid.length + ' file(s) selected: ' + valid.map(function (f) { return f.name; }).join(', ')
            : 'No valid Outlook email files selected (' + allowedLabel + ').';
        if (rejected.length) {
            message += ' Skipped: ' + rejected.join(', ');
        }
        setStatus($('smart-import-upload-status'), message, valid.length ? 'info' : 'error');
    }

    async function analyzeFiles() {
        if (!state.selectedFiles.length || state.isAnalyzing || state.isConfirming) {
            return;
        }

        var statusEl = $('smart-import-upload-status');
        var analyzeBtn = $('smart-import-analyze-btn');
        var fileCount = state.selectedFiles.length;
        var filesLabel = formatSelectedFilesLabel(state.selectedFiles);

        state.isAnalyzing = true;
        updateAnalyzeButton();
        setStatus(statusEl, 'Analyzing ' + fileCount + ' email(s)...', 'info');
        if (analyzeBtn) {
            analyzeBtn.disabled = true;
        }

        showSmartImportLoading(
            fileCount === 1 ? 'Analyzing email' : 'Analyzing ' + fileCount + ' emails',
            'Uploading and parsing .msg files, then matching clients and matters…',
            filesLabel,
            12
        );

        var progressTimer = setInterval(function () {
            var bar = $('smartImportLoadingProgressBar');
            if (!bar || !state.isAnalyzing) {
                return;
            }
            var current = parseFloat(bar.style.width) || 12;
            if (current < 88) {
                updateSmartImportLoading(null, null, null, current + 4);
            }
        }, 450);

        var formData = new FormData();
        state.selectedFiles.forEach(function (file) {
            formData.append('email_files[]', file, sanitizeSmartImportUploadFilename(file.name));
        });

        try {
            var response = await fetch(config.urls.analyze, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': config.csrfToken,
                    'Accept': 'application/json',
                },
                body: formData,
            });

            var data = await response.json();
            if (!response.ok || !data.status) {
                throw new Error(data.message || 'Analyze failed');
            }

            updateSmartImportLoading(null, 'Analysis complete. Preparing review table…', null, 100);

            state.batchToken = data.batch_token;
            state.items = (data.items || []).map(function (item) {
                item.include = false;
                item.selected_client_id = item.suggested_client_id || null;
                item.selected_client_matter_id = item.suggested_client_matter_id || null;
                item.selected_record_type = item.suggested_record_type || 'client';
                return item;
            });

            renderReviewPanel(data);
            setStatus(statusEl, data.message, 'success');
        } catch (error) {
            setStatus(statusEl, error.message || 'Analyze failed', 'error');
        } finally {
            clearInterval(progressTimer);
            state.isAnalyzing = false;
            hideSmartImportLoading();
            updateAnalyzeButton();
        }
    }

    function destroyAllClientSelects() {
        Object.keys(state.clientSelects).forEach(function (index) {
            try {
                var ts = state.clientSelects[index];
                if (ts && typeof ts.destroy === 'function') {
                    ts.destroy();
                }
            } catch (ignore) {}
        });
        state.clientSelects = {};
    }

    function renderReviewPanel(data) {
        destroyAllClientSelects();

        $('smart-import-upload-panel').style.display = 'none';
        $('smart-import-review-panel').style.display = 'block';

        var summary = $('smart-import-summary');
        summary.innerHTML =
            '<span class="badge badge-light badge-pill">' + state.items.length + ' ready</span>' +
            '<span class="badge badge-success badge-pill">' + (data.high_confidence_count || 0) + ' high confidence</span>' +
            '<span class="badge badge-warning badge-pill">' + (data.unmatched_count || 0) + ' need assignment</span>';

        var tbody = $('smart-import-table-body');
        tbody.innerHTML = '';

        state.items.forEach(function (item, index) {
            var rowClass = item.is_high_confidence ? 'row-high-confidence' : (!item.suggested_client_id ? 'row-unmatched' : '');
            var previewId = 'preview-' + index;
            var clientSelectId = 'client-select-' + index;
            var matterSelectId = 'matter-select-' + index;

            var tr = document.createElement('tr');
            tr.className = rowClass;
            tr.innerHTML =
                '<td>' +
                    '<div><strong>' + escapeHtml(item.preview.subject || '(No subject)') + '</strong></div>' +
                    '<small class="text-muted">' + escapeHtml(item.filename) + '</small>' +
                    '<div class="mt-1"><button type="button" class="btn btn-link btn-sm p-0 toggle-preview" data-target="' + previewId + '">Quick peek</button></div>' +
                    '<div class="smart-import-preview" id="' + previewId + '">' +
                        '<div><strong>From:</strong> ' + escapeHtml(item.preview.from) + '</div>' +
                        '<div><strong>To:</strong> ' + escapeHtml(item.preview.to) + '</div>' +
                        (item.preview.cc ? '<div><strong>CC:</strong> ' + escapeHtml(item.preview.cc) + '</div>' : '') +
                        (item.preview.sent_date ? '<div><strong>Date:</strong> ' + escapeHtml(formatEmailSectionDate(item.preview.sent_date)) + '</div>' : '') +
                        '<div class="mt-1">' + escapeHtml(item.preview.body_snippet) + '</div>' +
                        (item.preview.attachment_count
                            ? '<div class="mt-1"><strong>Attachments:</strong> ' + item.preview.attachment_count +
                              (item.preview.attachment_names && item.preview.attachment_names.length
                                  ? ' (' + escapeHtml(item.preview.attachment_names.join(', ')) + ')'
                                  : '') +
                              '</div>'
                            : '') +
                    '</div>' +
                '</td>' +
                '<td>' +
                    '<label class="smart-import-field-label">Mail type</label>' +
                    '<select class="form-control form-control-sm mail-type-select" data-index="' + index + '">' +
                        '<option value="inbox"' + (item.mail_type === 'inbox' ? ' selected' : '') + '>Inbox</option>' +
                        '<option value="sent"' + (item.mail_type === 'sent' ? ' selected' : '') + '>Sent</option>' +
                    '</select>' +
                '</td>' +
                '<td>' +
                    buildSuggestionPickerHtml(index, item) +
                    '<label class="smart-import-field-label">Client (search manually)</label>' +
                    '<select class="form-control form-control-sm smart-import-client-select" id="' + clientSelectId + '" data-index="' + index + '"></select>' +
                '</td>' +
                '<td>' +
                    '<label class="smart-import-field-label">Matter (select manually)</label>' +
                    '<select class="form-control form-control-sm smart-import-matter-select" id="' + matterSelectId + '" data-index="' + index + '"><option value="">Select matter</option></select>' +
                '</td>' +
                '<td>' +
                    '<div class="' + confidenceClass(item.confidence) + '">' + confidenceLabel(item.confidence) + '</div>' +
                    '<small class="text-muted">' + escapeHtml(matchedByLabel(item.matched_by)) + '</small>' +
                '</td>' +
                '<td class="text-center">' +
                    '<input type="checkbox" class="include-checkbox" data-index="' + index + '"' + (item.include ? ' checked' : '') + '>' +
                '</td>' +
                '<td class="text-center">' +
                    '<button type="button" class="btn btn-sm btn-outline-primary confirm-row-btn" data-index="' + index + '" title="Confirm this email only">Confirm row</button>' +
                '</td>';

            tbody.appendChild(tr);
            initClientSelect(clientSelectId, index, item);
            if (item.selected_client_id) {
                loadMattersForRow(index, item.selected_client_id, item.selected_client_matter_id);
            }
        });

        bindReviewEvents();
    }

    function setClientSelectValue(index, clientId, label, extra) {
        extra = extra || {};
        var ts = state.clientSelects[index];
        if (!ts || !clientId) {
            return;
        }
        var valueKey = 'client-' + clientId;
        if (!ts.options[valueKey]) {
            ts.addOption({
                id: valueKey,
                cid: clientId,
                name: label || ('Client #' + clientId),
                email: extra.email || '',
                client_id: extra.client_ref || '',
                record_type: extra.record_type || 'client',
            });
        }
        ts.setValue(valueKey, true);
        state.items[index].selected_client_id = clientId;
        if (extra.record_type) {
            state.items[index].selected_record_type = extra.record_type;
        }
    }

    function applySuggestion(index, suggestion) {
        if (!suggestion) {
            return;
        }
        setClientSelectValue(index, suggestion.client_id, suggestion.client_name || suggestion.client_ref, {
            email: suggestion.email,
            client_ref: suggestion.client_ref,
            record_type: suggestion.record_type,
        });
        loadMattersForRow(index, suggestion.client_id, suggestion.client_matter_id);
        state.items[index].include = true;
        var checkbox = document.querySelector('.include-checkbox[data-index="' + index + '"]');
        if (checkbox) {
            checkbox.checked = true;
        }
        syncSelectAllCheckbox();
    }

    function initClientSelect(selectId, index, item) {
        if (typeof initTS !== 'function' || typeof buildGetAllClientsTomSelectConfig !== 'function') {
            return;
        }

        var el = document.getElementById(selectId);
        if (!el) return;

        var ts = initTS(el, buildGetAllClientsTomSelectConfig({
            url: config.urls.getAllClients,
            dropdownParent: 'body',
            placeholder: 'Search client by name, email, ref...',
            onChange: function (value) {
                if (!value) {
                    state.items[index].selected_client_id = null;
                    state.items[index].selected_client_matter_id = null;
                    loadMattersForRow(index, null, null);
                    return;
                }
                var option = this.options[value] || {};
                if (option.locked) {
                    this.clear(true);
                    if (typeof window.openCrmAccessModal === 'function') {
                        window.openCrmAccessModal(option);
                    }
                    return;
                }
                var cid = extractClientIdFromTomSelectValue(value, this);
                state.items[index].selected_client_id = cid;
                state.items[index].selected_client_matter_id = null;
                if (option.record_type) {
                    state.items[index].selected_record_type = option.record_type;
                }
                loadMattersForRow(index, cid, null);
            },
        }));

        state.clientSelects[index] = ts;

        if (item.suggested_client_id) {
            var suggestion = (item.suggestions || []).find(function (s) {
                return s.client_id === item.suggested_client_id;
            }) || {};
            setClientSelectValue(index, item.suggested_client_id, suggestion.client_name || suggestion.client_ref, {
                email: suggestion.email,
                client_ref: suggestion.client_ref,
                record_type: suggestion.record_type || item.suggested_record_type,
            });
        }
    }

    function extractClientIdFromTomSelectValue(value, tsInstance) {
        if (!value) return null;
        var option = tsInstance && tsInstance.options ? tsInstance.options[value] : null;
        if (option && option.cid != null && option.cid !== '') {
            var fromCid = parseInt(option.cid, 10);
            if (!isNaN(fromCid)) {
                return fromCid;
            }
        }
        var str = String(value);
        if (str.indexOf('client-') === 0) {
            var fromKey = parseInt(str.slice(7), 10);
            if (!isNaN(fromKey)) {
                return fromKey;
            }
        }
        return null;
    }

    async function loadMattersForRow(index, clientId, selectedMatterId) {
        var matterSelect = document.getElementById('matter-select-' + index);
        if (!matterSelect || !clientId) {
            if (matterSelect) {
                matterSelect.innerHTML = '<option value="">Select client first</option>';
            }
            return;
        }

        matterSelect.innerHTML = '<option value="">Loading...</option>';

        try {
            var body = new FormData();
            body.append('_token', config.csrfToken);
            body.append('client_id', clientId);

            var response = await fetch(config.urls.listMatters, {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: body,
            });
            var data = await response.json();
            var matters = data.clientMatetrs || [];

            matterSelect.innerHTML = '<option value="">Select matter</option>';
            matters.forEach(function (matter) {
                var option = document.createElement('option');
                option.value = matter.id;
                option.textContent = (matter.client_unique_matter_no || 'Matter') +
                    (matter.title ? ' — ' + matter.title : '');
                if (selectedMatterId && parseInt(selectedMatterId, 10) === parseInt(matter.id, 10)) {
                    option.selected = true;
                }
                matterSelect.appendChild(option);
            });

            if (selectedMatterId) {
                state.items[index].selected_client_matter_id = parseInt(selectedMatterId, 10);
            }
        } catch (error) {
            matterSelect.innerHTML = '<option value="">Failed to load matters</option>';
        }
    }

    function bindReviewEvents() {
        document.querySelectorAll('.toggle-preview').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var target = document.getElementById(btn.getAttribute('data-target'));
                if (target) target.classList.toggle('show');
            });
        });

        document.querySelectorAll('.mail-type-select').forEach(function (select) {
            select.addEventListener('change', function () {
                var index = parseInt(select.getAttribute('data-index'), 10);
                state.items[index].mail_type = select.value;
            });
        });

        document.querySelectorAll('.smart-import-matter-select').forEach(function (select) {
            select.addEventListener('change', function () {
                var index = parseInt(select.getAttribute('data-index'), 10);
                state.items[index].selected_client_matter_id = select.value ? parseInt(select.value, 10) : null;
            });
        });

        document.querySelectorAll('.include-checkbox').forEach(function (checkbox) {
            checkbox.addEventListener('change', function () {
                var index = parseInt(checkbox.getAttribute('data-index'), 10);
                state.items[index].include = checkbox.checked;
                syncSelectAllCheckbox();
            });
        });

        document.querySelectorAll('.suggestion-pick-select').forEach(function (select) {
            select.addEventListener('change', function () {
                var index = parseInt(select.getAttribute('data-index'), 10);
                var sIdx = select.value;
                if (sIdx === '') {
                    return;
                }
                var suggestion = (state.items[index].suggestions || [])[parseInt(sIdx, 10)];
                applySuggestion(index, suggestion);
            });
        });

        document.querySelectorAll('.confirm-row-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var index = parseInt(btn.getAttribute('data-index'), 10);
                var item = state.items[index];
                if (!item) {
                    return;
                }
                item.include = true;
                var checkbox = document.querySelector('.include-checkbox[data-index="' + index + '"]');
                if (checkbox) {
                    checkbox.checked = true;
                }
                confirmImports([item]);
            });
        });

        var selectAll = $('smart-import-select-all');
        if (selectAll) {
            selectAll.onchange = function () {
                var checked = selectAll.checked;
                state.items.forEach(function (item, index) {
                    item.include = checked;
                    var cb = document.querySelector('.include-checkbox[data-index="' + index + '"]');
                    if (cb) {
                        cb.checked = checked;
                    }
                });
            };
        }

        syncSelectAllCheckbox();

        var confirmHighBtn = $('smart-import-confirm-high-btn');
        var confirmSelectedBtn = $('smart-import-confirm-selected-btn');
        var resetBtn = $('smart-import-reset-btn');

        if (confirmHighBtn) {
            confirmHighBtn.onclick = function () {
                state.items.forEach(function (item, index) {
                    item.include = !!item.is_high_confidence;
                    var checkbox = document.querySelector('.include-checkbox[data-index="' + index + '"]');
                    if (checkbox) checkbox.checked = item.include;
                });
                syncSelectAllCheckbox();
                confirmImports(state.items.filter(function (item) {
                    return item.include && item.is_high_confidence;
                }));
            };
        }

        if (confirmSelectedBtn) {
            confirmSelectedBtn.onclick = function () {
                confirmImports(state.items.filter(function (item) {
                    return item.include;
                }));
            };
        }

        if (resetBtn) {
            resetBtn.onclick = resetPage;
        }
    }

    function syncSelectAllCheckbox() {
        var selectAll = $('smart-import-select-all');
        if (!selectAll || !state.items.length) {
            return;
        }
        var allChecked = state.items.every(function (item) { return item.include; });
        var noneChecked = state.items.every(function (item) { return !item.include; });
        selectAll.checked = allChecked;
        selectAll.indeterminate = !allChecked && !noneChecked;
    }

    function setConfirmingUi(isConfirming, importCount, filesLabel) {
        state.isConfirming = isConfirming;
        ['smart-import-confirm-high-btn', 'smart-import-confirm-selected-btn', 'smart-import-reset-btn'].forEach(function (id) {
            var btn = $(id);
            if (btn) {
                btn.disabled = isConfirming || state.isAnalyzing;
            }
        });
        document.querySelectorAll('.confirm-row-btn').forEach(function (btn) {
            btn.disabled = isConfirming || state.isAnalyzing;
        });
        updateAnalyzeButton();

        if (isConfirming) {
            showSmartImportLoading(
                importCount === 1 ? 'Importing email' : 'Importing ' + importCount + ' emails',
                'Saving selected emails to client records. This may take a moment…',
                filesLabel || '',
                18
            );
        } else {
            hideSmartImportLoading();
        }
    }

    async function confirmImports(itemsToImport) {
        if (state.isConfirming) {
            return;
        }
        if (!itemsToImport.length) {
            setStatus($('smart-import-confirm-status'), 'Select at least one email to import.', 'error');
            return;
        }

        var incomplete = itemsToImport.filter(function (item) {
            return !item.selected_client_id || !item.selected_client_matter_id;
        });
        if (incomplete.length) {
            setStatus(
                $('smart-import-confirm-status'),
                incomplete.length + ' selected email(s) still need a client and matter.',
                'error'
            );
            return;
        }

        var statusEl = $('smart-import-confirm-status');
        var importCount = itemsToImport.length;
        var filesLabel = itemsToImport.map(function (item) {
            return item.filename || (item.preview && item.preview.subject) || 'Email';
        }).slice(0, 3).join(', ') + (importCount > 3 ? ' +' + (importCount - 3) + ' more' : '');

        setConfirmingUi(true, importCount, filesLabel);
        setStatus(statusEl, 'Importing ' + importCount + ' email(s)...', 'info');

        var progressTimer = setInterval(function () {
            var bar = $('smartImportLoadingProgressBar');
            if (!bar || !state.isConfirming) {
                return;
            }
            var current = parseFloat(bar.style.width) || 18;
            if (current < 92) {
                updateSmartImportLoading(null, null, null, current + 5);
            }
        }, 400);

        var assignments = itemsToImport.map(function (item) {
            return {
                item_id: item.id,
                client_id: item.selected_client_id,
                client_matter_id: item.selected_client_matter_id,
                mail_type: item.mail_type || 'inbox',
                record_type: item.selected_record_type || 'client',
            };
        });

        try {
            var response = await fetch(config.urls.confirm, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    batch_token: state.batchToken,
                    assignments: assignments,
                }),
            });

            var data = await response.json();
            if (!response.ok) {
                throw new Error(data.message || 'Import failed');
            }

            updateSmartImportLoading(null, 'Import finished. Updating list…', null, 100);

            var failed = data.failed || [];
            var failedCount = failed.length;
            var message = data.message || 'Import complete';
            setStatus(statusEl, message, data.saved > 0 ? (failedCount ? 'info' : 'success') : 'error');

            if (failedCount) {
                var failedNames = failed.map(function (f) {
                    return (f.filename || f.item_id) + ': ' + (f.error || 'failed');
                }).join('; ');
                setStatus(statusEl, message + ' ' + failedNames, data.saved > 0 ? 'info' : 'error');
            }

            var savedIds = data.saved_item_ids || [];
            if (savedIds.length) {
                state.items = state.items.filter(function (item) {
                    return savedIds.indexOf(item.id) === -1;
                });

                if (!state.items.length) {
                    setTimeout(resetPage, 1500);
                } else {
                    renderReviewPanel({
                        high_confidence_count: state.items.filter(function (i) { return i.is_high_confidence; }).length,
                        unmatched_count: state.items.filter(function (i) { return !i.suggested_client_id; }).length,
                    });
                }
            }
        } catch (error) {
            setStatus(statusEl, error.message || 'Import failed', 'error');
        } finally {
            clearInterval(progressTimer);
            setConfirmingUi(false);
        }
    }

    function resetPage() {
        destroyAllClientSelects();
        hideSmartImportLoading();
        state.isAnalyzing = false;
        setConfirmingUi(false);
        state.batchToken = null;
        state.items = [];
        state.selectedFiles = [];
        state.matterSelects = {};

        $('smart-import-review-panel').style.display = 'none';
        $('smart-import-upload-panel').style.display = 'block';
        $('smart-import-files').value = '';
        setStatus($('smart-import-upload-status'), '', 'info');
        setStatus($('smart-import-confirm-status'), '', 'info');
        updateAnalyzeButton();
    }

    async function checkPythonService() {
        var badge = $('python-service-status');
        if (!badge || !config.urls.checkService) return;

        try {
            var response = await fetch(config.urls.checkService, { headers: { 'Accept': 'application/json' } });
            var data = await response.json();
            if (data.status) {
                badge.className = 'badge badge-success';
                badge.textContent = 'Parser online';
            } else {
                badge.className = 'badge badge-danger';
                badge.textContent = 'Parser offline';
            }
        } catch (error) {
            badge.className = 'badge badge-danger';
            badge.textContent = 'Parser offline';
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindUploadPanel();
        checkPythonService();
        updateAnalyzeButton();
    });
})();
