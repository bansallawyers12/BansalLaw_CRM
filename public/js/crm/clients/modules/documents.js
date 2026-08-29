/**
 * Documents module - Folder updates, document rename, download
 * Extracted from detail-main.js - Phase 3d refactoring.
 * Requires: jQuery, ClientDetailConfig. Uses: previewFile (global)
 */
(function($) {
    'use strict';
    if (!$) return;

    var $renameFileTargetRow = null;

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
            console.error('Document row not found');
            return false;
        }

        var docId = $parent.data('id');
        var fileName = $parent.data('name');
        if (!docId || !fileName) {
            console.error('Document id or name not found');
            return false;
        }

        appendRenameFileModalToBody();
        $renameFileTargetRow = $drow;
        $('#renameFileDocId').val(docId);
        clearRenameFileError();
        $('#renameFileName').val(fileName);

        var modalEl = document.getElementById('renameFileModal');
        if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
            console.error('Rename file modal not available');
            return false;
        }

        bootstrap.Modal.getOrCreateInstance(modalEl).show();
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
                    }
                    $renameFileTargetRow = null;
                } else {
                    showRenameFileError(obj.message || 'Please try again');
                    console.error('Failed to rename document:', obj.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Ajax error:', error);
                showRenameFileError('An error occurred while saving');
            },
            complete: function() {
                $saveBtn.prop('disabled', false);
            }
        });
    }

    $(document).ready(function() {
        // ---- Update Personal Document Folder ----
        $(document).on('click', '.update-personal-cat-title', function() {
            var id = $(this).data('id');
            var newTitle = prompt('Enter new title for the folder:');
            newTitle = (newTitle || '').trim();
            if (!newTitle) {
                return;
            }
            $.ajax({
                url: window.ClientDetailConfig.urls.updatePersonalCategory,
                method: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    id: id,
                    title: newTitle
                },
                success: function(response) {
                    if (response.status) {
                        alert(response.message);
                        location.reload();
                    } else {
                        alert(response.message || 'Unable to update folder.');
                    }
                },
                error: function(xhr) {
                    alert(folderUpdateErrorMessage(xhr));
                }
            });
        });

        // ---- Update Matter Document Folder ----
        $(document).on('click', '.update-visa-cat-title', function() {
            var id = $(this).data('id');
            var newTitle = prompt('Enter new title for the folder:');
            newTitle = (newTitle || '').trim();
            if (!newTitle) {
                return;
            }
            $.ajax({
                url: window.ClientDetailConfig.urls.updateVisaCategory,
                method: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    id: id,
                    title: newTitle
                },
                success: function(response) {
                    if (response.status) {
                        alert(response.message);
                        location.reload();
                    } else {
                        alert(response.message || 'Unable to update folder.');
                    }
                },
                error: function(xhr) {
                    alert(folderUpdateErrorMessage(xhr));
                }
            });
        });

        // ---- Delete Personal Document Folder ----
        $(document).on('click', '.delete-personal-cat-title', function(e) {
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
                                alert('✓ Success: ' + response.message);
                                location.reload();
                            } else {
                                alert('✗ Error: ' + (response.message || 'Failed to delete folder.'));
                            }
                        },
                        error: function(xhr) {
                            var errorMsg = 'An error occurred while deleting the folder.';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMsg = xhr.responseJSON.message;
                            }
                            alert('✗ Error: ' + errorMsg);
                        }
                    });
                }
            }
        });

        // ---- Rename document: Personal + matter (modal) ----
        $(document).on('click', '.persdocumnetlist .renamedoc, .persdocumnetlist a.renamedoc, .migdocumnetlist1 .renamedoc', function(e) {
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

        // ---- Download Document ----
        $(document).on('click', '.download-file', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $this = $(this);
            // Read from current DOM attributes so updated values after rename are used (jQuery .data() caches and would return old URL)
            var documentId = $this.attr('data-document-id') || $this.data('documentId');
            var filelink = $this.attr('data-filelink') || $this.data('filelink');
            var filename = $this.attr('data-filename') || $this.data('filename');
            if ((!documentId && !filelink) || !filename) {
                console.error('Missing file info - documentId:', documentId, 'filelink:', filelink, 'filename:', filename);
                alert('Missing file info. Please try again.');
                return false;
            }
            $this.html('<i class="fa-solid fa-spinner fa-spin"></i> Downloading...');
            $this.prop('disabled', true);
            var form = $('<form>', {
                method: 'POST',
                action: window.ClientDetailConfig.urls.downloadDocument,
                target: '_blank',
                style: 'display: none'
            });
            var token = $('meta[name="csrf-token"]').attr('content');
            if (!token) {
                console.error('CSRF token not found');
                alert('Security token not found. Please refresh the page and try again.');
                $this.html('Download').prop('disabled', false);
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
            try {
                form[0].submit();
                setTimeout(function() {
                    $this.html('Download').prop('disabled', false);
                }, 2000);
            } catch (error) {
                console.error('Error submitting form:', error);
                alert('Error initiating download. Please try again.');
                $this.html('Download').prop('disabled', false);
            }
            setTimeout(function() { form.remove(); }, 1000);
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
