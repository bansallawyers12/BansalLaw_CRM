(function () {
    'use strict';

    var cfg = window.CommunicationCheckConfig || {};
    var filesInput = document.getElementById('cc-files');
    var dropzone = document.getElementById('cc-dropzone');
    var analyzeBtn = document.getElementById('cc-analyze-btn');
    var resetBtn = document.getElementById('cc-reset-btn');
    var statusEl = document.getElementById('cc-upload-status');
    var lookbackEl = document.getElementById('cc-lookback');
    var resultsPanel = document.getElementById('cc-results-panel');
    var resultsEl = document.getElementById('cc-results');
    var summaryEl = document.getElementById('cc-summary');
    var disclaimerEl = document.getElementById('cc-disclaimer');
    var overlay = document.getElementById('ccLoadingOverlay');
    var selectedFiles = [];

    if (!filesInput || !analyzeBtn) {
        return;
    }

    function setBusy(busy) {
        document.body.classList.toggle('cc-busy', !!busy);
        if (overlay) {
            overlay.classList.toggle('active', !!busy);
            overlay.setAttribute('aria-hidden', busy ? 'false' : 'true');
        }
        analyzeBtn.disabled = busy || selectedFiles.length === 0;
    }

    function setStatus(msg, isError) {
        if (!statusEl) return;
        statusEl.textContent = msg || '';
        statusEl.style.color = isError ? '#b42318' : '#5a6b7d';
    }

    function acceptFiles(fileList) {
        var max = cfg.maxFiles || 10;
        var next = Array.prototype.slice.call(fileList || []).filter(function (f) {
            return f && f.type && f.type.indexOf('image/') === 0;
        });
        selectedFiles = next.slice(0, max);
        analyzeBtn.disabled = selectedFiles.length === 0;
        if (selectedFiles.length === 0) {
            setStatus('Select one or more image screenshots.');
        } else {
            setStatus(selectedFiles.length + ' image(s) ready.');
        }
    }

    dropzone.addEventListener('click', function () {
        filesInput.click();
    });
    filesInput.addEventListener('change', function () {
        acceptFiles(filesInput.files);
    });
    ['dragenter', 'dragover'].forEach(function (evt) {
        dropzone.addEventListener(evt, function (e) {
            e.preventDefault();
            dropzone.classList.add('dragover');
        });
    });
    ['dragleave', 'drop'].forEach(function (evt) {
        dropzone.addEventListener(evt, function (e) {
            e.preventDefault();
            dropzone.classList.remove('dragover');
            if (evt === 'drop') {
                acceptFiles(e.dataTransfer.files);
            }
        });
    });

    analyzeBtn.addEventListener('click', function () {
        if (!selectedFiles.length) return;

        var fd = new FormData();
        selectedFiles.forEach(function (f) {
            fd.append('screenshots[]', f);
        });
        fd.append('lookback_days', lookbackEl ? lookbackEl.value : '30');
        fd.append('_token', cfg.csrf);

        setBusy(true);
        setStatus('Analyzing…');

        fetch(cfg.routes.analyze, {
            method: 'POST',
            body: fd,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
            credentials: 'same-origin',
        })
            .then(function (res) {
                return res.json().then(function (data) {
                    return { ok: res.ok, status: res.status, data: data };
                });
            })
            .then(function (payload) {
                setBusy(false);
                if (!payload.ok || !payload.data.status) {
                    var msg = (payload.data && payload.data.message) || 'Analyze failed.';
                    if (payload.data && payload.data.failed && payload.data.failed.length) {
                        msg += ' ' + payload.data.failed.map(function (f) {
                            return f.filename + ': ' + f.error;
                        }).join('; ');
                    }
                    setStatus(msg, true);
                    return;
                }
                renderResults(payload.data);
                if (resetBtn) resetBtn.style.display = '';
                setStatus(payload.data.message || 'Done.');
            })
            .catch(function (err) {
                setBusy(false);
                setStatus(err.message || 'Network error', true);
            });
    });

    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            selectedFiles = [];
            filesInput.value = '';
            analyzeBtn.disabled = true;
            resultsPanel.style.display = 'none';
            resultsEl.innerHTML = '';
            summaryEl.innerHTML = '';
            resetBtn.style.display = 'none';
            setStatus('');
        });
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderResults(data) {
        resultsPanel.style.display = '';
        var s = data.summary || {};
        summaryEl.innerHTML =
            '<span class="cc-pill worked">Worked: ' + (s.worked || 0) + '</span>' +
            '<span class="cc-pill logged">Logged: ' + (s.logged || 0) + '</span>' +
            '<span class="cc-pill gap">Gap: ' + (s.gap || 0) + '</span>' +
            '<span class="cc-pill unsupported">Unsupported: ' + (s.unsupported || 0) + '</span>';

        disclaimerEl.textContent = data.disclaimer || '';

        resultsEl.innerHTML = (data.items || []).map(function (item) {
            var ex = item.extracted || {};
            var score = item.score || {};
            var match = item.match || {};
            var best = score.matched_record || match.best;
            var verdict = score.verdict || 'gap';

            var extractedLine =
                (ex.direction || 'unknown') + ' ' + (ex.channel || 'message') +
                (ex.phone ? ' · ' + ex.phone : '') +
                (ex.from ? ' from ' + ex.from : '') +
                (ex.to ? ' to ' + ex.to : '') +
                (ex.subject ? ' — ' + ex.subject : '') +
                (ex.snippet ? ' · “' + (ex.snippet.length > 60 ? ex.snippet.slice(0, 60) + '…' : ex.snippet) + '”' : '') +
                (ex.datetime || ex.datetime_raw ? ' · ' + (ex.datetime || ex.datetime_raw) : '');

            var crmHtml = '';
            if (best) {
                var recordLabel;
                if (best.sms_log_id) {
                    recordLabel = 'sms_logs #' + best.sms_log_id;
                } else if (best.note_id) {
                    recordLabel = 'Call Action (notes) #' + best.note_id;
                } else if (best.activity_log_id) {
                    recordLabel = 'Call activity #' + best.activity_log_id;
                } else {
                    recordLabel = 'email_logs #' + (best.email_log_id || '?');
                }
                var staffBit = best.staff_name
                    ? (' · ' + (best.sms_log_id ? 'sent by ' : (best.note_id || best.activity_log_id ? 'logged by ' : 'assigned ')) + best.staff_name)
                    : (best.assignee_name ? ' · assigned ' + best.assignee_name : '');
                var readBit = best.email_log_id
                    ? (best.mail_is_read ? ' · read' : ' · unread')
                    : (best.direction_inferred
                        ? ' · ' + best.direction_inferred
                        : (best.status ? ' · ' + best.status : ''));
                crmHtml =
                    '<div class="cc-meta"><strong>CRM:</strong> ' +
                    escapeHtml(best.client_name || ('Client #' + (best.client_id || '?'))) +
                    (best.matter_ref ? ' / ' + escapeHtml(best.matter_ref) : '') +
                    ' · ' + escapeHtml(recordLabel) +
                    escapeHtml(staffBit) +
                    escapeHtml(readBit) +
                    (best.phone ? ' · ' + escapeHtml(best.phone) : '') +
                    (best.title ? ' — ' + escapeHtml(best.title) : '') +
                    ' · match ' + escapeHtml(best.confidence) + '%' +
                    '</div>';
                if (best.links && best.links.client) {
                    crmHtml +=
                        '<div class="cc-actions" style="margin-top:8px;">' +
                        '<a class="btn btn-sm btn-outline-primary" href="' + escapeHtml(best.links.client) + '" target="_blank" rel="noopener">Open client</a>' +
                        (best.links.email_body
                            ? '<a class="btn btn-sm btn-outline-secondary" href="' + escapeHtml(best.links.email_body) + '" target="_blank" rel="noopener">Email body</a>'
                            : '') +
                        '</div>';
                }
            } else {
                var missingTable = 'email_logs';
                if (ex.channel === 'sms') missingTable = 'sms_logs';
                if (ex.channel === 'call') missingTable = 'Call Action / activity';
                crmHtml = '<div class="cc-meta"><strong>CRM:</strong> No matching ' + missingTable + '.</div>';
                var suggestions = (score.client_suggestions || match.client_suggestions || []);
                if (suggestions.length) {
                    crmHtml += '<div class="cc-meta"><strong>Possible clients by phone:</strong> ' +
                        suggestions.slice(0, 3).map(function (s) {
                            return escapeHtml(s.client_name || ('#' + s.client_id));
                        }).join(', ') + '</div>';
                }
            }

            if (match.inbound_warning) {
                crmHtml += '<div class="cc-meta" style="color:#7a5b00;"><strong>Note:</strong> ' +
                    escapeHtml(match.inbound_warning) + '</div>';
            }
            if (match.insufficient_data && match.insufficient_reason) {
                crmHtml += '<div class="cc-meta" style="color:#7a5b00;"><strong>Call caveat:</strong> ' +
                    escapeHtml(match.insufficient_reason) + '</div>';
            }

            var follow = (score.follow_ups || []).map(function (f) {
                return '<li>' + escapeHtml(f.detail || f.type) +
                    (f.staff_name ? ' — ' + escapeHtml(f.staff_name) : '') +
                    (f.at ? ' <span class="text-muted">(' + escapeHtml(f.at) + ')</span>' : '') +
                    '</li>';
            }).join('');

            var reasons = (score.reasons || []).map(function (r) {
                return '<li>' + escapeHtml(r) + '</li>';
            }).join('');

            return (
                '<div class="cc-card">' +
                '<span class="cc-verdict ' + escapeHtml(verdict) + '">' + escapeHtml(score.label || verdict) +
                ' · confidence ' + escapeHtml(score.confidence || 0) + '%</span>' +
                '<h5>' + escapeHtml(item.filename) + '</h5>' +
                '<div class="cc-meta"><strong>Extracted:</strong> ' + escapeHtml(extractedLine) +
                (ex.app ? ' <em>(' + escapeHtml(ex.app) + ')</em>' : '') +
                ' · vision ' + escapeHtml(ex.extract_confidence || 0) + '%</div>' +
                crmHtml +
                (follow
                    ? '<div class="cc-meta"><strong>Follow-up:</strong></div><ul class="cc-followups">' + follow + '</ul>'
                    : '<div class="cc-meta"><strong>Follow-up:</strong> none in window</div>') +
                (reasons ? '<ul class="cc-followups">' + reasons + '</ul>' : '') +
                '</div>'
            );
        }).join('');
    }
})();
