/**
 * Documents module - Folder updates, document rename, download
 * Extracted from detail-main.js - Phase 3d refactoring.
 * Requires: jQuery, ClientDetailConfig. Uses: previewFile (global)
 */
(function($) {
    'use strict';
    if (!$) return;

    var $renameFileTargetRow = null;

    function notifyDocumentsError(message) {
        var msg = message || 'Something went wrong. Please try again.';
        if (typeof window.crmNotify !== 'undefined' && typeof window.crmNotify.error === 'function') {
            window.crmNotify.error({ message: msg });
            return;
        }
        if (typeof window.crmAlert === 'function') {
            window.crmAlert(msg);
            return;
        }
        if (typeof window.showCrmFlash === 'function') {
            window.showCrmFlash(msg, 'error');
        }
    }

    function folderUpdateErrorMessage(xhr, fallback) {
        if (xhr.responseJSON && xhr.responseJSON.message) {
            return xhr.responseJSON.message;
        }
        if (xhr.responseJSON && xhr.responseJSON.errors) {
            var firstKey = Object.keys(xhr.responseJSON.errors)[0];
            if (firstKey && xhr.responseJSON.errors[firstKey][0]) {
                return xhr.responseJSON.errors[firstKey][0];
            }
        }
        return fallback || 'Unable to update folder.';
    }

    function appendRenameFolderModalToBody() {
        var $modal = $('#renameFolderModal');
        if ($modal.length && !$modal.parent().is('body')) {
            $modal.appendTo('body');
        }
    }

    function showRenameFolderError(message) {
        $('#renameFolderName').addClass('is-invalid');
        $('#renameFolderError').text(message).show();
    }

    function clearRenameFolderError() {
        $('#renameFolderName').removeClass('is-invalid');
        $('#renameFolderError').text('').hide();
    }

    function hideRenameFolderModal() {
        var modalEl = document.getElementById('renameFolderModal');
        if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        } else if (modalEl && typeof $.fn.modal === 'function') {
            $(modalEl).modal('hide');
        }
    }

    function openRenameFolderModal(folderId, folderType, currentTitle) {
        appendRenameFolderModalToBody();
        $('#renameFolderId').val(folderId || '');
        $('#renameFolderType').val(folderType || '');
        clearRenameFolderError();
        $('#renameFolderName').val(currentTitle || '');

        var modalEl = document.getElementById('renameFolderModal');
        if (!modalEl) {
            notifyDocumentsError('Rename folder dialog is not available. Please refresh the page.');
            return false;
        }

        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        } else if (typeof $.fn.modal === 'function') {
            $(modalEl).modal('show');
        } else {
            notifyDocumentsError('Rename folder dialog is not available. Please refresh the page.');
            return false;
        }

        setTimeout(function() {
            $('#renameFolderName').trigger('focus').trigger('select');
        }, 200);
        return false;
    }

    function saveRenameFolder() {
        clearRenameFolderError();
        var folderId = $('#renameFolderId').val();
        var folderType = $('#renameFolderType').val();
        var newTitle = ($('#renameFolderName').val() || '').trim();
        if (!newTitle) {
            showRenameFolderError('This field is required');
            return;
        }
        if (!folderId) {
            showRenameFolderError('Unable to locate the folder. Please close and try again.');
            return;
        }

        var updateUrl = folderType === 'personal'
            ? window.ClientDetailConfig.urls.updatePersonalCategory
            : window.ClientDetailConfig.urls.updateVisaCategory;
        var activeTab = folderType === 'personal' ? 'personaldocuments' : 'matterdocuments';
        var $saveBtn = $('#renameFolderSaveBtn');
        $saveBtn.prop('disabled', true);

        $.ajax({
            url: updateUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                id: folderId,
                title: newTitle
            },
            success: function(response) {
                if (response && response.status) {
                    hideRenameFolderModal();
                    try {
                        localStorage.setItem('activeTab', activeTab);
                    } catch (e) {}
                    location.reload();
                    return;
                }
                showRenameFolderError((response && response.message) ? response.message : 'Unable to update folder.');
            },
            error: function(xhr) {
                showRenameFolderError(folderUpdateErrorMessage(xhr));
            },
            complete: function() {
                $saveBtn.prop('disabled', false);
            }
        });
    }

    function getFolderTitleFromButton($btn, tabButtonSelector) {
        var title = $btn.attr('data-title') || $btn.data('title') || '';
        title = String(title).trim();
        if (title) {
            return title;
        }
        return String($btn.closest('.pd-folder-tab-wrap, .md-folder-tab-wrap').find(tabButtonSelector).attr('title') || '').trim();
    }

    function getDocRowFromDrow($drow) {
        return $drow.find('.doc-row');
    }

    function appendRenameFileModalToBody() {
        var $modal = $('#renameFileModal');
        if ($modal.length && !$modal.parent().is('body')) {
            $modal.appendTo('body');
        }
    }

    function showRenameFileError(message) {
        $('#renameFileName').addClass('is-invalid');
        $('#renameFileError').text(message).show();
    }

    function clearRenameFileError() {
        $('#renameFileName').removeClass('is-invalid');
        $('#renameFileError').text('').hide();
    }

    function applyDocumentRenameSuccess($drow, $parent, obj, fileNameBase) {
        var previewUrl = obj.preview_url || obj.fileurl || ((obj.document_id || obj.Id) ? '/documents/preview/' + (obj.document_id || obj.Id) : '');
        var filetype = obj.filetype;
        var folderName = obj.folder_name;
        var fileName = obj.filename + '.' + obj.filetype;
        var $existingIcon = $parent.find('a i').first();
        var iconClass = ($existingIcon.length && $existingIcon.attr('class')) ? $existingIcon.attr('class') : 'fa-solid fa-file-image';

        $parent.empty()
            .data('id', obj.Id)
            .data('name', fileNameBase)
            .append(
                $('<a>', {
                    href: 'javascript:void(0);',
                    onclick: 'previewFile(\'' + filetype + '\', \'' + previewUrl + '\', \'' + folderName + '\')'
                }).append(
                    $('<i>', { class: iconClass }),
                    ' ',
                    $('<span>').text(fileName)
                )
            );

        if ($('#grid_' + obj.Id).length) {
            $('#grid_' + obj.Id).html(fileName);
        }

        var dropdownMenu = $drow.find('.dropdown-menu');
        dropdownMenu.find('.dropdown-item').filter(function() {
            return $(this).text().trim() === 'Preview';
        }).attr('href', previewUrl);
        $drow.find('.download-file').attr('data-filename', fileName);
        if (obj.document_id || obj.Id) {
            $drow.find('.download-file')
                .attr('data-document-id', obj.document_id || obj.Id)
                .attr('data-id', obj.document_id || obj.Id)
                .removeAttr('data-filelink');
        } else {
            $drow.find('.download-file').attr('data-filelink', previewUrl);
        }
    }

    function openRenameFileModal($drow) {
        var $parent = getDocRowFromDrow($drow);
        if (!$parent.length) {
            notifyDocumentsError('Could not find that document row. Please refresh and try again.');
            return false;
        }

        var docId = $parent.data('id');
        var fileName = $parent.data('name');
        if (!docId || !fileName) {
            notifyDocumentsError('Document details are missing. Please refresh and try again.');
            return false;
        }

        appendRenameFileModalToBody();
        $renameFileTargetRow = $drow;
        $('#renameFileDocId').val(docId);
        clearRenameFileError();
        $('#renameFileName').val(fileName);

        var modalEl = document.getElementById('renameFileModal');
        if (!modalEl) {
            notifyDocumentsError('Rename file dialog is not available. Please refresh the page.');
            return false;
        }

        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        } else if (typeof $.fn.modal === 'function') {
            $(modalEl).modal('show');
        } else {
            notifyDocumentsError('Rename file dialog is not available. Please refresh the page.');
            return false;
        }
        setTimeout(function() {
            $('#renameFileName').trigger('focus').trigger('select');
        }, 200);
        return false;
    }

    function saveRenameFile() {
        clearRenameFileError();
        var fileNameBase = ($('#renameFileName').val() || '').trim();
        var docId = $('#renameFileDocId').val();
        if (!fileNameBase) {
            showRenameFileError('This field is required');
            return;
        }
        if (!$renameFileTargetRow || !$renameFileTargetRow.length) {
            showRenameFileError('Unable to locate the file row. Please close and try again.');
            return;
        }

        var $parent = getDocRowFromDrow($renameFileTargetRow);
        if (!$parent.length) {
            showRenameFileError('Unable to locate the file row. Please close and try again.');
            return;
        }

        var $saveBtn = $('#renameFileSaveBtn');
        $saveBtn.prop('disabled', true);

        $.ajax({
            type: 'POST',
            dataType: 'json',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                filename: fileNameBase,
                id: docId
            },
            url: window.ClientDetailConfig.urls.renameDoc,
            success: function(result) {
                var obj = (typeof result === 'object' && result !== null) ? result : (typeof result === 'string' && result.trim() ? (function() {
                    try { return JSON.parse(result); } catch (e) { return null; }
                })() : null);
                if (!obj) {
                    showRenameFileError('Unexpected response from server');
                    return;
                }
                if (obj.status) {
                    applyDocumentRenameSuccess($renameFileTargetRow, $parent, obj, fileNameBase);
                    var modalEl = document.getElementById('renameFileModal');
                    if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                    } else if (modalEl && typeof $.fn.modal === 'function') {
                        $(modalEl).modal('hide');
                    }
                    $renameFileTargetRow = null;
                    var successMsg = obj.message || obj.data || 'Document renamed successfully';
                    if (typeof window.showCrmFlash === 'function') {
                        window.showCrmFlash(successMsg, 'success');
                    } else if (typeof successMessage === 'function') {
                        $('.custom-error-msg').html(successMessage(successMsg)).show();
                        $('html, body').animate({ scrollTop: 0 }, 300);
                    }
                } else {
                    showRenameFileError(obj.message || 'Please try again');
                }
            },
            error: function(xhr) {
                showRenameFileError(folderUpdateErrorMessage(xhr, 'An error occurred while saving'));
            },
            complete: function() {
                $saveBtn.prop('disabled', false);
            }
        });
    }

    $(document).ready(function() {
        // ---- Update Personal Document Folder ----
        $(document).on('click', '.update-personal-cat-title', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $btn = $(this);
            return openRenameFolderModal(
                $btn.data('id'),
                'personal',
                getFolderTitleFromButton($btn, '.subtab2-button')
            );
        });

        // ---- Update Matter Document Folder ----
        $(document).on('click', '.update-visa-cat-title', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $btn = $(this);
            return openRenameFolderModal(
                $btn.data('id'),
                'visa',
                getFolderTitleFromButton($btn, '.subtab6-button')
            );
        });

        // ---- Delete Personal Document Folder ----
        $(document).on('click', '.delete-personal-cat-title', function(e) {
            e.stopPropagation();
            e.preventDefault();
            var id = $(this).data('id');
            var title = $(this).data('title') || 'this folder';
            var warningMessage = '⚠️ WARNING: You are about to delete the folder "' + title + '"\n\n' +
                'This action will permanently remove the folder from the system.\n\n' +
                'Requirements:\n' +
                '• Folder must be empty (no documents)\n' +
                '• Only superadmin can perform this action\n\n' +
                'This action CANNOT be undone!\n\n' +
                'Do you want to proceed?';
            if (confirm(warningMessage)) {
                var confirmMessage = '⚠️ FINAL CONFIRMATION\n\n' +
                    'Are you absolutely sure you want to delete "' + title + '"?\n\n' +
                    'This will permanently delete the folder.\n\n' +
                    'Click OK to delete or Cancel to abort.';
                if (confirm(confirmMessage)) {
                    $.ajax({
                        url: window.ClientDetailConfig.urls.deletePersonalCategory,
                        method: 'POST',
                        data: {
                            _token: $('meta[name="csrf-token"]').attr('content'),
                            id: id
                        },
                        success: function(response) {
                            if (response.status) {
                                crmAlert('✓ Success: ' + response.message);
                                location.reload();
                            } else {
                                crmAlert('✗ Error: ' + (response.message || 'Failed to delete folder.'));
                            }
                        },
                        error: function(xhr) {
                            var errorMsg = 'An error occurred while deleting the folder.';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMsg = xhr.responseJSON.message;
                            }
                            crmAlert('✗ Error: ' + errorMsg);
                        }
                    });
                }
            }
        });

        // ---- Rename document: Personal + matter (modal) ----
        $(document).on('click', '.renamedoc', function(e) {
            e.preventDefault();
            e.stopPropagation();
            return openRenameFileModal($(this).closest('.drow'));
        });

        $('#renameFileSaveBtn').on('click', function(e) {
            e.preventDefault();
            saveRenameFile();
        });

        $('#renameFileName').on('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                saveRenameFile();
            }
        });

        $('#renameFileModal').on('hidden.bs.modal', function() {
            clearRenameFileError();
            $renameFileTargetRow = null;
        });

        $('#renameFolderSaveBtn').on('click', function(e) {
            e.preventDefault();
            saveRenameFolder();
        });

        $('#renameFolderName').on('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                saveRenameFolder();
            }
        });

        $('#renameFolderModal').on('hidden.bs.modal', function() {
            clearRenameFolderError();
            $('#renameFolderId').val('');
            $('#renameFolderType').val('');
        });

        // ---- Download Document ----
        function submitClassicDownloadForm(documentId, filelink, filename) {
            var form = $('<form>', {
                method: 'POST',
                action: window.ClientDetailConfig.urls.downloadDocument,
                target: '_blank',
                style: 'display: none'
            });
            var token = $('meta[name="csrf-token"]').attr('content');
            if (!token) {
                notifyDocumentsError('Security token not found. Please refresh the page and try again.');
                return false;
            }
            form.append($('<input>', { type: 'hidden', name: '_token', value: token }));
            if (documentId) {
                form.append($('<input>', { type: 'hidden', name: 'document_id', value: documentId }));
            } else {
                form.append($('<input>', { type: 'hidden', name: 'filelink', value: filelink }));
            }
            form.append($('<input>', { type: 'hidden', name: 'filename', value: filename }));
            $('body').append(form);
            form[0].submit();
            setTimeout(function() { form.remove(); }, 1000);
            return true;
        }

        $(document).on('click', '.download-file', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $this = $(this);
            // Read from current DOM attributes so updated values after rename are used (jQuery .data() caches and would return old URL)
            var documentId = $this.attr('data-document-id') || $this.data('documentId');
            var filelink = $this.attr('data-filelink') || $this.data('filelink');
            var filename = $this.attr('data-filename') || $this.data('filename');
            if ((!documentId && !filelink) || !filename) {
                notifyDocumentsError('Missing file info. Please try again.');
                return false;
            }
            if (!window.ClientDetailConfig || !window.ClientDetailConfig.urls || !window.ClientDetailConfig.urls.downloadDocument) {
                notifyDocumentsError('Download is not configured on this page. Please refresh.');
                return false;
            }

            var originalHtml = $this.html();
            $this.html('<i class="fa-solid fa-spinner fa-spin"></i> Downloading...');
            $this.prop('disabled', true);

            var token = $('meta[name="csrf-token"]').attr('content');
            if (!token) {
                notifyDocumentsError('Security token not found. Please refresh the page and try again.');
                $this.html(originalHtml).prop('disabled', false);
                return false;
            }

            var postData = { _token: token, filename: filename };
            if (documentId) {
                postData.document_id = documentId;
            } else {
                postData.filelink = filelink;
            }

            $.ajax({
                type: 'POST',
                url: window.ClientDetailConfig.urls.downloadDocument,
                data: postData,
                dataType: 'json',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                success: function(resp) {
                    if (resp && resp.url) {
                        var link = document.createElement('a');
                        link.href = resp.url;
                        link.target = '_blank';
                        link.rel = 'noopener';
                        if (resp.filename || filename) {
                            link.setAttribute('download', resp.filename || filename);
                        }
                        document.body.appendChild(link);
                        link.click();
                        link.remove();
                        return;
                    }
                    if (resp && resp.use_form) {
                        if (!submitClassicDownloadForm(documentId, filelink, filename)) {
                            notifyDocumentsError('Could not start download. Please try again.');
                        }
                        return;
                    }
                    notifyDocumentsError((resp && resp.message) ? resp.message : 'Download failed. Please try again.');
                },
                error: function(xhr) {
                    var msg = 'Download failed. Please try again.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    } else if (xhr.status === 403) {
                        msg = 'You do not have permission to download this file.';
                    } else if (xhr.status === 404) {
                        msg = 'File not found.';
                    }
                    notifyDocumentsError(msg);
                },
                complete: function() {
                    $this.html(originalHtml || 'Download').prop('disabled', false);
                }
            });
            return false;
        });

        // ---- Visual: make download-file and renamedoc clickable ----
        $('.download-file, .renamedoc').css({
            'pointer-events': 'auto',
            'cursor': 'pointer',
            'z-index': '1000'
        });
        $(document).on('mouseenter', '.download-file, .renamedoc', function() { $(this).css('background-color', '#f8f9fa'); });
        $(document).on('mouseleave', '.download-file, .renamedoc', function() { $(this).css('background-color', ''); });
    });

})(typeof jQuery !== 'undefined' ? jQuery : null);
