/**
 * Checklist module - Application checklist, rename, upload, edit, delete
 * Extracted from detail-main.js - Phase 3c refactoring.
 * Requires: jQuery, ClientDetailConfig
 */
(function($) {
    'use strict';
    if (!$) return;

    var $renameChecklistTargetRow = null;

    function getChecklistRowFromDrow($drow) {
        var $parent = $drow.find('.personalchecklist-row');
        if (!$parent.length) {
            $parent = $drow.find('.visachecklist-row');
        }
        return $parent;
    }

    function getChecklistNameFromRow($parent) {
        return $parent.data('personalchecklistname') || $parent.data('visachecklistname') || '';
    }

    function appendRenameChecklistModalToBody() {
        var $modal = $('#renameChecklistModal');
        if ($modal.length && !$modal.parent().is('body')) {
            $modal.appendTo('body');
        }
    }

    function showRenameChecklistError(message) {
        var $input = $('#renameChecklistName');
        var $error = $('#renameChecklistError');
        $input.addClass('is-invalid');
        $error.text(message).show();
    }

    function clearRenameChecklistError() {
        $('#renameChecklistName').removeClass('is-invalid');
        $('#renameChecklistError').text('').hide();
    }

    function applyChecklistRenameSuccess($drow, obj) {
        var $parent = getChecklistRowFromDrow($drow);
        if (!$parent.length) {
            return;
        }
        var isVisa = $parent.hasClass('visachecklist-row');
        $parent.empty()
            .data('id', obj.Id)
            .data(isVisa ? 'visachecklistname' : 'personalchecklistname', obj.checklist)
            .html(obj.html || '<span style="flex: 1;">' + obj.checklist + '</span>');
        if ($('#grid_' + obj.Id).length) {
            $('#grid_' + obj.Id).html(obj.checklist);
        }
    }

    function openRenameChecklistModal($drow, fallbackName) {
        var $parent = getChecklistRowFromDrow($drow);
        if (!$parent.length) {
            console.error('Checklist row not found');
            return false;
        }

        var docId = $parent.data('id');
        var checklistName = getChecklistNameFromRow($parent) || fallbackName || '';
        if (!docId) {
            console.error('Checklist document id not found');
            return false;
        }

        appendRenameChecklistModalToBody();
        $renameChecklistTargetRow = $drow;
        $('#renameChecklistDocId').val(docId);
        clearRenameChecklistError();
        $('#renameChecklistName').val(checklistName);

        var modalEl = document.getElementById('renameChecklistModal');
        if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
            console.error('Rename checklist modal not available');
            return false;
        }

        bootstrap.Modal.getOrCreateInstance(modalEl).show();
        setTimeout(function() {
            $('#renameChecklistName').trigger('focus').trigger('select');
        }, 200);
        return false;
    }

    function saveRenameChecklist() {
        clearRenameChecklistError();
        var checklistName = ($('#renameChecklistName').val() || '').trim();
        var docId = $('#renameChecklistDocId').val();
        if (!checklistName) {
            showRenameChecklistError('This field is required');
            return;
        }
        if (!$renameChecklistTargetRow || !$renameChecklistTargetRow.length) {
            showRenameChecklistError('Unable to locate the checklist row. Please close and try again.');
            return;
        }

        var $saveBtn = $('#renameChecklistSaveBtn');
        $saveBtn.prop('disabled', true);

        $.ajax({
            type: 'POST',
            dataType: 'json',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                checklist: checklistName,
                id: docId
            },
            url: window.ClientDetailConfig.urls.renameChecklistDoc,
            success: function(result) {
                var obj = (typeof result === 'object' && result !== null) ? result : (typeof result === 'string' && result.trim() ? (function() {
                    try { return JSON.parse(result); } catch (e) { return null; }
                })() : null);
                if (!obj) {
                    showRenameChecklistError('Unexpected response from server');
                    return;
                }
                if (obj.status) {
                    applyChecklistRenameSuccess($renameChecklistTargetRow, obj);
                    var modalEl = document.getElementById('renameChecklistModal');
                    if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                    }
                    $renameChecklistTargetRow = null;
                } else {
                    showRenameChecklistError(obj.message || 'Please try again');
                }
            },
            error: function(xhr, status, error) {
                console.error('Ajax error:', error);
                showRenameChecklistError('An error occurred while saving');
            },
            complete: function() {
                $saveBtn.prop('disabled', false);
            }
        });
    }

    $(document).ready(function() {
        // ---- Application checklist: open modal ----
        // NOTE: .openchecklist handler moved to detail-main.js (same pattern as add personal / matter document checklist)

        // ---- Due date toggle ----
        $(document).delegate('.due_date_sec a.due_date_btn', 'click', function(){
            $('.due_date_sec .due_date_col').show();
            $(this).hide();
            $('.checklistdue_date').val(1);
        });

        $(document).delegate('.remove_col a.remove_btn', 'click', function(){
            $('.due_date_sec .due_date_col').hide();
            $('.due_date_sec a.due_date_btn').show();
            $('.checklistdue_date').val(0);
        });

        // ---- Rename checklist: Personal + matter documents (modal) ----
        $(document).on('click', '.persdocumnetlist .renamechecklist, .persdocumnetlist a.renamechecklist, .migdocumnetlist1 .renamechecklist', function(e) {
            e.preventDefault();
            e.stopPropagation();
            return openRenameChecklistModal($(this).closest('.drow'));
        });

        $(document).on('click', '.edit-checklist-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            return openRenameChecklistModal($(this).closest('.drow'), $(this).data('checklist'));
        });

        $('#renameChecklistSaveBtn').on('click', function(e) {
            e.preventDefault();
            saveRenameChecklist();
        });

        $('#renameChecklistName').on('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                saveRenameChecklist();
            }
        });

        $('#renameChecklistModal').on('hidden.bs.modal', function() {
            clearRenameChecklistError();
            $renameChecklistTargetRow = null;
        });

        // ---- Delete checklist ----
        $(document).on('click', '.delete-checklist-btn', function(e){
            e.preventDefault();
            e.stopPropagation();
            var checklistId = $(this).data('id');
            var checklistName = $(this).data('checklist');
            var $row = $(this).closest('.drow');
            if (!confirm('Are you sure you want to delete the checklist "' + checklistName + '"? This action cannot be undone.')) {
                return false;
            }
            $('.custom-error-msg').html('<span class="alert alert-info"><i class="fa-solid fa-clock"></i> Deleting checklist...</span>');
            var deleteUrl = (window.ClientDetailConfig && window.ClientDetailConfig.urls && window.ClientDetailConfig.urls.deleteChecklist) ?
                window.ClientDetailConfig.urls.deleteChecklist : (typeof site_url !== 'undefined' ? site_url + '/documents/delete-checklist' : '/documents/delete-checklist');
            $.ajax({
                type: "POST",
                url: deleteUrl,
                data: {
                    "_token": $('meta[name="csrf-token"]').attr('content'),
                    "id": checklistId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status) {
                        $('.custom-error-msg').html('<span class="alert alert-success">' + response.message + '</span>');
                        $row.remove();
                    } else {
                        $('.custom-error-msg').html('<span class="alert alert-danger">' + response.message + '</span>');
                    }
                },
                error: function(xhr, status, error) {
                    $('.custom-error-msg').html('<span class="alert alert-danger">An error occurred. Please try again.</span>');
                    console.error('Error deleting checklist:', error);
                }
            });
            return false;
        });
    });

})(typeof jQuery !== 'undefined' ? jQuery : null);
