/**
 * Notes module - Create, edit, view notes; getallnotes.
 * Extracted from detail-main.js - Phase 3b refactoring.
 * Requires: jQuery, ClientDetailConfig, clearEditor, setEditorContent, adjustActivityFeedHeight
 */
(function($) {
    'use strict';
    if (!$) return;

    var baseUrl = (typeof site_url !== 'undefined' ? site_url : (window.ClientDetailConfig && window.ClientDetailConfig.urls && window.ClientDetailConfig.urls.base ? window.ClientDetailConfig.urls.base : ''));

    function safeParse(r) {
        if (typeof r === 'object' && r !== null) return r;
        if (typeof r === 'string' && r.trim()) { try { return JSON.parse(r); } catch(e) { return null; } }
        return null;
    }

    function getallnotes() {
        var notesUrl = (window.ClientDetailConfig && window.ClientDetailConfig.urls && window.ClientDetailConfig.urls.getNotes) ? window.ClientDetailConfig.urls.getNotes : baseUrl + '/get-notes';
        $.ajax({
            url: notesUrl,
            type: 'GET',
            data: { clientid: window.ClientDetailConfig.clientId, type: 'client' },
            success: function(responses) {
                $('.popuploader').hide();
                $('.note_term_list').html(responses);

                if (typeof window.filterNotes === 'function') {
                    window.filterNotes();
                } else {
                    var activeTaskGroup = $('.subtab8-button.active').data('subtab8') || 'All';
                    var selectedMatter = $('.general_matter_checkbox_client_detail').is(':checked')
                        ? $('.general_matter_checkbox_client_detail').val()
                        : $('#sel_matter_id_client_detail').val();

                    if (!$('.subtab8-button.active').length) {
                        $('.subtab8-button.pill-tab[data-subtab8="All"]').addClass('active');
                        $('#noteterm-tab').find('.note-card-redesign').show();
                    } else {
                        var matterMatches = (window.ClientDetailShared && window.ClientDetailShared.noteMatchesSelectedMatter)
                            ? window.ClientDetailShared.noteMatchesSelectedMatter
                            : function (cardMatter, sel) {
                                if (!sel) return true;
                                var c = cardMatter == null ? '' : String(cardMatter).trim();
                                return c === '' || c === 'null' || c === '0' || c === String(sel);
                            };

                        $('#noteterm-tab').find('.note-card-redesign').each(function() {
                            var noteType = $(this).data('type');
                            var typeMatch = (activeTaskGroup === 'All' || noteType === activeTaskGroup);
                            var matterMatch = matterMatches($(this).attr('data-matterid'), selectedMatter);

                            if (typeMatch && matterMatch) {
                                $(this).show();
                            } else {
                                $(this).hide();
                            }
                        });
                    }
                }

                if (typeof adjustActivityFeedHeight === 'function') {
                    adjustActivityFeedHeight();
                }
            },
            error: function(xhr, status, error) {
                $('.popuploader').hide();
                console.error('[getallnotes] Failed to refresh notes:', status, error);
                if (typeof iziToast !== 'undefined' && typeof iziToast.error === 'function') {
                    iziToast.error({ message: 'Notes refreshed but some data may be outdated. Please refresh the page.', position: 'topRight' });
                } else {
                    alert('Notes refreshed but some data may be outdated. Please refresh the page.');
                }
            }
        });
    }

    window.getallnotes = getallnotes;

    var MAX_NOTE_FILES = 10;

    function noteAttachmentField($form) {
        return $form.find('.note-attachments-field').first();
    }

    function resetNoteAttachments($form) {
        var $field = noteAttachmentField($form);
        if (!$field.length) return;
        var $input = $field.find('.note-attachments-input');
        $input.val('');
        $field.find('.note-selected-files').empty();
        $field.find('.note-existing-attachments').empty();
        $field.data('files', []);
    }

    function syncNoteFileInput($field) {
        var files = $field.data('files') || [];
        var input = $field.find('.note-attachments-input')[0];
        if (!input) return;
        var dt = new DataTransfer();
        files.forEach(function(f) { dt.items.add(f); });
        try {
            input.files = dt.files;
        } catch (err) {
            // Keep native FileList if DataTransfer assignment is unsupported
        }
        renderSelectedNoteFiles($field);
    }

    function renderSelectedNoteFiles($field) {
        var files = $field.data('files') || [];
        var $list = $field.find('.note-selected-files');
        $list.empty();
        files.forEach(function(file, idx) {
            var size = file.size < 1048576
                ? (Math.round(file.size / 102.4) / 10) + ' KB'
                : (Math.round(file.size / 104857.6) / 10) + ' MB';
            var $li = $('<li class="note-file-chip"></li>');
            $li.append($('<i class="fa-solid fa-paperclip"></i>'));
            $li.append($('<span></span>').text(file.name + ' (' + size + ')'));
            var $btn = $('<button type="button" aria-label="Remove file"><i class="fa-solid fa-xmark"></i></button>');
            $btn.on('click', function() {
                var next = ($field.data('files') || []).filter(function(_, i) { return i !== idx; });
                $field.data('files', next);
                syncNoteFileInput($field);
            });
            $li.append($btn);
            $list.append($li);
        });
    }

    function addNoteFiles($field, fileList) {
        var current = $field.data('files') || [];
        var incoming = Array.prototype.slice.call(fileList || []);
        incoming.forEach(function(f) {
            if (current.length >= MAX_NOTE_FILES) return;
            current.push(f);
        });
        $field.data('files', current);
        syncNoteFileInput($field);
    }

    function renderExistingNoteAttachments($form, attachments) {
        var $field = noteAttachmentField($form);
        var $wrap = $field.find('.note-existing-attachments');
        $wrap.empty();
        (attachments || []).forEach(function(att) {
            var $chip = $('<div class="note-existing-chip"></div>');
            $chip.append($('<i class="fa-solid fa-paperclip"></i>'));
            var $link = $('<a target="_blank" rel="noopener noreferrer"></a>')
                .attr('href', att.download_url)
                .text(att.name + (att.size ? ' (' + att.size + ')' : ''));
            $chip.append($link);
            var $rm = $('<button type="button" aria-label="Remove attachment"><i class="fa-solid fa-xmark"></i></button>');
            $rm.on('click', function() {
                $form.append($('<input type="hidden" name="remove_attachment_ids[]">').val(att.id));
                $chip.remove();
            });
            $chip.append($rm);
            $wrap.append($chip);
        });
    }

    function bindNoteAttachmentUi($form) {
        var $field = noteAttachmentField($form);
        if (!$field.length || $field.data('bound')) return;
        $field.data('bound', true);
        $field.data('files', []);
        var $zone = $field.find('.note-dropzone');
        var $input = $field.find('.note-attachments-input');

        $input.on('change', function() {
            addNoteFiles($field, this.files);
        });

        $zone.on('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $zone.addClass('is-dragover');
        });
        $zone.on('dragleave drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $zone.removeClass('is-dragover');
        });
        $zone.on('drop', function(e) {
            var dt = e.originalEvent && e.originalEvent.dataTransfer;
            if (dt && dt.files && dt.files.length) {
                addNoteFiles($field, dt.files);
            }
        });
    }

    window.resetNoteAttachments = resetNoteAttachments;

    $(document).ready(function() {
        bindNoteAttachmentUi($('#create_note'));
        bindNoteAttachmentUi($('#create_note_d'));

        $(document).delegate('.create_note_d', 'click', function() {
            // Reset type select and clear any leftover phone/extra fields from a previous edit
            $('#create_note_d select[name="task_group"]').val('');
            $('#create_note_d .additional-fields-container').html('');

            $('#create_note_d').modal('show');
            $('#create_note_d input[name="mailid"]').val(0);
            $('#create_note_d input[name="title"]').val("Matter Discussion");
            $('#create_note_d input[name="noteid"]').val('');
            $('#create_note_d #appliationModalLabel').html('Create Note');
            resetNoteAttachments($('#create_note_d'));

            // Pre-select the currently active matter so notes are saved under the right matter
            var activeMatterId = $('#sel_matter_id_client_detail').val() || '';
            $('#create_note_d select[name="matter_id"]').val(activeMatterId);

            if ($(this).attr('datatype') == 'note') {
                $('.is_not_note').hide();
            } else {
                var datasubject = $(this).attr('datasubject');
                var datamailid = $(this).attr('datamailid');
                $('#create_note_d input[name="title"]').val(datasubject);
                $('#create_note_d input[name="mailid"]').val(datamailid);
                $('.is_not_note').show();
            }
        });

        $(document).delegate('.create_note', 'click', function() {
            // Reset type select and clear any leftover phone/extra fields from a previous edit
            $('#create_note select[name="task_group"]').val('');
            $('#create_note .additional-fields-container').html('');

            $('#create_note').modal('show');
            $('#create_note input[name="mailid"]').val(0);
            $('#create_note input[name="title"]').val('');
            $('#create_note #appliationModalLabel').html('Create Note');
            $('#create_note input[name="noteid"]').val('');
            resetNoteAttachments($('#create_note'));
            if (typeof clearEditor === 'function') {
                clearEditor("#create_note .tinymce-editor");
            }
            $("#create_note .tinymce-editor").val('');
            if ($(this).attr('datatype') == 'note') {
                $('.is_not_note').hide();
            } else {
                var datasubject = $(this).attr('datasubject');
                var datamailid = $(this).attr('datamailid');
                $('#create_note input[name="title"]').val(datasubject);
                $('#create_note input[name="mailid"]').val(datamailid);
                $('.is_not_note').show();
            }
        });

        if ($('#create_note').length && $('#create_note .js-data-example-ajaxcc').length) {
            if (typeof initTS === 'function' && typeof buildCrmGetRecipientsMultiTomSelectConfig === 'function' &&
                window.ClientDetailConfig && window.ClientDetailConfig.urls && window.ClientDetailConfig.urls.getRecipients) {
                $('#create_note .js-data-example-ajaxcc').each(function () {
                    initTS(this, buildCrmGetRecipientsMultiTomSelectConfig({
                        url: window.ClientDetailConfig.urls.getRecipients,
                        dropdownParent: '#create_note',
                        enableRemoteLoad: true
                    }));
                });
            } else {
                console.warn('[notes.js] CC recipient Tom Select skipped: initTS/buildCrmGetRecipientsMultiTomSelectConfig or ClientDetailConfig.urls.getRecipients missing.');
            }
        }

        $(document).on('click', '.opennoteform', function(e) {
            e.preventDefault();
            if ($('#create_note').length === 0) {
                console.error('Modal #create_note not found!');
                return;
            }
            $('#create_note').modal('show');
            $('#create_note #appliationModalLabel').html('Edit Note');
            var v = $(this).attr('data-id');
            $('#create_note input[name="noteid"]').val(v);
            resetNoteAttachments($('#create_note'));
            $('#create_note input[name="remove_attachment_ids[]"]').remove();
            $('.popuploader').show();
            $.ajax({
                url: window.ClientDetailConfig.urls.getNoteDetail,
                type: 'GET',
                dataType: 'json',
                data: { note_id: v },
                success: function(response) {
                    $('.popuploader').hide();
                    var res = safeParse(response);
                    if (!res || !res.status) return;
                    var taskGroup = res.data.task_group || '';
                    var savedPhone = (res.data.mobile_number != null ? String(res.data.mobile_number) : '').trim();

                    $('#create_note select[name="task_group"]').val(taskGroup);
                    $("#create_note .tinymce-editor").val(res.data.description);
                    if (typeof setEditorContent === 'function') {
                        setEditorContent("#create_note .tinymce-editor", res.data.description);
                    }
                    renderExistingNoteAttachments($('#create_note'), res.attachments || []);

                    $('#create_note select[name="task_group"]').trigger('change');

                    if (taskGroup === 'Call' && savedPhone) {
                        var tries = 0;
                        var maxTries = 80;
                        var interval = setInterval(function() {
                            tries++;
                            var $sel = $('#create_note #mobileNumber');
                            if ($sel.length && $sel.find('option').length > 1) {
                                $sel.val(savedPhone);
                                if ($sel.val() !== savedPhone) {
                                    var esc = $('<div/>').text(savedPhone).html();
                                    $sel.append('<option value="' + esc + '">' + esc + '</option>');
                                    $sel.val(savedPhone);
                                }
                                clearInterval(interval);
                            } else if (tries >= maxTries) {
                                clearInterval(interval);
                            }
                        }, 50);
                    }
                },
                error: function(xhr, status, error) {
                    $('.popuploader').hide();
                    console.error('Error fetching note details:', error);
                }
            });
        });

        $(document).delegate('.viewnote', 'click', function() {
            $('#view_note').modal('show');
            var v = $(this).attr('data-id');
            $('#view_note input[name="noteid"]').val(v);
            $('.popuploader').show();
            $.ajax({
                url: window.ClientDetailConfig.urls.viewNoteDetail,
                type: 'GET',
                dataType: 'json',
                data: { note_id: v },
                success: function(response) {
                    $('.popuploader').hide();
                    var res = safeParse(response);
                    if (!res || !res.status) return;
                    $('#view_note .modal-body .note_content h5').text(res.data.title);
                    $("#view_note .modal-body .note_content p").text(res.data.description);
                }
            });
        });

        $(document).delegate('.viewmatternote', 'click', function() {
            $('#view_matter_note').modal('show');
            var v = $(this).attr('data-id');
            $('#view_matter_note input[name="noteid"]').val(v);
            $('.popuploader').show();
            $.ajax({
                url: (window.ClientDetailConfig.urls.viewMatterNote || window.ClientDetailConfig.urls.viewApplicationNote),
                type: 'GET',
                dataType: 'json',
                data: { note_id: v },
                success: function(response) {
                    $('.popuploader').hide();
                    var res = safeParse(response);
                    if (!res || !res.status) return;
                    $('#view_matter_note .modal-body .note_content h5').text(res.data.title);
                    $("#view_matter_note .modal-body .note_content p").text(res.data.description);
                }
            });
        });
    });

})(typeof jQuery !== 'undefined' ? jQuery : null);
