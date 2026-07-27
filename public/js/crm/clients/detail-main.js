    // Global flag to prevent redirects during page initialization
    var isInitializing = true;

    /**
     * Safely parse AJAX response - handles both pre-parsed objects (dataType:'json')
     * and raw strings, and guards against empty/invalid JSON to prevent "Unexpected end of input".
     */
    function safeParseJsonResponse(response) {
        if (typeof response === 'object' && response !== null) return response;
        if (typeof response === 'string' && response.trim()) {
            try { return JSON.parse(response); } catch(e) { console.error('Invalid JSON response:', e); return null; }
        }
        return null;
    }
    window.safeParseJsonResponse = safeParseJsonResponse;

    function formatClientDocDateTime(iso) {
        if (typeof window.formatDisplayDateTime === 'function') {
            return window.formatDisplayDateTime(iso) || '';
        }
        if (!iso) return '';
        var d = new Date(iso);
        return isNaN(d.getTime()) ? String(iso) : d.toLocaleString();
    }

    // Utilities (see utils/): Flatpickr, Editor - flatpickr-helpers.js, editor-helpers.js | DOM - dom-helpers.js (adjustActivityFeedHeight, adjustPreviewContainers, downloadFile)

    $(document).ready(function() {
        // Run on load
        adjustActivityFeedHeight();
        if (typeof scheduleClientDocumentsPanelHeightAdjust === 'function') {
            scheduleClientDocumentsPanelHeightAdjust();
        } else {
            adjustClientDocumentsPanelHeight();
        }
        adjustPreviewContainers();
        // Run on resize (for responsiveness)
        $(window).on('resize', function () {
            adjustActivityFeedHeight();
            adjustClientDocumentsPanelHeight();
            adjustPreviewContainers();
        });

       // On page load, sync matter dropdown from URL (supports .../FAM_1/noteterm — tab is not the matter ref)

        var matterRefInUrl = (window.ClientDetailShared && window.ClientDetailShared.parseClientDetailMatterRefFromUrl)
            ? window.ClientDetailShared.parseClientDetailMatterRefFromUrl()
            : '';

        if (!matterRefInUrl) {
            selectedMatter = $('#sel_matter_id_client_detail').val() || '';
        } else if (window.ClientDetailShared && window.ClientDetailShared.selectClientDetailMatterByRef) {
            selectedMatter = window.ClientDetailShared.selectClientDetailMatterByRef(matterRefInUrl);
        } else {
            var matterDropdownMatched = false;
            $('#sel_matter_id_client_detail option').each(function() {
                if ($(this).data('clientuniquematterno') === matterRefInUrl) {
                    $('#sel_matter_id_client_detail').val($(this).val()).trigger('change');
                    selectedMatter = $(this).val();
                    matterDropdownMatched = true;
                    return false;
                }
            });
            if (!matterDropdownMatched) {
                selectedMatter = $('#sel_matter_id_client_detail').val() || '';
            }
        }

        if (typeof window.filterNotes === 'function') {
            window.filterNotes();
        }

        // Set flag to false after initialization is complete

        setTimeout(function() {

            isInitializing = false;

        }, 100);



        // When Matter AI tab is clicked









        // Activity Feed Width Toggle - Moved to activity-feed.js



    });



    // REMOVED: Duplicate tab switching code - now handled by sidebar-tabs.js



    // Download document - see modules/documents.js



        //JavaScript to Show File Selection Hint

    document.addEventListener('DOMContentLoaded', function() {

        // Trigger file input click when "Add Document" button is clicked

        const addDocumentBtn = document.querySelector('.add-document-btn');
        if (addDocumentBtn) {
            addDocumentBtn.addEventListener('click', function() {

                document.querySelector('.docclientreceiptupload').click();

            });
        }



        // Show file selection hint when files are selected

        const docClientReceiptUpload = document.querySelector('.docclientreceiptupload');
        if (docClientReceiptUpload) {
            docClientReceiptUpload.addEventListener('change', function(e) {

            const files = e.target.files;

            const hintElement = document.querySelector('.file-selection-hint');



            if (files.length > 0) {

                if (files.length === 1) {

                    // Show the file name if only one file is selected

                    hintElement.textContent = `${files[0].name} selected`;

                } else {

                    // Show the number of files if multiple files are selected

                    hintElement.textContent = `${files.length} Files selected`;

                }

            } else {

                // Clear the hint if no files are selected

                hintElement.textContent = '';

            }

            });
        }





        // Trigger file input click when "Add Document" button is clicked

        const addDocumentBtn1 = document.querySelector('.add-document-btn1');
        if (addDocumentBtn1) {
            addDocumentBtn1.addEventListener('click', function() {

                document.querySelector('.docofficereceiptupload').click();

            });
        }

        // Ledger and Office Receipt drag & drop - see modules/ledger-dragdrop.js

    });



    document.addEventListener('DOMContentLoaded', function () {

        const radios = document.querySelectorAll('input[name="receipt_type"]');

        const forms = document.querySelectorAll('.form-type');



        radios.forEach(radio => {

            radio.addEventListener('change', function () {

                const $modal = $('#createreceiptmodal');
                const isQuickReceiptMode = $modal.length && $modal.data('quick-receipt-mode');

                forms.forEach(form => form.style.display = 'none');

                const selected = this.value;

                if (!isQuickReceiptMode) {
                    // Clear all forms before showing selected one (prevents data leakage between forms)
                    document.querySelectorAll('.form-type').forEach(form => {
                        // Clear input fields, but preserve hidden system fields (client_id, matter_id, etc)
                        form.querySelectorAll('input[type="text"], textarea').forEach(field => {
                            if (!field.name.includes('client_id') &&
                                !field.name.includes('matter_id') &&
                                !field.name.includes('loggedin_staffid') &&
                                !field.name.includes('loggedin_userid') && // backward compat
                                !field.name.includes('receipt_type') &&
                                !field.name.includes('client')) {
                                field.value = '';
                            }
                        });
                        // Clear select dropdowns except Legal Practitioner
                        form.querySelectorAll('select').forEach(field => {
                            if (!field.id || !field.id.includes('agent_id')) {
                                field.selectedIndex = 0;
                            }
                        });
                    });
                }

                const targetForm = document.getElementById(selected + '_form');
                if (targetForm) {
                    targetForm.style.display = 'block';
                }

                let selectedMatter;

                if ($('.general_matter_checkbox_client_detail').is(':checked')) {

                    selectedMatter = $('.general_matter_checkbox_client_detail').val();

                } else {

                    selectedMatter = $('#sel_matter_id_client_detail').val();

                }

                if (!selectedMatter && window.ClientDetailConfig && window.ClientDetailConfig.clientMatterId) {
                    selectedMatter = String(window.ClientDetailConfig.clientMatterId);
                }

                if(selected == 'office_receipt'){

                    if (!isQuickReceiptMode) {
                        listOfInvoice();
                    }

                    $('#client_matter_id_office').val(selectedMatter);

                }

                else if(selected == 'invoice_receipt'){

                    if($('#invoice_receipt_form input[name="function_type"]').val() == '' || $('#invoice_receipt_form input[name="function_type"]').val() == 'add' ) {

                        $('#invoice_receipt_form input[name="function_type"]').val("add");

                        getTopInvoiceNoFromDB(3);

                    }

                    $('#client_matter_id_invoice').val(selectedMatter);

                }

                else if(selected == 'client_receipt'){

                    if (!isQuickReceiptMode) {
                        listOfInvoice();
                        clientLedgerBalanceAmount(selectedMatter);
                    }

                    $('#client_matter_id_ledger').val(selectedMatter);

                }

            });

        });

    });



    function getTopInvoiceNoFromDB(type) {

        $.ajax({

            type:'post',

            url: window.ClientDetailConfig.urls.getTopInvoiceNo,

            sync:true,

            data: {type:type},

success: function(response){

                var obj = safeParseJsonResponse(response);
                if (!obj) return;
                $('.invoice_no').val(obj.max_receipt_id);

                $('.unique_invoice_no').text(obj.max_receipt_id);

            }

        });

    }



    function getTopReceiptValInDB(type) {

        $.ajax({

            type:'post',

            url: window.ClientDetailConfig.urls.getTopReceiptVal,

            sync:true,

            data: {type:type},

            success: function(response){

                var obj = safeParseJsonResponse(response);
                if (!obj) return;
                if(obj.receipt_type == 1){ //client receipt

                    if(obj.record_count >0){

                        $('#top_value_db').val(obj.record_count);

                    } else {

                        $('#top_value_db').val(obj.record_count);

                    }

                }



                if(obj.receipt_type == 2){ //office receipt

                    if(obj.record_count >0){

                        $('#office_top_value_db').val(obj.record_count);

                    } else {

                        $('#office_top_value_db').val(obj.record_count);

                    }

                }



                if(obj.receipt_type == 4){ //journal receipt

                    if(obj.record_count >0){

                        $('#journal_top_value_db').val(obj.record_count);

                    } else {

                        $('#journal_top_value_db').val(obj.record_count);

                    }

                }



                if(obj.receipt_type == 3){ //invoice receipt

                    if(obj.record_count >0){

                        $('#invoice_top_value_db').val(obj.record_count);

                    } else {

                        $('#invoice_top_value_db').val(obj.record_count);

                    }



                    if(obj.max_receipt_id >0){

                        var max_receipt_id = obj.max_receipt_id +1;

                        max_receipt_id = "Inv000"+max_receipt_id;

                        $('.unique_invoice_no').text(max_receipt_id);

                        $('.invoice_no').val(max_receipt_id);

                    } else {

                        var max_receipt_id = obj.max_receipt_id +1;

                        max_receipt_id = "Inv000"+max_receipt_id;

                        $('.unique_invoice_no').text(max_receipt_id);

                        $('.invoice_no').val(max_receipt_id);

                    }

                }

            }

        });

    }



    // listOfInvoice, loadInvoicesForQuickReceipt, populateQuickReceiptOfficeForm - see modules/invoices.js



    // clientLedgerBalanceAmount - see modules/accounts.js




    // downloadFile - see utils/dom-helpers.js


$(document).ready(function() {

    

    









    //Send message

    $(document).delegate('.sendmsg', 'click', function(){

        $('#sendmsgmodal').modal('show');

        var client_id = $(this).attr('data-id');

        $('#sendmsg_client_id').val(client_id);

    });



    // Tags modal: open for normal tags

    $(document).delegate('.opentagspopup', 'click', function(e){

        e.preventDefault();

        var entityId = $(this).attr('data-id');

        if (entityId) {

            $('#tags_clients #client_id').val(entityId);

            $('#tags_clients #create_new_as_red').val('0');

            $('#tags_clients #tags_red_mode_hint').hide();

            $('#tags_clients').modal('show');

        }

    });



    // Tags modal: open for red tags

    $(document).delegate('.openredtagspopup', 'click', function(e){

        e.preventDefault();

        var entityId = $(this).attr('data-id');

        if (entityId) {

            $('#tags_clients #client_id').val(entityId);

            $('#tags_clients #create_new_as_red').val('1');

            $('#tags_clients #tags_red_mode_hint').show();

            $('#tags_clients').modal('show');

        }

    });

    // Matter assignee / edit matter details modal: public/js/crm/clients/matter-assignee-modal.js (loaded before this file)

    // Convert Lead to Client modal: re-init Tom Select with dropdownParent so dropdowns render inside modal
    $(document).on('shown.bs.modal', '#convertLeadToClientModal', function(){

        var modal = this;

        $('#sel_legal_practitioner_id, #sel_person_responsible_id, #sel_person_assisting_id, #sel_office_id, #sel_matter_id').each(function(){

            initTS(this, { dropdownParent: modal, create: false, allowEmptyOption: true });

        });

    });



    // Tags modal: add tag pill(s) from input on comma or Enter

    $(document).on('keydown', '#tags_modal_container #tag_input', function(e){

        var $input = $(this);

        var val = ($input.val() || '').trim();

        if (e.which === 188 || e.which === 13) {

            e.preventDefault();

            if (val) {

                var parts = val.split(',').map(function(t){ return t.trim(); }).filter(function(t){ return t.length > 0; });

                var $container = $('#tags_modal_container .tags-pills-inner');

                var existing = [];

                $container.find('.tag-pill').each(function(){ existing.push($(this).attr('data-tag-name')); });

                parts.forEach(function(tagName){

                    if (existing.indexOf(tagName) === -1) {

                        existing.push(tagName);

                        var esc = $('<div>').text(tagName).html();

                        var isRed = ($('#tags_clients #create_new_as_red').val() === '1');

                        var redClass = isRed ? ' tag-pill--red' : '';

                        var $pill = $('<span class="tag-pill' + redClass + '" data-tag-name="' + esc + '" data-tag-red="' + (isRed ? '1' : '0') + '"><span class="tag-pill-text">' + esc + '</span><button type="button" class="tag-pill-remove" aria-label="Remove tag">&times;</button></span>');

                        $pill.insertBefore($input);

                    }

                });

                $input.val('');

                $('#tags_validation').val('1');

            }

            if (e.which === 188) return false;

        }

    });



    // Tags modal: add tag(s) from input on blur (comma-separated)

    $(document).on('blur', '#tags_modal_container #tag_input', function(){

        var $input = $(this);

        var val = ($input.val() || '').trim();

        if (!val) return;

        var parts = val.split(',').map(function(t){ return t.trim(); }).filter(function(t){ return t.length > 0; });

        if (parts.length === 0) return;

        var $container = $('#tags_modal_container .tags-pills-inner');

        var existing = [];

        $container.find('.tag-pill').each(function(){ existing.push($(this).attr('data-tag-name')); });

        parts.forEach(function(tagName){

            if (existing.indexOf(tagName) === -1) {

                existing.push(tagName);

                var isRed = ($('#tags_clients #create_new_as_red').val() === '1');

                var redClass = isRed ? ' tag-pill--red' : '';

                var esc = $('<div>').text(tagName).html();

                var $pill = $('<span class="tag-pill' + redClass + '" data-tag-name="' + esc + '" data-tag-red="' + (isRed ? '1' : '0') + '"><span class="tag-pill-text">' + esc + '</span><button type="button" class="tag-pill-remove" aria-label="Remove tag">&times;</button></span>');

                $pill.insertBefore($input);

            }

        });

        $input.val('');

        $('#tags_validation').val('1');

    });



    // Tags modal: remove tag pill on X click

    $(document).delegate('#tags_modal_container .tag-pill-remove', 'click', function(e){

        e.preventDefault();

        $(this).closest('.tag-pill').remove();

        var count = $('#tags_modal_container .tag-pill').length;

        $('#tags_validation').val(count > 0 ? '1' : '');

    });



    // Tags form: collect tags from pills and submit

    $(document).on('submit', '#stags_matter', function(e){

        var $form = $(this);

        $form.find('#tag_input').trigger('blur');

        var $container = $form.find('#tags_modal_container');

        if ($container.length) {

            e.preventDefault();

            $form.find('input[name="tag_normal[]"]').remove();

            $form.find('input[name="tag_red[]"]').remove();

            $container.find('.tag-pill').each(function(){

                var n = $(this).attr('data-tag-name');

                if (!n) return;

                var isRed = $(this).attr('data-tag-red') === '1';

                var nm = isRed ? 'tag_red[]' : 'tag_normal[]';

                $('<input type="hidden">').attr('name', nm).val(n).appendTo($form);

            });

            $form[0].submit();

        }

    });



    // Initialize Sidebar Tabs Management

        if (typeof SidebarTabs !== 'undefined' && window.ClientDetailConfig) {

            SidebarTabs.init({

                clientId: window.ClientDetailConfig.encodeId,

                matterId: window.ClientDetailConfig.matterId,

                activeTab: window.ClientDetailConfig.activeTab,

                selectedMatter: '',

                detailBaseUrl: window.ClientDetailConfig.detailBaseUrl || undefined

            });

        } else {

            console.error('[DetailMain] SidebarTabs or ClientDetailConfig not available');

        }

    

    

    // REMOVED: Duplicate popstate handler - now handled by sidebar-tabs.js



   // renderClientFundsLedger - see modules/accounts.js



    // Ledger edit (handleEditLedgerEntry, updateLedgerEntryBtn) - see modules/accounts.js

    // Document/Notes/Form subtab switching - see modules/subtabs.js

    // REMOVED: Old email filtering system (dead code - filter UI elements no longer exist)
    // The modern email interface (emails.js) now handles all email filtering





    // Initialize Activity Feed visibility on page load (Timeline tab only)

    var showFeedOnLoad = $('#activityfeed-tab').hasClass('active');

    if (window.SidebarTabs && typeof window.SidebarTabs.syncFeedGridLayout === 'function') {
        window.SidebarTabs.syncFeedGridLayout(showFeedOnLoad);
    } else if ($('.crm-container.crm-container--unified').length) {
        $('.crm-container.crm-container--unified').toggleClass('crm-container--no-feed', !showFeedOnLoad);
    }

    if (showFeedOnLoad) {

        // activityfeed tab: show feed, hide main content column
        $('#activity-feed').show();
        $('#main-content').hide();
        $('.crm-container').addClass('crm-container--activity-tab');
        if (window.ActivityFeed && typeof window.ActivityFeed.ensureTimelineFiltersVisible === 'function') {
            window.ActivityFeed.ensureTimelineFiltersVisible();
        }

        setTimeout(function() {
            adjustActivityFeedHeight();
        }, 150);

    } else {

        $('#activity-feed').hide();

    }

});
    function renderPreviewLoadingOverlay(message) {
        return `
            <div class="preview-loading-overlay" style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.96); z-index: 5;">
                <div style="text-align: center; padding: 16px;">
                    <i class="fa-solid fa-spinner fa-spin fa-2x" style="color: #4a90e2;"></i>
                    <p class="preview-loading-message" style="margin-top: 12px; margin-bottom: 0; color: #666; font-size: 14px;">${message}</p>
                </div>
            </div>
        `;
    }

    function resolvePreviewContainer(containerId) {
        var $all = $('.' + containerId);
        if (!$all.length || $all.length === 1) {
            return $all;
        }

        var $visible = $all.filter(':visible');
        if ($visible.length) {
            return $visible.first();
        }

        var $activePane = $all.filter(function() {
            return $(this).closest('.subtab6-pane.active, .subtab2-pane.active').length > 0;
        });
        if ($activePane.length) {
            return $activePane.first();
        }

        return $all.first();
    }

    function isClientDocPreviewPane($container) {
        return $container && $container.length && $container.hasClass('client-doc-preview-pane');
    }

    function getPreviewFrameHeight($container, isOfficePreview) {
        if (isClientDocPreviewPane($container)) {
            return '100%';
        }

        return 'calc(100vh - ' + (isOfficePreview ? '140' : '100') + 'px)';
    }

    function buildPreviewHeaderHtml(fileType, fileUrl, fileLabel, options) {
        options = options || {};
        var normalizedType = (fileType || '').toLowerCase().replace(/^\./, '');
        var iconClass = documentFileIconClass(normalizedType);
        var label = fileLabel || normalizedType.toUpperCase() || 'Document';
        var safeLabel = $('<div/>').text(label).html();
        var downloadUrl = fileUrl + (fileUrl.indexOf('?') >= 0 ? '&' : '?') + 'download=1';
        var showToggleList = options.showToggleList !== false;
        var isOfficePreview = normalizedType.match(/^(docx?|xlsx?|pptx?|rtf|odt|ods|odp)$/);
        var typeBadge = isOfficePreview
            ? '<span class="client-doc-preview-type-badge">' + normalizedType.toUpperCase() + '</span>'
            : '';
        var toggleBtn = showToggleList
            ? '<button type="button" class="btn btn-sm client-doc-preview-action-btn client-doc-preview-toggle-list-btn" title="Toggle document list" aria-label="Toggle document list" onclick="var $pane=$(this).closest(\'.subtab6-pane, .subtab2-pane, .subtab-pane, .not-used-layout, .tab-pane\'); $pane.toggleClass(\'hide-list-view\'); $(this).toggleClass(\'is-active\');"><i class="fa-solid fa-bars" aria-hidden="true"></i></button>'
            : '';

        return ''
            + '<div class="client-doc-preview-header">'
            + (showToggleList ? '<div class="client-doc-preview-header-start">' + toggleBtn + '</div>' : '')
            + '<div class="client-doc-preview-header-title">'
            + '<i class="fa-solid ' + iconClass + '" aria-hidden="true"></i>'
            + '<span class="client-doc-preview-filename" title="' + safeLabel + '">' + safeLabel + '</span>'
            + typeBadge
            + '</div>'
            + '<div class="client-doc-preview-header-actions">'
            + '<a href="' + fileUrl + '" target="_blank" rel="noopener" class="btn btn-sm client-doc-preview-action-btn client-doc-preview-open-btn" title="Open in new tab" aria-label="Open in new tab"><i class="fa-solid fa-up-right-from-square" aria-hidden="true"></i></a>'
            + '<a href="' + downloadUrl + '" class="btn btn-sm client-doc-preview-action-btn client-doc-preview-download-btn" title="Download file" aria-label="Download file"><i class="fa-solid fa-download" aria-hidden="true"></i></a>'
            + '</div>'
            + '</div>';
    }

    function mountIframePreview(container, options) {
        const embeddedPreviewUrl = options.embeddedPreviewUrl;
        const toolbarHtml = options.toolbarHtml || '';
        const headerHtml = options.headerHtml || '';
        const loadingMessage = options.loadingMessage || 'Loading preview…';
        const slowMessage = options.slowMessage || 'Still loading preview… this may take up to a minute.';
        const frameHeight = options.frameHeight || getPreviewFrameHeight(container, false);
        const onError = typeof options.onError === 'function' ? options.onError : function() {};

        container.html(`
            <div class="preview-content preview-content-with-loader" style="flex: 1; display: flex; flex-direction: column; overflow: hidden; width: 100%; position: relative; min-height: 0;">
                ${headerHtml}
                ${toolbarHtml}
                <div class="preview-iframe-wrap">
                    ${renderPreviewLoadingOverlay(loadingMessage)}
                    <iframe class="preview-iframe" src="${embeddedPreviewUrl}" title="Document preview" style="width: 100%; height: ${frameHeight}; border: none; background: #fff;"></iframe>
                </div>
            </div>
        `);

        const $overlay = container.find('.preview-loading-overlay');
        const $iframe = container.find('.preview-iframe');
        const $message = container.find('.preview-loading-message');
        let finished = false;

        const finishLoading = function() {
            if (finished) {
                return;
            }
            finished = true;
            clearTimeout(slowTimer);
            clearTimeout(hardTimeout);
            $overlay.stop(true, true).fadeOut(200, function() {
                $(this).remove();
            });
        };

        const slowTimer = setTimeout(function() {
            if (!finished && $message.length) {
                $message.text(slowMessage);
            }
        }, 6000);

        const hardTimeout = setTimeout(function() {
            if (!finished) {
                finished = true;
                onError();
            }
        }, 120000);

        $iframe.on('load', function() {
            finishLoading();
            if (isClientDocPreviewPane(container)) {
                if (typeof window.scheduleClientDocumentsPanelHeightAdjust === 'function') {
                    window.scheduleClientDocumentsPanelHeightAdjust();
                } else if (typeof window.adjustMatterDocPreviewHeight === 'function') {
                    window.adjustMatterDocPreviewHeight();
                } else if (typeof window.adjustPersonalDocPreviewHeight === 'function') {
                    window.adjustPersonalDocPreviewHeight();
                }
            }
        });

        $iframe.on('error', function() {
            if (!finished) {
                finished = true;
                onError();
            }
        });
    }

    function extractDocumentIdFromPreviewUrl(fileUrl) {
        if (!fileUrl) {
            return null;
        }

        const urlPath = String(fileUrl).split('?')[0];
        let match = urlPath.match(/\/documents\/preview\/(\d+)/);
        if (match) {
            return match[1];
        }

        match = urlPath.match(/\/documents\/(\d+)\/preview-signed/);
        if (match) {
            return match[1];
        }

        return null;
    }

    function setMatterDocumentPreviewActive(fileUrl, containerId, containerEl) {
        if (!containerEl) {
            const $resolved = resolvePreviewContainer(containerId);
            containerEl = $resolved.length ? $resolved[0] : document.querySelector('.' + containerId);
        }
        if (!containerEl) {
            return;
        }

        const matterTab = containerEl.closest('#matterdocuments-tab');
        if (!matterTab) {
            return;
        }

        matterTab.querySelectorAll('tr.drow.is-preview-active').forEach(function(row) {
            row.classList.remove('is-preview-active');
        });
        matterTab.querySelectorAll('.doc-row.is-preview-active').forEach(function(docRow) {
            docRow.classList.remove('is-preview-active');
        });

        const docId = extractDocumentIdFromPreviewUrl(fileUrl);
        if (!docId) {
            return;
        }

        const row = matterTab.querySelector('#id_' + docId);
        if (row) {
            row.classList.add('is-preview-active');
            const docRow = row.querySelector('.doc-row');
            if (docRow) {
                docRow.classList.add('is-preview-active');
            }
        }
    }

    function documentFileIconClass(fileType) {
        const normalizedType = (fileType || '').toLowerCase().replace(/^\./, '');
        if (/^(mp4|webm|mov|m4v|avi|mkv|ogv)$/.test(normalizedType)) {
            return 'fa-file-video';
        }
        if (/^(jpg|jpeg|png|gif|webp|bmp|tif|tiff)$/.test(normalizedType)) {
            return 'fa-file-image';
        }
        if (normalizedType === 'pdf') {
            return 'fa-file-pdf';
        }
        if (/^docx?$/.test(normalizedType)) {
            return 'fa-file-word';
        }
        return 'fa-file-image';
    }

    function previewVideoMimeType(fileType) {
        const normalizedType = (fileType || '').toLowerCase().replace(/^\./, '');
        const mimeMap = {
            mp4: 'video/mp4',
            webm: 'video/webm',
            mov: 'video/quicktime',
            m4v: 'video/x-m4v',
            avi: 'video/x-msvideo',
            mkv: 'video/x-matroska',
            ogv: 'video/ogg'
        };
        return mimeMap[normalizedType] || 'video/mp4';
    }

    function previewFile(fileType, fileUrl, containerId, fileLabel) {

        const container = resolvePreviewContainer(containerId);
        if (!container.length) {
            console.error('Preview container not found:', containerId);
            return;
        }

        setMatterDocumentPreviewActive(fileUrl, containerId, container[0]);

        if (!fileLabel) {
            const docId = extractDocumentIdFromPreviewUrl(fileUrl);
            if (docId) {
                const nameEl = document.querySelector('#id_' + docId + ' .doc-row span');
                if (nameEl) {
                    fileLabel = nameEl.textContent.trim();
                }
            }
        }

        const docId = extractDocumentIdFromPreviewUrl(fileUrl);
        if (docId) {
            const tabContainer = container.closest('.subtab6-pane, .subtab2-pane, .subtab-pane, .not-used-layout, .tab-pane');
            if (tabContainer.length) {
                tabContainer.find('.drow, .grid_list').removeClass('active-preview-doc');
                tabContainer.find('#id_' + docId + ', #gid_' + docId).addClass('active-preview-doc');
            } else {
                $('.drow, .grid_list').removeClass('active-preview-doc');
                $('#id_' + docId + ', #gid_' + docId).addClass('active-preview-doc');
            }
        }


        const embeddedPreviewUrl = fileUrl + (fileUrl.indexOf('?') >= 0 ? '&' : '?') + 'embed=1';
        const normalizedType = (fileType || '').toLowerCase();
        const isOfficePreview = normalizedType.match(/^(docx?|xlsx?|pptx?|rtf|odt|ods|odp)$/);
        const inDocPane = isClientDocPreviewPane(container);
        const previewHeaderHtml = buildPreviewHeaderHtml(fileType, fileUrl, fileLabel, {
            showToggleList: true
        });
        const mediaMaxHeight = inDocPane ? '100%' : 'calc(100vh - 300px)';

        container.html(`
            <div class="preview-content preview-content-loading" style="flex: 1; display: flex; flex-direction: column; overflow: hidden; min-height: 0;">
                ${previewHeaderHtml}
                <div class="preview-loading-body" style="flex: 1; display: flex; align-items: center; justify-content: center; min-height: 200px;">
                    <div style="text-align: center;">
                        <i class="fa-solid fa-spinner fa-spin fa-2x" style="color: #4a90e2;"></i>
                        <p style="margin-top: 10px; color: #666;">${isOfficePreview ? 'Converting document for preview…' : 'Loading preview…'}</p>
                    </div>
                </div>
            </div>
        `);

        if (typeof window.scheduleClientDocumentsPanelHeightAdjust === 'function') {
            window.scheduleClientDocumentsPanelHeightAdjust();
        } else if (typeof window.adjustMatterDocPreviewHeight === 'function') {
            window.adjustMatterDocPreviewHeight();
        } else if (typeof window.adjustPersonalDocPreviewHeight === 'function') {
            window.adjustPersonalDocPreviewHeight();
        } else if (typeof window.adjustClientDocumentsPanelHeight === 'function') {
            window.adjustClientDocumentsPanelHeight();
        }

        if (container[0] && typeof container[0].scrollIntoView === 'function' && !inDocPane) {
            container[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        const showPreviewError = function() {
            container.html(`
                <div class="preview-content" style="flex: 1; display: flex; flex-direction: column; overflow: hidden; min-height: 0;">
                    ${previewHeaderHtml}
                    <div class="preview-error-body" style="flex: 1; display: flex; align-items: center; justify-content: center; flex-direction: column;">
                        <i class="fa-solid fa-circle-exclamation fa-3x" style="color: #dc3545; margin-bottom: 15px;"></i>
                        <p style="margin-bottom: 15px;">Unable to load preview.</p>
                        <a href="${fileUrl}" target="_blank" rel="noopener" class="btn btn-primary">Open File</a>
                    </div>
                </div>
            `);
        };

        if (normalizedType.match(/(jpg|jpeg|png|gif|webp|bmp|tif|tiff)$/)) {
            const img = new Image();
            img.onload = function() {
                container.html(`
                    <div class="preview-content" style="flex: 1; display: flex; flex-direction: column; overflow: hidden; min-height: 0;">
                        ${previewHeaderHtml}
                        <div class="preview-media-body" style="flex: 1; overflow: auto; text-align: center; min-height: 0; padding: 8px;">
                            <img src="${embeddedPreviewUrl}" alt="Document Preview" style="max-width: 100%; max-height: ${mediaMaxHeight}; margin: auto; display: block;" />
                        </div>
                    </div>
                `);
                if (typeof window.scheduleClientDocumentsPanelHeightAdjust === 'function') {
                    window.scheduleClientDocumentsPanelHeightAdjust();
                } else if (typeof window.adjustMatterDocPreviewHeight === 'function') {
                    window.adjustMatterDocPreviewHeight();
                } else if (typeof window.adjustPersonalDocPreviewHeight === 'function') {
                    window.adjustPersonalDocPreviewHeight();
                }
            };
            img.onerror = showPreviewError;
            img.src = embeddedPreviewUrl;
        } else if (normalizedType === 'pdf' || isOfficePreview) {
            mountIframePreview(container, {
                embeddedPreviewUrl: embeddedPreviewUrl,
                headerHtml: previewHeaderHtml,
                toolbarHtml: '',
                loadingMessage: isOfficePreview ? 'Preparing document preview…' : 'Loading PDF preview…',
                slowMessage: isOfficePreview
                    ? 'Converting document for preview… please wait.'
                    : 'Still loading PDF… please wait.',
                frameHeight: getPreviewFrameHeight(container, isOfficePreview),
                onError: showPreviewError
            });
        } else if (normalizedType === 'eml') {
            mountIframePreview(container, {
                embeddedPreviewUrl: embeddedPreviewUrl,
                headerHtml: previewHeaderHtml,
                loadingMessage: 'Loading email preview…',
                frameHeight: getPreviewFrameHeight(container, false),
                onError: showPreviewError
            });
        } else if (normalizedType === 'txt') {
            fetch(embeddedPreviewUrl, { credentials: 'same-origin', headers: { 'Accept': 'text/plain, */*' } })
                .then(function(response) {
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }
                    return response.text();
                })
                .then(function(text) {
                    const escaped = $('<div/>').text(text).html();
                    container.html(`
                        <div class="preview-content" style="flex: 1; display: flex; flex-direction: column; overflow: hidden; min-height: 0;">
                            ${previewHeaderHtml}
                            <div class="preview-text-body" style="flex: 1; overflow: auto; width: 100%; padding: 12px; background: #fff; min-height: 0;">
                                <pre style="white-space: pre-wrap; word-wrap: break-word; font-size: 13px; margin: 0;">${escaped}</pre>
                            </div>
                        </div>
                    `);
                })
                .catch(showPreviewError);
        } else if (normalizedType === 'csv') {
            fetch(embeddedPreviewUrl, { credentials: 'same-origin', headers: { 'Accept': 'text/plain, */*' } })
                .then(function(response) {
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }
                    return response.text();
                })
                .then(function(text) {
                    const escaped = $('<div/>').text(text).html();
                    container.html(`
                        <div class="preview-content" style="flex: 1; display: flex; flex-direction: column; overflow: hidden; min-height: 0;">
                            ${previewHeaderHtml}
                            <div class="preview-text-body" style="flex: 1; overflow: auto; width: 100%; padding: 12px; background: #fff; min-height: 0;">
                                <pre style="white-space: pre-wrap; word-wrap: break-word; font-size: 13px; margin: 0;">${escaped}</pre>
                            </div>
                        </div>
                    `);
                })
                .catch(showPreviewError);
        } else if (normalizedType.match(/^(mp4|webm|mov|m4v|avi|mkv|ogv)$/)) {
            const videoMimeType = previewVideoMimeType(normalizedType);
            container.html(`
                <div class="preview-content" style="flex: 1; display: flex; flex-direction: column; overflow: hidden; min-height: 0;">
                    ${previewHeaderHtml}
                    <div class="preview-media-body" style="flex: 1; display: flex; align-items: center; justify-content: center; background: #000; min-height: 0;">
                        <video controls playsinline preload="metadata" style="max-width: 100%; max-height: ${mediaMaxHeight}; width: 100%;">
                            <source src="${embeddedPreviewUrl}" type="${videoMimeType}">
                            Your browser does not support video playback.
                        </video>
                    </div>
                </div>
            `);
            const videoEl = container.find('video')[0];
            if (videoEl) {
                videoEl.addEventListener('error', showPreviewError);
            }
        } else {
            container.html(`
                <div class="preview-content" style="flex: 1; display: flex; flex-direction: column; overflow: hidden; min-height: 0;">
                    ${previewHeaderHtml}
                    <div class="preview-error-body" style="flex: 1; display: flex; align-items: center; justify-content: center; flex-direction: column;">
                        <i class="fa-solid fa-file fa-3x" style="color: #6c757d; margin-bottom: 15px;"></i>
                        <p style="margin-bottom: 15px;">Preview not available for this file type.</p>
                        <a href="${fileUrl}" target="_blank" rel="noopener" class="btn btn-primary">Open File</a>
                    </div>
                </div>
            `);
        }
    }

    window.previewFile = previewFile;
    window.resolvePreviewContainer = resolvePreviewContainer;
    window.documentFileIconClass = documentFileIconClass;



    // Update preview container styles when the document is ready

        // Style all preview containers

        $('.preview-pane.file-preview-container').not('.client-doc-preview-pane').css({

            'display': 'flex',

            'flex-direction': 'column',

            'margin-top': '15px',

            'width': '499px',

            'min-height': '500px',

            'height': 'calc(100vh - 200px)',

            'border': '1px solid #dee2e6',

            'border-radius': '4px',

            'padding': '15px',

            'background': '#fff',

            'position': 'sticky',

            'top': '20px'

        });



        // Handle window resize

        $(window).resize(function() {

            adjustPreviewContainers();

        }).resize(); // Trigger on load




    // adjustPreviewContainers - see utils/dom-helpers.js









    jQuery(document).ready(function($){

        /** Anchor Tom Select matter-switcher dropdown below the Change Matter hero button. */
        function positionClientDetailMatterSwitchDropdown() {
            var ts = (typeof getTomSelectInstance === 'function') ? getTomSelectInstance('#sel_matter_id_client_detail') : null;
            if (!ts) return;
            var btn = document.getElementById('cdn-focus-matter-select');
            if (!btn) return;
            var r = btn.getBoundingClientRect();
            var minW = 280;
            var w = Math.max(r.width, minW);
            var maxLeft = window.innerWidth - w - 8;
            var left = Math.min(Math.max(8, r.left), maxLeft);
            var dd = ts.dropdown;
            if (dd) {
                dd.style.position = 'fixed';
                dd.style.left = left + 'px';
                dd.style.top = (r.bottom + 4) + 'px';
                dd.style.width = w + 'px';
                dd.style.zIndex = '10050';
            }
        }



        // Initialize Tom Select for the matter dropdown (body parent + JS position for hero button; matter-dropdown-wrap for long-name wrapping)
        var $matterClientDetail = $('#sel_matter_id_client_detail');
        if ($matterClientDetail.length) {
            var _tsMatterDetail = initTS($matterClientDetail[0], {
                dropdownParent: 'body',
                create: false,
                onDropdownOpen: function () {
                    requestAnimationFrame(function () {
                        positionClientDetailMatterSwitchDropdown();
                        $(window).on('scroll.matterSwitchDd resize.matterSwitchDd', positionClientDetailMatterSwitchDropdown);
                    });
                },
                onDropdownClose: function () {
                    $(window).off('scroll.matterSwitchDd resize.matterSwitchDd');
                }
            });
            // Add class for long-name option wrapping on Tom Select dropdown
            if (_tsMatterDetail && _tsMatterDetail.dropdown) {
                _tsMatterDetail.dropdown.classList.add('matter-dropdown-wrap');
            }
        }



        $('.selecttemplate').each(function () {
            initTS(this, { dropdownParent: '#emailmodal', create: false, allowEmptyOption: true });
        });



        //mail preview click update mail_is_read bit

        $('.mail_preview_modal').on('click', function(){

            var mail_report_id = $(this).attr('memail_id');

            $.ajaxSetup({

                headers: {

                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')

                }

            });

            $.ajax({

                url: window.ClientDetailConfig.urls.base + '/clients/updatemailreadbit',

                method: "POST",

                data: {mail_report_id:mail_report_id},

                dataType: 'json',

                success: function(response) {

                }

            });

        });



        //inbox mail reassign Model popup code start

        $(document).on('click', '.inbox_reassignemail_modal', function() {

            var val = $(this).attr('memail_id');

            $('#inbox_reassignemail_modal #memail_id').val(val);

            var staff_mail = $(this).attr('staff_mail') || $(this).attr('user_mail');

            $('#inbox_reassignemail_modal #staff_mail').val(staff_mail);

            var uploaded_doc_id = $(this).attr('uploaded_doc_id');

            $('#inbox_reassignemail_modal #uploaded_doc_id').val(uploaded_doc_id);

            $('#inbox_reassignemail_modal').modal('show');

        });



        //Initialize both Tom Select dropdowns

        initTS('#reassign_client_id', { create: false, dropdownParent: 'body' });

        initTS('#reassign_client_matter_id', { create: false, dropdownParent: 'body' });



        $(document).delegate('#reassign_client_id', 'change', function(){

            let selected_client_id = $(this).val();

            



            if (selected_client_id != "") {

                $('.popuploader').show();

                $.ajaxSetup({

                    headers: {

                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')

                    }

                });

                $.ajax({

                    url: window.ClientDetailConfig.urls.base + '/clients/listAllMattersWRTSelClient',

                    method: "POST",

                    data: {client_id:selected_client_id},

                    dataType: 'json',

success: function(response) {

                        $('.popuploader').hide();

                        var obj = safeParseJsonResponse(response);
                        if (!obj) return;
                        var matterlist = '<option value="">Select Client Matter</option>';

                        $.each(obj.clientMatetrs, function(index, subArray) {

                            matterlist += '<option value="'+subArray.id+'">'+subArray.title+'('+subArray.client_unique_matter_no+')</option>';

                        });

                        $('#reassign_client_matter_id').html(matterlist);

                        // Reinit Tom Select so it picks up the new options
                        initTS('#reassign_client_matter_id', { create: false, dropdownParent: 'body' });

                    }

                });

                // Enable matter dropdown immediately while AJAX loads new options
                setDisabledTS('#reassign_client_matter_id', false);

            } else {

                setDisabledTS('#reassign_client_matter_id', true);

            }

        });





        //sent mail reassign Model popup code start

        $(document).on('click', '.sent_reassignemail_modal', function() {

            var val = $(this).attr('memail_id');

            $('#sent_reassignemail_modal #memail_id').val(val);

            var staff_mail = $(this).attr('staff_mail') || $(this).attr('user_mail');

            $('#sent_reassignemail_modal #staff_mail').val(staff_mail);

            var uploaded_doc_id = $(this).attr('uploaded_doc_id');

            $('#sent_reassignemail_modal #uploaded_doc_id').val(uploaded_doc_id);

            $('#sent_reassignemail_modal').modal('show');

        });



        $('.sent_mail_preview_modal').on('click', function(){

            var memail_subject = $(this).attr('memail_subject');

            $('#sent_mail_preview_modal #memail_subject').html(memail_subject);



            var memail_message = $(this).attr('memail_message');

            $('#sent_mail_preview_modal #memail_message').html(memail_message);



            $('#sent_mail_preview_modal').modal('show');

        });



        //Initialize both Tom Select dropdowns

        initTS('#reassign_sent_client_id', { create: false, dropdownParent: 'body' });

        initTS('#reassign_sent_client_matter_id', { create: false, dropdownParent: 'body' });



        $(document).delegate('#reassign_sent_client_id', 'change', function(){

            let selected_client_id = $(this).val();

            if (selected_client_id != "") {

                $('.popuploader').show();

                $.ajaxSetup({

                    headers: {

                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')

                    }

                });

                $.ajax({

                    url: window.ClientDetailConfig.urls.base + '/clients/listAllMattersWRTSelClient',

                    method: "POST",

                    data: {client_id:selected_client_id},

                    dataType: 'json',

success: function(response) {

                        $('.popuploader').hide();

                        var obj = safeParseJsonResponse(response);
                        if (!obj) return;
                        var matterlist = '<option value="">Select Client Matter</option>';

                        $.each(obj.clientMatetrs, function(index, subArray) {

                            matterlist += '<option value="'+subArray.id+'">'+subArray.title+'('+subArray.client_unique_matter_no+')</option>';

                        });

                        $('#reassign_sent_client_matter_id').html(matterlist);

                        // Reinit Tom Select so it picks up the new options
                        initTS('#reassign_sent_client_matter_id', { create: false, dropdownParent: 'body' });

                    }

                });

                // Enable matter dropdown immediately while AJAX loads new options
                setDisabledTS('#reassign_sent_client_matter_id', false);

            } else {

                setDisabledTS('#reassign_sent_client_matter_id', true);

            }

        });















        // Handle click event on the action button

        $(document).delegate('.btn-assignstaff, .btn-assignuser, .btn-create-action', 'click', function(){

            // Get the value from the #note_description TinyMCE editor

            var note_description = getEditorContent('#note_description');



            // Preserve formatting while cleaning HTML tags and entities

            var clean_note_description = note_description

                .replace(/<br\s*\/?>/gi, '\n')  // Convert <br> tags to line breaks

                .replace(/<p[^>]*>/gi, '\n')    // Convert <p> tags to line breaks

                .replace(/<\/p>/gi, '')         // Remove closing </p> tags

                .replace(/<div[^>]*>/gi, '\n')  // Convert <div> tags to line breaks

                .replace(/<\/div>/gi, '')       // Remove closing </div> tags

                .replace(/<strong[^>]*>/gi, '**')  // Convert <strong> to ** for bold

                .replace(/<\/strong>/gi, '**')     // Close bold with **

                .replace(/<b[^>]*>/gi, '**')       // Convert <b> to ** for bold

                .replace(/<\/b>/gi, '**')          // Close bold with **

                .replace(/<em[^>]*>/gi, '*')       // Convert <em> to * for italic

                .replace(/<\/em>/gi, '*')          // Close italic with *

                .replace(/<i[^>]*>/gi, '*')        // Convert <i> to * for italic

                .replace(/<\/i>/gi, '*')           // Close italic with *

                .replace(/<u[^>]*>/gi, '__')       // Convert <u> to __ for underline

                .replace(/<\/u>/gi, '__')          // Close underline with __

                .replace(/<[^>]*>/g, '')           // Strip all remaining HTML tags

                .replace(/&nbsp;/g, ' ')           // Convert &nbsp; to regular spaces

                .replace(/&amp;/g, '&')            // Convert &amp; to &

                .replace(/&lt;/g, '<')             // Convert &lt; to <

                .replace(/&gt;/g, '>')             // Convert &gt; to >

                .replace(/&quot;/g, '"')           // Convert &quot; to "

                .replace(/&#39;/g, "'")            // Convert &#39; to '

                .replace(/\n\s*\n/g, '\n')         // Remove multiple consecutive line breaks

                .trim();                           // Remove leading/trailing whitespace



            // Display the clean value in an alert

            //alert(clean_note_description);



            // Transfer the original HTML content to the #assignnote field (preserving formatting)

            // If #assignnote is a TinyMCE editor, use setEditorContent, otherwise use val()

            if (isEditorInitialized('#assignnote')) {

                setEditorContent('#assignnote', note_description);

            } else {

                $('#assignnote').val(clean_note_description);

            }



            // Close the #create_note_d modal

            $('#create_note_d').modal('hide');



            // Show the #create_action_popup modal

            $('#create_action_popup').modal('show');

        });







        // Toggle dropdown menu on button click

        $('.dropdown-toggle').on('click', function() {

            $(this).parent().toggleClass('show');

        });



        // Close the dropdown if clicked outside

        $(document).on('click', function(e) {

            if (!$(e.target).closest('.dropdown-multi-select').length) {

                $('.dropdown-multi-select').removeClass('show');

            }

        });



        // Handle checkbox click events

        $('.checkbox-item').on('change', function() {

            var selectedValues = [];



            // Collect selected checkboxes values

            $('.checkbox-item:checked').each(function() {

                selectedValues.push($(this).val());

            });



            // Set the selected values in the hidden select dropdown

            $('#rem_cat').val(selectedValues).trigger('change');

        });



        // Handle "Select All" functionality (If needed, you can include this part)

        $('#select-all').on('change', function() {

            if ($(this).is(':checked')) {

                // Select all checkboxes

                $('.checkbox-item').prop('checked', true).trigger('change');

            } else {

                // Deselect all checkboxes

                $('.checkbox-item').prop('checked', false).trigger('change');

            }

        });





        //Matter selection - unified dropdown approach

        var selectedMatter = '';



        //Note: General matter checkbox handlers removed - now using unified dropdown approach





        //Convert lead to client popup and select matter

        $(document).delegate('#general_matter_checkbox_new', 'change', function(){

            if (this.checked) {

                setDisabledTS('#sel_matter_id', true);

                $('#sel_matter_id').removeAttr('data-valid');

            } else {

                setDisabledTS('#sel_matter_id', false);

                $('#sel_matter_id').attr('data-valid', 'required');

            }

        });
        //Client detail page Select general matter checkbox and assign matter id

        $(document).delegate('.general_matter_checkbox_client_detail', 'change', function(){

            if (this.checked) {

                setDisabledTS('#sel_matter_id_client_detail', true);

                $('#sel_matter_id_client_detail').removeAttr('data-valid');

                selectedMatter = $(this).val();

               



                var uniqueMatterNo = $(this).data('clientuniquematterno');

                

                // Get the active tab and sub tab

                var activeTab = $('.tab-button.active, .vertical-tab-button.active, .client-nav-button.active').data('tab') || 'personaldetails';

                var activeSubTab = $('.subtab-button.active').data('subtab');

                

            // Skip redirect during initialization

            if (isInitializing) {

                return;

            }

                

                // Build new URL

                var clientId = window.ClientDetailConfig.encodeId;

                var baseUrl = '/clients/detail/' + clientId;

                var currentUrl = window.location.href;



                var newUrl;

                if (selectedMatter != '' && uniqueMatterNo) {

                    // Append the new matter ID and active tab to the base URL

                    newUrl = baseUrl + '/' + uniqueMatterNo + '/' + activeTab;

                } else {

                    // If no matter is selected, redirect to the base URL with just the tab

                    newUrl = baseUrl + '/' + activeTab;

                }

                

                // Only redirect if the URL is actually changing to prevent infinite loops

                if (currentUrl.split('?')[0] !== newUrl && !currentUrl.endsWith(newUrl)) {

                    window.location.href = newUrl;

                    return; // Exit early to prevent further execution

                }



                if( activeTab == 'noteterm' ) {

                    if (typeof window.filterNotes === 'function') {
                        window.filterNotes();
                    }

                }

                else if( activeTab == 'matterdocuments') {

                    if(selectedMatter != "" ) {

                        $('#matterdocuments-tab .migdocumnetlist1').find('.drow').each(function() {

                            if ($(this).data('matterid') == selectedMatter) {

                                $(this).show();

                            } else {

                                $(this).hide();

                            }

                        });

                    }  else {

                        $(this).hide();

                    }

                }

                else if( activeTab == 'nominationdocuments') {

                    if(selectedMatter != "" ) {

                        $('#nominationdocuments-tab .migdocumnetlist1').find('.drow').each(function() {

                            if ($(this).data('matterid') == selectedMatter) {

                                $(this).show();

                            } else {

                                $(this).hide();

                            }

                        });

                    }  else {

                        $(this).hide();

                    }

                }




            } else {

                setDisabledTS('#sel_matter_id_client_detail', false);

                $('#sel_matter_id_client_detail').attr('data-valid', 'required');

                selectedMatter = "";

            }

        });



        //Select matter drop down chnage

        $('#sel_matter_id_client_detail').on('change', function() {

            selectedMatter = $(this).val();

            var uniqueMatterNo = $(this).find('option:selected').data('clientuniquematterno');

            var currentUrl = window.location.href;

            // Get the active tab

            var activeTab = $('.tab-button.active, .vertical-tab-button.active, .client-nav-button.active').data('tab') || 'personaldetails';



             // Get the active sub tab

            var activeSubTab = $('.subtab-button.active').data('subtab');

            

        // Skip redirect during initialization

        if (isInitializing) {

            return;

        }



        // Prevent redirect when "Select Matters" placeholder is selected

        if (selectedMatter === '' || selectedMatter === null) {


            return;

        }



        // Split the URL into segments

        var urlSegments = currentUrl.split('/');

        var baseUrl;

        var clientId = window.ClientDetailConfig.encodeId;

        

        // Build new URL with matter and tab

        baseUrl = '/clients/detail/' + clientId;



        var newUrl;

        if (selectedMatter != '' && uniqueMatterNo) {

            // Append the new matter ID and active tab to the base URL

            newUrl = baseUrl + '/' + uniqueMatterNo + '/' + activeTab;

        } else {

            // If no matter is selected, redirect to the base URL with just the tab

            newUrl = baseUrl + '/' + activeTab;

        }

        

        // Only redirect if the URL is actually changing to prevent infinite loops

        if (currentUrl.split('?')[0] !== newUrl && !currentUrl.endsWith(newUrl)) {

            window.location.href = newUrl;

            return; // Exit early to prevent further execution

        }



            if( activeTab == 'noteterm' ) {

                const activeTaskGroup = $('.subtab8-button.active').data('subtab8') || 'All';
                
                var noteMatterMatches = (window.ClientDetailShared && window.ClientDetailShared.noteMatchesSelectedMatter)
                    ? window.ClientDetailShared.noteMatchesSelectedMatter
                    : function (cardMatter, sel) {
                        if (!sel) return true;
                        return !cardMatter || cardMatter === '' || String(cardMatter) === String(sel);
                    };

                $('#noteterm-tab').find('.note-card-redesign').each(function() {
                    const noteType = $(this).data('type');
                    const typeMatch = (activeTaskGroup === 'All' || noteType === activeTaskGroup);
                    const cardMatter = $(this).attr('data-matterid');
                    const matterMatch = noteMatterMatches(cardMatter, selectedMatter);

                    if (typeMatch && matterMatch) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });

            }

            else if( activeTab == 'documentalls' && activeSubTab == 'migrationdocuments') {

                if(selectedMatter != "" ) {

                    $('#migrationdocuments-subtab .migdocumnetlist1').find('.drow').each(function() {

                        if ($(this).data('matterid') == selectedMatter) {

                            $(this).show();

                        } else {

                            $(this).hide();

                        }

                    });

                }  else {

                    $(this).hide();

                }

            }






            //var activeTab = $('.nav-item .nav-link.active');

            /*if( activeTab.attr('id') == 'noteterm-tab' ) {

                // Trigger click on the active tab

                activeTab.trigger('click');

            }

            else if( activeTab.attr('id') == 'migrationdocuments-tab' ) {

                // Trigger click on the active tab

                activeTab.trigger('click');

            }*/

        });





        //Tab click

        $(document).delegate('#client_tabs a', 'click', function(){

            // Get the target tab's href

            var target = $(this).attr('href');



            // Reset the visibility and classes

            $('.left_section').hide(); // Hide the left section by default

            $('.right_section').parent().removeClass('col-8 col-md-8 col-lg-8').addClass('col-12 col-md-12 col-lg-12');



            // Adjust based on the selected tab

            if (target === '#activities') {

                $('.left_section').show(); // Show the left section for Activities tab

                $('.left_section').removeClass('col-4 col-md-4 col-lg-4').addClass('col-4 col-md-4 col-lg-4');

                $('.right_section').parent().removeClass('col-12 col-md-12 col-lg-12').addClass('col-8 col-md-8 col-lg-8');

            }



            else if (target === '#noteterm') {

                if ($('.general_matter_checkbox_client_detail').is(':checked')) {

                    selectedMatter = $('.general_matter_checkbox_client_detail').val();

                } else {

                    selectedMatter = $('#sel_matter_id_client_detail').val();

                }

                

                if(target == '#noteterm' ){

                    if(selectedMatter != "" ) {

                        $(target).find('.note_col').each(function() {

                            if ($(this).data('matterid') == selectedMatter) {

                                $(this).show();

                            } else {

                                $(this).hide();

                            }

                        });

                    }  else {

                        //alert('Please select matter from matter drop down.');

                        $(target).find('.note_col').each(function() {

                            $(this).hide();

                        });

                    }

                }

            }



            else if (target === '#migrationdocuments') { //alert('migrationdocuments');

                if ($('.general_matter_checkbox_client_detail').is(':checked')) {

                    selectedMatter = $('.general_matter_checkbox_client_detail').val();

                } else {

                    selectedMatter = $('#sel_matter_id_client_detail').val();

                }

                if(target == '#migrationdocuments' ){

                    if(selectedMatter != "" ) {

                        $(target).find('.drow').each(function() {

                            if ($(this).data('matterid') == selectedMatter) {

                                $(this).show();

                            } else {

                                $(this).hide();

                            }

                        });

                    }  else {

                        //alert('Please select matter from matter drop down.');

                        $(target).find('.drow').each(function() {

                            $(this).hide();

                        });

                    }

                }

            }




            else if (target === '#clientdetailform') {

                var right_section_height = $('#clientdetailform').height();

                right_section_height = right_section_height+200;

                $('.right_section').css({"maxHeight":right_section_height});

            }

        });



        $(document).delegate('.general_matter_checkbox_client_detail', 'click', function(){

            // Uncheck all checkboxes

            $('.general_matter_checkbox_client_detail').not(this).prop('checked', false);

        });

        //Matter checkbox end





        //create client receipt start - Initialize Flatpickr
        initFlatpickrForClass('.report_date_fields');
        initFlatpickrForClass('.report_entry_date_fields', {
            defaultDate: new Date()
        });



        /*$(document).delegate('.openproductrinfo', 'click', function(){

            var clonedval = $('.clonedrow').html();

            $('.productitem').append('<tr class="product_field_clone">'+clonedval+'</tr>');

            // Initialize Flatpickr for new date fields
            initFlatpickrForClass('.report_date_fields,.report_entry_date_fields');

           // $('.report_entry_date_fields').last().datepicker({ format: 'dd/mm/yyyy',todayHighlight: true,autoclose: true }).datepicker('setDate', new Date());

        });*/



        $(document).delegate('.openproductrinfo', 'click', function() {

            var clonedval = $('.clonedrow').html();

            var $newRow = $('<tr class="product_field_clone">' + clonedval + '</tr>');

            // Reset invoice column (placeholder visible until Fee Transfer)

            $newRow.find('.invoice_no_cls').hide().removeAttr('data-valid').val('');

            $newRow.find('.ledger-invoice-placeholder').show();

            $('.productitem').append($newRow);

            // Initialize Flatpickr for new date fields
            initFlatpickrForClass('.report_date_fields,.report_entry_date_fields');

            toggleLedgerEftposSurchargeRow($newRow);
            toggleLedgerMetaFields($newRow);

            if (typeof window.updateLedgerRule42Visibility === 'function') {
                window.updateLedgerRule42Visibility();
            }

            //$('.report_entry_date_fields').last().datepicker({ format: 'dd/mm/yyyy',todayHighlight: true,autoclose: true }).datepicker('setDate', new Date());

        });





        $(document).delegate('.removeitems', 'click', function(){

            var $tr    = $(this).closest('.product_field_clone');

            var trclone = $('.product_field_clone').length;

            if(trclone > 0){

                $tr.remove();

            }

            grandtotalAccountTab();

            if (typeof window.updateLedgerRule42Visibility === 'function') {
                window.updateLedgerRule42Visibility();
            }

        });



        $(document).delegate('.deposit_amount_per_row,.withdraw_amount_per_row', 'keyup', function(){

            grandtotalAccountTab();

        });



        $.ajaxSetup({

            headers: {

                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')

            }

        });
        function getInfoByReceiptId11(receiptid) {

            $.ajax({

                type:'post',

                url: window.ClientDetailConfig.urls.getInfoByReceiptId,

                sync:true,

                data: {receiptid:receiptid},

                success: function(response){

                    var obj = safeParseJsonResponse(response);
                    if (!obj) return;
                    if(obj.status){

                        $('#invoice_receipt_form input[name="function_type"]').val("edit");

                        $('#createreceiptmodal').modal('show');



                        const invoiceRadio = document.querySelector('input[name="receipt_type"][value="invoice_receipt"]');

                        if (invoiceRadio) {

                            invoiceRadio.checked = true;



                            // Manually trigger the change event

                            invoiceRadio.dispatchEvent(new Event('change'));

                        }



                        if(obj.record_get){

                            var record_get = obj.record_get;

                            //var trRows_office = "";

                            var sum = 0;

                            $('.productitem_invoice tr.clonedrow_invoice').remove();

                            $('.productitem_invoice tr.product_field_clone_invoice').remove();

                            $.each(record_get, function(index, subArray) {

                                var value_sum = parseFloat(subArray.withdraw_amount);

                                if (!isNaN(value_sum)) {

                                    sum += value_sum;

                                }



                                var rowCls = index < 1 ? 'clonedrow_invoice' : 'product_field_clone_invoice';



                                //var trRows_office = '<tr class="'+rowCls+'"><td><input name="id[]" type="hidden" value="'+subArray.id+'" /><input data-valid="required" class="form-control report_date_fields_invoice" name="trans_date[]" type="text" value="'+subArray.trans_date+'" /></td><td><input data-valid="required" class="form-control report_date_fields_invoice" name="entry_date[]" type="text" value="'+subArray.entry_date+'" /></td><td><select class="form-control gst_included_cls" name="gst_included[]"><option value="">Select</option><option value="Yes">Yes</option><option value="No">No</option></select></td><td><select class="form-control payment_type_cls" name="payment_type[]"><option value="">Select</option><option value="Professional Fee">Professional Fee</option><option value="Department Charges">Department Charges</option><option value="Surcharge">Surcharge</option><option value="Disbursements">Disbursements</option><option value="Other Cost">Other Cost</option></select></td><td><input data-valid="required" class="form-control" name="description[]" type="text" value="'+subArray.description+'" /></td><td><span class="currencyinput">$</span><input data-valid="required" class="form-control withdraw_amount_invoice_per_row" name="withdraw_amount[]" type="text" value="'+subArray.withdraw_amount+'" /></td><td><a class="removeitems_invoice" href="javascript:;"><i class="fa-solid fa-xmark"></i></a></td></tr>';

                                var trRows_office = `<tr class="${rowCls}">

                                    <td>

                                        <input name="id[]" type="hidden" value="${subArray.id}" />

                                        <input data-valid="required" class="form-control report_date_fields_invoice" name="trans_date[]" type="text" value="${subArray.trans_date}" />

                                    </td>

                                    <td>

                                        <input data-valid="required" class="form-control report_date_fields_invoice" name="entry_date[]" type="text" value="${subArray.entry_date}" />

                                    </td>



                                    <td>

                                        <select class="form-control gst_included_cls" name="gst_included[]">

                                            <option value="">Select</option>

                                            <option value="Yes">Yes</option>

                                            <option value="No">No</option>

                                        </select>

                                    </td>

                                    <td>

                                        <select class="form-control payment_type_cls" name="payment_type[]">

                                            <option value="">Select</option>

                                            <option value="Professional Fee">Professional Fee</option>

                                            <option value="Department Charges">Department Charges</option>

                                            <option value="Surcharge">Surcharge</option>

                                            <option value="Disbursements">Disbursements</option>

                                            <option value="Other Cost">Other Cost</option>

                                            <option value="Discount">Discount</option>

                                        </select>

                                    </td>

                                    <td>

                                        <input data-valid="required" class="form-control" name="description[]" type="text" value="${subArray.description}" />

                                    </td>

                                    <td>

                                        <span class="currencyinput" style="display: inline-block;color: #34395e;">$</span>

                                        <input data-valid="required" style="display: inline-block;" class="form-control withdraw_amount_invoice_per_row" name="withdraw_amount[]" type="text" value="${subArray.withdraw_amount}" />

                                    </td>

                                    <td>

                                        <a class="removeitems_invoice" href="javascript:;"><i class="fa-solid fa-xmark"></i></a>

                                    </td>

                                </tr>`;



                                let $newRow = $(trRows_office);

                                $('.productitem_invoice').append($newRow);



                                // Set selected values

                                $newRow.find('.gst_included_cls').val(subArray.gst_included);

                                $newRow.find('.payment_type_cls').val(subArray.payment_type);



                                // Initialize Flatpickr for invoice date fields
                                initFlatpickrForClass('.report_date_fields_invoice');
                                initFlatpickrForClass('.report_entry_date_fields_invoice:last', {
                                    defaultDate: new Date()
                                });

                                if(index <1 ){

                                    $('.invoice_no').val(subArray.invoice_no);

                                    $('.unique_invoice_no').text(subArray.invoice_no);

                                    $('#invoice_receipt_id').val(subArray.receipt_id);

                                }

                            });

                            $('.total_withdraw_amount_all_rows_invoice').text("$"+sum.toFixed(2));

                        }

                    }

                }

            });

        }



        function prepareInvoiceEditModal() {
            var $modal = $('#createreceiptmodal');
            if (!$modal.length) {
                return;
            }

            $modal.find('.receipt-type-selector').hide();
            $modal.find('.modal-title').html('<i class="fa-solid fa-file-invoice-dollar" style="color: #17a2b8;"></i> Edit Draft Invoice');
            $('#client_receipt_form, #office_receipt_form').hide();
            $('#invoice_receipt_form').show();
        }

        function getInfoByReceiptId(receiptid) {

            if (!receiptid) {
                return;
            }

            prepareInvoiceEditModal();
            $('#invoice_receipt_form input[name="function_type"]').val('edit');

            $.ajax({

                type: 'post',

                url: window.ClientDetailConfig.urls.getInfoByReceiptId,

                sync: true,

                data: { receiptid: receiptid },

                success: function (response) {

                    var obj = safeParseJsonResponse(response);
                    if (!obj) return;
                    if (obj.status) {

                        $('#invoice_receipt_form input[name="function_type"]').val('edit');

                        $('#createreceiptmodal').modal('show');



                        const invoiceRadio = document.querySelector('input[name="receipt_type"][value="invoice_receipt"]');

                        if (invoiceRadio) {

                            invoiceRadio.checked = true;

                            invoiceRadio.dispatchEvent(new Event('change'));

                        }

                        if (obj.record_get_parent && obj.record_get_parent.length) {
                            var parentRow = obj.record_get_parent[0];
                            if (parentRow.client_matter_id) {
                                $('#client_matter_id_invoice').val(parentRow.client_matter_id);
                            }
                        }



                        if (obj.record_get) {

                            var record_get = obj.record_get;

                            var sum = 0;

                            $('.productitem_invoice tr.clonedrow_invoice').remove();

                            $('.productitem_invoice tr.product_field_clone_invoice').remove();



                            $.each(record_get, function (index, subArray) {

                                var value_sum = parseFloat(subArray.withdraw_amount);

                                if (!isNaN(value_sum)) {

                                    sum += value_sum;

                                }



                                var rowCls = index < 1 ? 'clonedrow_invoice' : 'product_field_clone_invoice';



                                var trRows_office = `<tr class="${rowCls}">

                                    <td>

                                        <input name="id[]" type="hidden" value="${subArray.id}" />

                                        <input data-valid="required" class="form-control report_date_fields_invoice" name="trans_date[]" type="text" value="${subArray.trans_date}" />

                                    </td>

                                    <td>

                                        <input data-valid="required" class="form-control report_date_fields_invoice" name="entry_date[]" type="text" value="${subArray.entry_date}" />

                                    </td>

                                    <td>

                                        <select class="form-control gst_included_cls" name="gst_included[]">

                                            <option value="">Select</option>

                                            <option value="Yes" ${subArray.gst_included === 'Yes' ? 'selected' : ''}>Yes</option>

                                            <option value="No" ${subArray.gst_included === 'No' ? 'selected' : ''}>No</option>

                                        </select>

                                    </td>

                                    <td>

                                        <select class="form-control payment_type_cls" name="payment_type[]">

                                            <option value="">Select</option>

                                            <option value="Professional Fee" ${subArray.payment_type === 'Professional Fee' ? 'selected' : ''}>Professional Fee</option>

                                            <option value="Department Charges" ${subArray.payment_type === 'Department Charges' ? 'selected' : ''}>Department Charges</option>

                                            <option value="Surcharge" ${subArray.payment_type === 'Surcharge' ? 'selected' : ''}>Surcharge</option>

                                            <option value="Disbursements" ${subArray.payment_type === 'Disbursements' ? 'selected' : ''}>Disbursements</option>

                                            <option value="Other Cost" ${subArray.payment_type === 'Other Cost' ? 'selected' : ''}>Other Cost</option>

                                            <option value="Discount" ${subArray.payment_type === 'Discount' ? 'selected' : ''}>Discount</option>

                                        </select>

                                    </td>

                                    <td>

                                        <input data-valid="required" class="form-control" name="description[]" type="text" value="${subArray.description}" />

                                    </td>

                                    <td>

                                        <span class="currencyinput" style="display: inline-block;color: #34395e;">$</span>

                                        <input data-valid="required" style="display: inline-block;" class="form-control withdraw_amount_invoice_per_row" name="withdraw_amount[]" type="text" value="${subArray.withdraw_amount}" />

                                    </td>

                                    <td>

                                        <a class="removeitems_invoice" href="javascript:;"><i class="fa-solid fa-xmark"></i></a>

                                    </td>

                                </tr>`;



                                let $newRow = $(trRows_office);

                                $('.productitem_invoice').append($newRow);



                                // Initialize Flatpickr for invoice date fields
                                initFlatpickrForClass('.report_date_fields_invoice');
                                initFlatpickrForClass('.report_entry_date_fields_invoice:last', {
                                    defaultDate: new Date()
                                });



                                if (index < 1) {

                                    $('.invoice_no').val(subArray.invoice_no);

                                    $('.unique_invoice_no').text(subArray.invoice_no);

                                    $('#invoice_receipt_id').val(subArray.receipt_id);

                                }

                            });



                            $('.total_withdraw_amount_all_rows_invoice').text("$" + sum.toFixed(2));

                        }

                    } else {
                        alert(obj.message || 'Could not load draft invoice.');
                    }

                },

                error: function() {
                    alert('Could not load draft invoice. Please try again.');
                }

            });

        }

        $(document).on('click', '.updatedraftinvoice', function(e) {
            e.preventDefault();
            e.stopPropagation();

            var receiptid = $(this).data('receiptid');
            getInfoByReceiptId(receiptid);
        });

        window.getInfoByReceiptId = getInfoByReceiptId;





        $(document).on('change', '.client_fund_ledger_type', function () {

            var $row = $(this).closest('tr');

            var ledgerType = $(this).val();



            var $depositInput = $row.find('.deposit_amount_per_row');

            var $withdrawInput = $row.find('.withdraw_amount_per_row');

            var $invoiceInput = $row.find('.invoice_no_cls');



            // Invoice show/hide based on Fee Transfer

            if (ledgerType === 'Fee Transfer') {

                $row.find('.ledger-invoice-placeholder').hide();

                $invoiceInput.show().attr('data-valid', 'required');

                listOfInvoice();

            } else {

                $row.find('.ledger-invoice-placeholder').show();

                $invoiceInput.hide().removeAttr('data-valid').val('');

            }



            if (ledgerType !== "") {

                var fundType = (ledgerType === 'Deposit') ? 'deposit' : 'withdraw';



                if (fundType === 'deposit') {

                    $depositInput.removeAttr('readonly').attr('data-valid', 'required').val("");

                    $withdrawInput.attr('readonly', 'readonly').removeAttr('data-valid').val("");

                } else if (fundType === 'withdraw') {

                    $withdrawInput.removeAttr('readonly').attr('data-valid', 'required').val("");

                    $depositInput.attr('readonly', 'readonly').removeAttr('data-valid').val("");

                } else {

                    $depositInput.attr('readonly', 'readonly').removeAttr('data-valid').val("");

                    $withdrawInput.attr('readonly', 'readonly').removeAttr('data-valid').val("");

                }

            } else {

                $depositInput.attr('readonly', 'readonly').removeAttr('data-valid').val("");

                $withdrawInput.attr('readonly', 'readonly').removeAttr('data-valid').val("");

            }

            toggleLedgerEftposSurchargeRow($row);

            toggleLedgerMetaFields($row);

            updateLedgerRule42Visibility();
        });



        function toggleLedgerMetaFields($row) {
            var ledgerType = $row.find('.client_fund_ledger_type').val();
            var pm = $row.find('.ledger-payment-method').val();
            var isDeposit = ledgerType === 'Deposit';
            var isWithdraw = ledgerType === 'Fee Transfer' || ledgerType === 'Disbursement' || ledgerType === 'Refund';

            $row.find('.ledger-payer-name').toggle(isDeposit);
            $row.find('.ledger-payee-name').toggle(isWithdraw);
            $row.find('.ledger-banking-date').toggle(isDeposit);
            $row.find('.ledger-bank-ref').toggle(isDeposit || pm === 'Cheque');
            $row.find('.ledger-cheque-no').toggle(isWithdraw && pm === 'Cheque');
            $row.find('.ledger-eft-bsb, .ledger-eft-acct-name, .ledger-eft-acct-no').toggle(isWithdraw && pm === 'Bank transfer');
        }

        window.toggleLedgerMetaFields = toggleLedgerMetaFields;



        function updateLedgerRule42Visibility() {
            var any = false;
            $('#client_receipt_form .client_fund_ledger_type').each(function () {
                if ($(this).val() === 'Fee Transfer') {
                    any = true;
                }
            });
            $('#ledger-rule42-block').toggle(any);
        }

        window.updateLedgerRule42Visibility = updateLedgerRule42Visibility;





        function toggleLedgerEftposSurchargeRow($row) {

            var pm = $row.find('.ledger-payment-method').val();

            var ledgerType = $row.find('.client_fund_ledger_type').val();

            var $block = $row.find('.ledger-eftpos-surcharge-block');

            if (pm === 'EFTPOS' && ledgerType === 'Deposit') {

                $block.show();

            } else {

                $block.hide();

                $row.find('.ledger-eftpos-surcharge-input').val('');

            }

        }



        function toggleOfficeEftposSurchargeRow($row) {

            var pm = $row.find('.office-receipt-payment-method').val();

            var $block = $row.find('.office-eftpos-surcharge-block');

            if (pm === 'EFTPOS') {

                $block.show();

            } else {

                $block.hide();

                $row.find('.office-eftpos-surcharge-input').val('');

            }

        }

        window.toggleOfficeEftposSurchargeRow = toggleOfficeEftposSurchargeRow;

        window.toggleLedgerEftposSurchargeRow = toggleLedgerEftposSurchargeRow;



        $(document).on('change', '.ledger-payment-method', function() {

            var $row = $(this).closest('tr');
            toggleLedgerEftposSurchargeRow($row);
            toggleLedgerMetaFields($row);

            grandtotalAccountTab();

        });



        $(document).on('change', '.office-receipt-payment-method', function() {

            toggleOfficeEftposSurchargeRow($(this).closest('tr'));

            grandtotalAccountTab_office();

        });



        $(document).on('keyup input', '.ledger-eftpos-surcharge-input', function() {

            grandtotalAccountTab();

        });



        $(document).on('keyup input', '.office-eftpos-surcharge-input', function() {

            grandtotalAccountTab_office();

        });





        function grandtotalAccountTab() {

            var total_deposit_amount_all_rows = 0;

            var total_withdraw_amount_all_rows = 0;



            $('.productitem tr').each(function() {

                var $row = $(this);



                // Handle deposit amount

                var depositVal = $row.find('.deposit_amount_per_row').val();

                var depositAmount = parseFloat(depositVal) || 0; // fallback to 0 if NaN

                if ($row.find('.ledger-eftpos-surcharge-block').is(':visible')) {

                    var sur = parseFloat($row.find('.ledger-eftpos-surcharge-input').val()) || 0;

                    total_deposit_amount_all_rows += depositAmount + sur;

                } else {

                    total_deposit_amount_all_rows += depositAmount;

                }



                // Handle withdraw amount

                var withdrawVal = $row.find('.withdraw_amount_per_row').val();

                var withdrawAmount = parseFloat(withdrawVal) || 0; // fallback to 0 if NaN

                total_withdraw_amount_all_rows += withdrawAmount;

            });



            $('.total_deposit_amount_all_rows').html("$" + total_deposit_amount_all_rows.toFixed(2));

            $('.total_withdraw_amount_all_rows').html("$" + total_withdraw_amount_all_rows.toFixed(2));

        }



        //create client receipt changes end





        //create invoice receipt start - Initialize Flatpickr
        initFlatpickrForClass('.report_date_fields_invoice');
        initFlatpickrForClass('.report_entry_date_fields_invoice', {
            defaultDate: new Date()
        });





        $(document).delegate('.openproductrinfo_invoice', 'click', function(){

            var clonedval_invoice = `<td>

                            <input name="id[]" type="hidden" value="" />

                            <input data-valid="required" class="form-control report_date_fields_invoice" name="trans_date[]" type="text" value="" />

                        </td>

                        <td>

                            <input data-valid="required" class="form-control report_entry_date_fields_invoice" name="entry_date[]" type="text" value="" />

                        </td>



                        <td>

                            <select class="form-control" name="gst_included[]">

                                <option value="">Select</option>

                                <option value="Yes">Yes</option>

                                <option value="No">No</option>

                            </select>

                        </td>



                        <td>

                            <select class="form-control payment_type_invoice_per_row" name="payment_type[]">

                                <option value="">Select</option>

                                <option value="Professional Fee">Professional Fee</option>

                                <option value="Department Charges">Department Charges</option>

                                <option value="Surcharge">Surcharge</option>

                                <option value="Disbursements">Disbursements</option>

                                <option value="Other Cost">Other Cost</option>

                                <option value="Discount">Discount</option>



                            </select>

                        </td>

                        <td>

                            <input data-valid="required" class="form-control" name="description[]" type="text" value="" />

                        </td>



                        <td>

                            <span class="currencyinput" style="display: inline-block;color: #34395e;">$</span>

                            <input data-valid="required" style="display: inline-block;" class="form-control withdraw_amount_invoice_per_row" name="withdraw_amount[]" type="text" value="" />

                        </td>



                        <td>

                            <a class="removeitems_invoice" href="javascript:;"><i class="fa-solid fa-xmark"></i></a>

                        </td>>`;



                //var clonedval_invoice = $('.clonedrow_invoice').html();

                $('.productitem_invoice').append('<tr class="product_field_clone_invoice">'+clonedval_invoice+'</tr>');

                // Initialize Flatpickr for invoice date fields
                initFlatpickrForClass('.report_date_fields_invoice,.report_entry_date_fields_invoice');
                initFlatpickrForClass('.report_entry_date_fields_invoice:last', {
                    defaultDate: new Date()
                });

        });



        function invoiceRowHasFilledData($row) {
            if ($.trim($row.find('input[name="id[]"]').val())) {
                return true;
            }
            if ($.trim($row.find('input[name="trans_date[]"]').val())) {
                return true;
            }
            if ($.trim($row.find('input[name="entry_date[]"]').val())) {
                return true;
            }
            if ($.trim($row.find('input[name="description[]"]').val())) {
                return true;
            }
            if ($.trim($row.find('input[name="withdraw_amount[]"]').val())) {
                return true;
            }
            var gstIncluded = $row.find('select[name="gst_included[]"]').val();
            if (gstIncluded && gstIncluded !== '') {
                return true;
            }
            var paymentType = $row.find('select[name="payment_type[]"]').val();
            if (paymentType && paymentType !== '') {
                return true;
            }
            return false;
        }

        function removeInvoiceRow($row) {
            var $tbody = $row.closest('.productitem_invoice');
            var $dataRows = $tbody.children('tr.clonedrow_invoice, tr.product_field_clone_invoice');

            if ($dataRows.length <= 1) {
                $row.find('input[name="id[]"]').val('');
                $row.find('input[type="text"]').not('[type="hidden"]').val('');
                $row.find('select').prop('selectedIndex', 0);
            } else {
                $row.remove();
            }

            grandtotalAccountTab_invoice();
        }

        $(document).delegate('.removeitems_invoice', 'click', function(e){

            e.preventDefault();
            e.stopPropagation();

            var $row = $(this).closest('tr');
            if (!$row.length || !$row.closest('.productitem_invoice').length) {
                return;
            }

            if (invoiceRowHasFilledData($row)) {
                if (!confirm('Are you sure you want to remove this line?')) {
                    return;
                }
            }

            removeInvoiceRow($row);

        });



        $(document).delegate('.withdraw_amount_invoice_per_row, .payment_type_invoice_per_row', 'blur', function() {

            grandtotalAccountTab_invoice();

        });



      



        function grandtotalAccountTab_invoice() {

            var total_withdraw_amount_all_rows_invoice = 0;



            // Loop through only visible rows

            $('.productitem_invoice tr:visible').each(function(index) {

                var $row = $(this);



                // Get the withdraw amount from the input field

                var withdrawVal = $row.find('.withdraw_amount_invoice_per_row').val();

                // Get the payment type from the select field

                var paymentType = $row.find('select[name="payment_type[]"]').val();



                if (withdrawVal) {

                    // Remove currency symbols, commas, and spaces

                    withdrawVal = withdrawVal.replace(/[^0-9.-]+/g, '');

                    var withdrawAmount = parseFloat(withdrawVal) || 0; // Fallback to 0 if NaN



                    // Adjust total based on payment type

                    if (paymentType === 'Discount') {

                        total_withdraw_amount_all_rows_invoice -= withdrawAmount;

                    } else {

                        total_withdraw_amount_all_rows_invoice += withdrawAmount;

                    }




                } else {


                }

            });



            //console.log('Total calculated: ' + total_withdraw_amount_all_rows_invoice);

            $('.total_withdraw_amount_all_rows_invoice').html('$' + total_withdraw_amount_all_rows_invoice.toFixed(2));

        }





        //create invoice changes end





        //create office receipt start - Initialize Flatpickr
        initFlatpickrForClass('.report_date_fields_office');
        initFlatpickrForClass('.report_entry_date_fields_office', {
            defaultDate: new Date()
        });



        $(document).delegate('.openproductrinfo_office', 'click', function(){

            var clonedval_office = $('.clonedrow_office').html();

            var $newOfficeRow = $('<tr class="product_field_clone_office">' + clonedval_office + '</tr>');

            $('.productitem_office').append($newOfficeRow);

            // Initialize Flatpickr for office receipt date fields
            initFlatpickrForClass('.report_date_fields_office,.report_entry_date_fields_office');
            initFlatpickrForClass('.report_entry_date_fields_office:last', {
                defaultDate: new Date()
            });

            toggleOfficeEftposSurchargeRow($newOfficeRow);

        });



        $(document).delegate('.removeitems_office', 'click', function(){

            var $tr_office    = $(this).closest('.product_field_clone_office');

            var trclone_office = $('.product_field_clone_office').length;

            if(trclone_office > 0){

                $tr_office.remove();

            }

            grandtotalAccountTab_office();

        });



        $(document).delegate('.total_deposit_amount_office', 'keyup', function(){

            grandtotalAccountTab_office();

        });



        function grandtotalAccountTab_office() {

            var total_deposit_amount_all_rows = 0;

            $('.productitem_office tr').each(function() {

                var $row = $(this);



                // Handle deposit amount

                var depositVal = $row.find('.total_deposit_amount_office').val();

                var depositAmount = parseFloat(depositVal) || 0; // fallback to 0 if NaN

                if ($row.find('.office-eftpos-surcharge-block').is(':visible')) {

                    var surO = parseFloat($row.find('.office-eftpos-surcharge-input').val()) || 0;

                    total_deposit_amount_all_rows += depositAmount + surO;

                } else {

                    total_deposit_amount_all_rows += depositAmount;

                }

            });



            $('.total_deposit_amount_all_rows_office').html("$" + total_deposit_amount_all_rows.toFixed(2));

        }

        window.grandtotalAccountTab_office = grandtotalAccountTab_office;

        //create office receipt changes end





        //create journal receipt start - Initialize Flatpickr
        initFlatpickrForClass('.report_date_fields_journal');
        initFlatpickrForClass('.report_entry_date_fields_journal', {
            defaultDate: new Date()
        });



        $(document).delegate('.openproductrinfo_journal', 'click', function(){

            var clonedval_journal = $('.clonedrow_journal').html();

            $('.productitem_journal').append('<tr class="product_field_clone_journal">'+clonedval_journal+'</tr>');

            // Initialize Flatpickr for journal receipt date fields
            initFlatpickrForClass('.report_date_fields_journal');
            initFlatpickrForClass('.report_entry_date_fields_journal:last', {
                defaultDate: new Date()
            });

        });



        $(document).delegate('.removeitems_journal', 'click', function(){

            var $tr_journal    = $(this).closest('.product_field_clone_journal');

            var trclone_journal = $('.product_field_clone_journal').length;

            if(trclone_journal > 0){

                $tr_journal.remove();

            }

            grandtotalAccountTab_journal();

        });



        $(document).delegate('.total_withdrawal_amount_journal,.total_deposit_amount_journal', 'keyup', function(){

            grandtotalAccountTab_journal();

        });



        $(document).delegate('.total_withdrawal_amount_journal', 'blur', function(){

            if( $(this).val() != ""){

                var randomNumber = $('#journal_top_value_db').val();

                randomNumber = Number(randomNumber);

                randomNumber = randomNumber + 1; 

                $('#journal_top_value_db').val(randomNumber);

                randomNumber = "Trans"+randomNumber;

                $(this).closest('tr').find('.unique_trans_no_journal').val(randomNumber);

                $(this).closest('tr').find('.unique_trans_no_hidden_journal').val(randomNumber);

            } else {

                $(this).closest('tr').find('.unique_trans_no_journal').val();

                $(this).closest('tr').find('.unique_trans_no_hidden_journal').val();

            }

        });



        $(document).delegate('.total_deposit_amount_journal', 'blur', function(){

            if( $(this).val() != ""){

                var randomNumber = $('#journal_top_value_db').val();

                randomNumber = Number(randomNumber);

                randomNumber = randomNumber + 1; 

                $('#journal_top_value_db').val(randomNumber);

                randomNumber = "Rec"+randomNumber;

                $(this).closest('tr').find('.unique_trans_no_journal').val(randomNumber);

                $(this).closest('tr').find('.unique_trans_no_hidden_journal').val(randomNumber);

            } else {

                $(this).closest('tr').find('.unique_trans_no_journal').val();

                $(this).closest('tr').find('.unique_trans_no_hidden_journal').val();

            }

        });



        function grandtotalAccountTab_journal(){

            var total_withdrawal_amount_all_rows_journal = 0;

            $('.productitem_journal tr').each(function(){

            if($(this).find('.total_withdrawal_amount_journal').val() != ''){

                    var withdrawal_amount_per_row_journal = $(this).find('.total_withdrawal_amount_journal').val();

                }else{

                    var withdrawal_amount_per_row_journal = 0;

                }

                total_withdrawal_amount_all_rows_journal += parseFloat(withdrawal_amount_per_row_journal);

            });

            $('.total_withdraw_amount_all_rows_journal').html("$"+total_withdrawal_amount_all_rows_journal.toFixed(2));

        }

        //create journal receipt changes end



        // Initialize Flatpickr for education service start date
        if (typeof flatpickr !== 'undefined') {
            const eduDateEl = $('#edu_service_start_date')[0];
            if (eduDateEl && !$(eduDateEl).data('flatpickr')) {
                flatpickr(eduDateEl, {
                    dateFormat: 'd/m/Y',
                    allowInput: true,
                    clickOpens: true,
                    defaultDate: $(eduDateEl).val() || null,
                    locale: { firstDayOfWeek: 1 }
                });
            }
        }



        $('.filter_btn').on('click', function(){

            $('.filter_panel').toggle();

        });



        // Service type toggle REMOVED - form #createservicetaken deleted (modal removed in Phase 2)



        // In-person assignee (check-in panel HTML): Tom Select, fixed width
        if (typeof crmAfterCheckinDetailHtml === 'function') {
            crmAfterCheckinDetailHtml(document);
        } else if (typeof initChangeAssigneeTS === 'function') {
            initChangeAssigneeTS(document, { width: '220px' });
        }



        var windowsize = $(window).width();

        if(windowsize > 2000){

            $('.add_note').css('width','980px');

        }



        // --- not picked call button code start ---

        $(document).delegate('.not_picked_call', 'click', function (e) {

            var clientName = window.ClientDetailConfig.clientFirstName || 'client';

            clientName = clientName.charAt(0).toUpperCase() + clientName.slice(1).toLowerCase(); //alert(clientName);



            var message = (window.ClientDetailConfig.notPickedCallSmsDefault || '').trim();
            if (!message) {
                message = 'Hi ' + clientName + ',\n\nWe tried reaching you but couldn\'t connect. Please call us at 0396021330 or let us know a suitable time.\n\nPlease do not reply via SMS.\n\n' + (window.__CRM_APP_NAME__ || '');
            }

            $('#messageText').val(message); // Set dynamic message text

            $('#notPickedCallModal').modal('show'); // Show Modal Window



            $('.sendMessage').on('click', function () {

                var message = $('#messageText').val();

                var not_picked_call = 1;

                $.ajax({

                    url: window.ClientDetailConfig.urls.notPickedCall,

                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },

                    type: 'POST',

                    dataType: 'json',

                    data: {

                        id: window.ClientDetailConfig.clientId,

                        not_picked_call: not_picked_call,

                        message: message

                    },

                    success: function (response) {

                        var obj = safeParseJsonResponse(response);
                        if (!obj) return;

                        if (obj.not_picked_call == 1) {

                            alert(obj.message);

                        } else {

                            alert(obj.message);

                        }

                        getallactivities();

                        $('#notPickedCallModal').modal('hide'); // Hide Modal Window

                    }

                });

            });

        });



        // --- not picked call button code end ---

        // Appointment booking, time slots, getDisabledDateTime, calendar UI - see modules/appointments.js

        $('.manual_email_phone_verified').on('change', function(){

            if( $(this).is(":checked") ) {

                $('.manual_email_phone_verified').val(1);

                var manual_email_phone_verified = 1;

            } else {

                $('.manual_email_phone_verified').val(0);

                var manual_email_phone_verified = 0;

            }



            var client_id = window.ClientDetailConfig.clientId; //alert(site_url);

            $.ajax({

                url: site_url+'/clients/update-email-verified',

                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},

                type:'POST',

                data:{manual_email_phone_verified:manual_email_phone_verified,client_id:client_id},

                success: function(responses){

                    location.reload();

                }

            });

        });



        //alert('ready');

        $('#feather-icon').click(function(){

            var windowsize = $(window).width(); 

            if($('.main-sidebar').width() == 65){

                if(windowsize > 2000){

                    $('.add_note,.last_updated_date').css('width','980px');

                } else {

                    $('.add_note').css('width','338px');

                    $('.last_updated_date').css('width','348px');

                }



            } else if($('.main-sidebar').width() == 250) {

                if(windowsize > 2000){

                    $('.add_note,.last_updated_date').css('width','1040px');

                } else {

                    $('.add_note').css('width','433px');

                    $('.last_updated_date').css('width','442px');

                }

            }

        });

        //set height of right side section

        var left_upper_height = $('.left_section_upper').height();

        //var left_section_lower = $('.left_section_lower').height();

        var left_section_lower = 0;

        var total_left  = left_upper_height + left_section_lower;

        total_left = total_left +25;



        var right_section_height = $('.right_section').height();

       



        //alert(left_upper_height+'==='+left_section_lower+'==='+total_left+'==='+right_section_height);

        if(right_section_height >total_left ){ 

            var total_left_px = total_left+'px';

            $('.right_section').css({"maxHeight":total_left_px});

            $('.right_section').css({"overflow": 'scroll' });

        } else {  

            var total_left_px = total_left+'px';

            $('.right_section').css({"maxHeight":total_left_px});

        }





        let css_property =

            {

                "display": "none",

            }

        $('#create_note_d').hide();

        $('.main-footer').css(css_property);







        $(document).delegate('.uploadmail','click', function(){

            $('#maclient_id').val(window.ClientDetailConfig.clientId);

            $('#uploadmail').modal('show');

        });



        $(document).delegate('.uploadAndFetchMail','click', function(){

            $('#maclient_id_fetch').val(window.ClientDetailConfig.clientId);

            var hidden_client_matter_id = $('#sel_matter_id_client_detail').val();

            $('#upload_inbox_mail_client_matter_id').val(hidden_client_matter_id);

            $('#uploadAndFetchMailModel').modal('show');

        });

        // Handle uploadAndFetchMail form submission via AJAX
        function resolveEmailUploadAjaxError(xhr) {
            var errorMessage = 'An unexpected error occurred. Please try again.';
            if (xhr.status === 403 && typeof window.crmEmailUpload403Message === 'function') {
                return window.crmEmailUpload403Message(xhr);
            }
            if (xhr.responseJSON && xhr.responseJSON.errors) {
                var errors = xhr.responseJSON.errors;
                var errorHtml = '<span class="alert alert-danger">';
                for (var field in errors) {
                    errorHtml += errors[field][0] + '<br>';
                }
                errorHtml += '</span>';
                return errorHtml;
            }
            if (xhr.responseJSON && xhr.responseJSON.message) {
                return xhr.responseJSON.message;
            }
            return errorMessage;
        }

        $(document).on('submit', '#uploadAndFetchMail', function(e) {
            e.preventDefault();
            
            var formData = (typeof window.crmBuildEmailUploadFormData === 'function')
                ? window.crmBuildEmailUploadFormData(this)
                : new FormData(this);
            $('.popuploader').show();
            $('.custom-error-msg').html('');
            
            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    $('.popuploader').hide();
                    if (response.status) {
                        $('.custom-error-msg').html('<span class="alert alert-success">' + response.message + '</span>');
                        $('#uploadAndFetchMailModel').modal('hide');
                        // Reload the page to show the uploaded emails
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        $('.custom-error-msg').html('<span class="alert alert-danger">' + response.message + '</span>');
                    }
                },
                error: function(xhr) {
                    $('.popuploader').hide();
                    var resolved = resolveEmailUploadAjaxError(xhr);
                    if (typeof resolved === 'string' && resolved.indexOf('<span') === 0) {
                        $('.custom-error-msg').html(resolved);
                    } else {
                        $('.custom-error-msg').html('<span class="alert alert-danger">' + resolved + '</span>');
                    }
                }
            });
        });

        // Handle uploadSentAndFetchMail form submission via AJAX
        $(document).on('submit', '#uploadSentAndFetchMail', function(e) {
            e.preventDefault();
            
            var formData = (typeof window.crmBuildEmailUploadFormData === 'function')
                ? window.crmBuildEmailUploadFormData(this)
                : new FormData(this);
            $('.popuploader').show();
            $('.custom-error-msg').html('');
            
            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    $('.popuploader').hide();
                    if (response.status) {
                        $('.custom-error-msg').html('<span class="alert alert-success">' + response.message + '</span>');
                        $('#uploadSentAndFetchMailModel').modal('hide');
                        // Reload the page to show the uploaded emails
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        $('.custom-error-msg').html('<span class="alert alert-danger">' + response.message + '</span>');
                    }
                },
                error: function(xhr) {
                    $('.popuploader').hide();
                    var resolved = resolveEmailUploadAjaxError(xhr);
                    if (typeof resolved === 'string' && resolved.indexOf('<span') === 0) {
                        $('.custom-error-msg').html(resolved);
                    } else {
                        $('.custom-error-msg').html('<span class="alert alert-danger">' + resolved + '</span>');
                    }
                }
            });
        });







        // Set up CSRF token for all AJAX requests

        $.ajaxSetup({

            headers: {

                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')

            }

        });




        // Direct AJAX submission for visa agreement

        $(document).delegate('.visaAgreementCreateForm', 'click', function() {

            var client_id = window.ClientDetailConfig.clientId;

            var client_matter_id = $('#sel_matter_id_client_detail').val();

            // FIX: Validate that a matter is selected before proceeding
            // This prevents generating corrupted agreements without matter data
            if (!client_matter_id || client_matter_id === '' || client_matter_id === null) {
                alert('Please select a matter before generating the visa agreement.\n\nA matter is required to populate visa details, fees, and agent information.');
                return false;
            }

            // First check if cost assignment exists

            $.ajax({

                url: window.ClientDetailConfig.urls.checkCostAssignment,

                type: "POST",

                data: {

                    client_id: client_id,

                    client_matter_id: client_matter_id,

                    _token: $('meta[name="csrf-token"]').attr('content')

                },

                success: function (response) {

                    if (response.exists) {

                        // Get agent details and then submit via AJAX

                        $.ajax({

                            type: 'post',

                            url: window.ClientDetailConfig.urls.getVisaAgreementLegalPractitioner,

                            data: {client_matter_id: client_matter_id},

                            success: function(agentResponse) {

                                var obj = safeParseJsonResponse(agentResponse);
                                if (!obj) return;
                                if(obj.agentInfo) {

                                    // Prepare form data for AJAX submission

                                    var formData = {

                                        _token: $('meta[name="csrf-token"]').attr('content'),

                                        client_id: client_id,

                                        client_matter_id: client_matter_id,

                                        agent_id: obj.agentInfo.agentId,

                                        agent_name: obj.agentInfo.last_name != '' ?

                                            obj.agentInfo.first_name + ' ' + obj.agentInfo.last_name :

                                            obj.agentInfo.first_name,

                                        business_name: obj.agentInfo.company_name || ''

                                    };



                                    // Submit via AJAX

                                    $.ajax({

                                        url: window.ClientDetailConfig.urls.generateAgreement,

                                        method: 'POST',

                                        data: formData,

                                        success: function(response) {

                                            // Handle successful response

                                            if (response.success && response.download_url) {

                                                // Use window.open for download - single method to prevent duplicates

                                                try {

                                                    // Primary method: window.open for download

                                                    var downloadWindow = window.open(response.download_url, '_blank');

                                                    

                                                    // Check if window.open was blocked or failed immediately

                                                    if (!downloadWindow || downloadWindow.closed) {

                                                        // Fallback: Use direct link click only if window.open was blocked

                                                        var link = document.createElement('a');

                                                        link.href = response.download_url;

                                                        link.download = 'visa_agreement_' + new Date().getTime() + '.docx';

                                                        link.target = '_blank';

                                                        link.style.display = 'none';

                                                        document.body.appendChild(link);

                                                        link.click();

                                                        // Clean up after a short delay

                                                        setTimeout(function() {

                                                            document.body.removeChild(link);

                                                        }, 100);

                                                    }

                                                    

                                                    // Show success message

                                                    alert('Visa agreement generated successfully!');

                                                } catch (error) {

                                                    console.error('Download error:', error);

                                                    

                                                    // Last resort: Direct link approach only if window.open throws an error

                                                    try {

                                                        var link = document.createElement('a');

                                                        link.href = response.download_url;

                                                        link.download = 'visa_agreement_' + new Date().getTime() + '.docx';

                                                        link.target = '_blank';

                                                        link.style.display = 'none';

                                                        document.body.appendChild(link);

                                                        link.click();

                                                        setTimeout(function() {

                                                            document.body.removeChild(link);

                                                        }, 100);

                                                        

                                                        alert('Visa agreement generated successfully!');

                                                    } catch (fallbackError) {

                                                        console.error('Fallback download error:', fallbackError);

                                                        alert('Visa agreement generated successfully! Please check your downloads folder or browser download settings.');

                                                    }

                                                }

                                            } else {

                                                alert('Document generated but no download URL returned.');

                                            }

                                        },

                                        error: function(xhr) {

                                            // Handle errors

                                            if (xhr.responseJSON && xhr.responseJSON.message) {

                                                alert('Error: ' + xhr.responseJSON.message);

                                            } else {

                                                alert('Error generating visa agreement.');

                                            }

                                        }

                                    });

                                } else {

                                    alert("Agent information not found.");

                                }

                            },

                            error: function() {

                                alert("Error fetching agent details.");

                            }

                        });

                    } else {

                        alert("Please first create Cost Assignment.");

                    }

                },

                error: function() {

                    alert("Error checking cost assignment.");

                }

            });

        });



         // Get visa agreement Legal Practitioner detail

        function getVisaAgreementLegalPractitionerDetail(client_matter_id) {

            $.ajax({

                type:'post',

                url: window.ClientDetailConfig.urls.getVisaAgreementLegalPractitioner,

                sync:true,

                data: {client_matter_id:client_matter_id},

                success: function(response){

                    var obj = safeParseJsonResponse(response);
                    if (!obj) return;
                    if(obj.agentInfo){

                        $('#visaagree_agent_id').val(obj.agentInfo.agentId);

                        if(obj.agentInfo.last_name != ''){

                            var agentFullName = obj.agentInfo.first_name+' '+obj.agentInfo.last_name;

                        } else {

                            var agentFullName =  obj.agentInfo.first_name;

                        }

                        $('#visaagree_agent_name').val(agentFullName);

                        $('#visaagree_agent_name_label').html(agentFullName);



                        $('#visaagree_business_name').val(obj.agentInfo.company_name);

                        $('#visaagree_business_name_label').html(obj.agentInfo.company_name);

                    }

                }

            });

        }



        // Handle form submission via AJAX

        $('#visaagreementform11').on('submit', function(e) {

            e.preventDefault();

            let form = $(this);

            $.ajax({

                url: form.attr('action'),

                method: 'POST',

                data: form.serialize(),

                success: function(response) {

                    // Hide modal if needed

                    $('#visaAgreementCreateFormModel').modal('hide');



                    // Redirect to download URL

                    if (response.download_url) {

                        window.location.href = response.download_url;

                    } else {

                        alert('Document generated but no download URL returned.');

                    }

                },

                error: function(xhr) {

                    $('.custom-error-msg').html('');

                    let errors = xhr.responseJSON?.errors || {};

                    for (let field in errors) {

                        $('.custom-error-msg').append('<p class="text-red-600">' + errors[field][0] + '</p>');

                    }

                }

            });

        });





        // Note: costAssignmentCreateForm click handler and switchToCostAssignmentList
        // removed — Form Generation tab no longer exists. Cost assignment create/amend
        // now happens exclusively via the modal in the Checklists tab.



         // Get cost assignment Legal Practitioner detail
        // modalContainer: optional selector (e.g. '#costAssignmentCreateFormModel') to scope field updates to a specific container (for modal edit)
        // onLoadedCallback: optional function called after data is loaded (e.g. to show modal)
        function getCostAssignmentLegalPractitionerDetail(client_id,client_matter_id, modalContainer, onLoadedCallback) {

            var $scope = (modalContainer && $(modalContainer).length) ? $(modalContainer) : $(document);

            $.ajax({

                type:'post',

                url: window.ClientDetailConfig.urls.getCostAssignmentLegalPractitioner,

                sync:true,

                data: {client_id:client_id,client_matter_id:client_matter_id},

                success: function(response){

                    var obj = safeParseJsonResponse(response);
                    if (!obj) return;
                    if(obj.agentInfo){

                        $scope.find('#costassign_agent_id').val(obj.agentInfo.agentId);

                        if(obj.agentInfo.last_name != ''){

                            var agentFullName = obj.agentInfo.first_name+' '+obj.agentInfo.last_name;

                        } else {

                            var agentFullName =  obj.agentInfo.first_name;

                        }

                        //$('#costassign_agent_name').val(agentFullName);

                        $scope.find('#costassign_agent_name_label').html(agentFullName);



                        //$('#costassign_business_name').val(obj.agentInfo.company_name);

                        $scope.find('#costassign_business_name_label').html(obj.agentInfo.company_name);

                        $scope.find('#costassign_client_matter_name_label').html(obj.matterInfo.title);



                        //Fetch matter related cost assignments

                        if(obj.cost_assignment_matterInfo){

                            $scope.find('#Block_1_Ex_Tax').val(obj.cost_assignment_matterInfo.Block_1_Ex_Tax);
                            $scope.find('#Block_2_Ex_Tax').val(obj.cost_assignment_matterInfo.Block_2_Ex_Tax);
                            $scope.find('#Block_3_Ex_Tax').val(obj.cost_assignment_matterInfo.Block_3_Ex_Tax);
                            $scope.find('#additional_fee_1').val(obj.cost_assignment_matterInfo.additional_fee_1);
                            $scope.find('#TotalBLOCKFEE').val(obj.cost_assignment_matterInfo.TotalBLOCKFEE);

                            // Populate disbursement lines
                            var lines = obj.cost_assignment_matterInfo.disbursement_lines || [];
                            populateDisbursementRows(lines, $scope.find('#disbursement-rows'));
                            calculateTotalDisbursements(modalContainer);

                        } else {

                            $scope.find('#Block_1_Ex_Tax').val(obj.matterInfo.Block_1_Ex_Tax);
                            $scope.find('#Block_2_Ex_Tax').val(obj.matterInfo.Block_2_Ex_Tax);
                            $scope.find('#Block_3_Ex_Tax').val(obj.matterInfo.Block_3_Ex_Tax);
                            $scope.find('#additional_fee_1').val(obj.matterInfo.additional_fee_1);
                            $scope.find('#TotalBLOCKFEE').val(obj.matterInfo.TotalBLOCKFEE);
                            populateDisbursementRows([], $scope.find('#disbursement-rows'));

                        }

                        // Initialize calculation handlers after data is loaded
                        setTimeout(function() {
                            initializeCostAssignmentCalculations(modalContainer);
                            calculateTotalBlockFee(modalContainer);
                            calculateTotalDisbursements(modalContainer);
                            if (typeof onLoadedCallback === 'function') {
                                onLoadedCallback();
                            }
                        }, 100);

                    }
                },
                error: function(xhr, status, error) {
                    // error handled silently
                }
            });
        }

        // ──────────────────────────────────────────────────────────────────
        //  Disbursement row helpers
        // ──────────────────────────────────────────────────────────────────
        var disbursementNatures = {
            court_fees:      'Court Fees',
            barrister_fees:  'Barrister Fees',
            expert_report:   'Expert Report',
            travel:          'Travel',
            postage:         'Postage / Courier',
            filing_registry: 'Filing / Registry',
            search_fees:     'Search Fees',
            other:           'Other'
        };

        function buildNatureOptions(selected) {
            var html = '';
            $.each(disbursementNatures, function(val, label) {
                html += '<option value="' + val + '"' + (val === selected ? ' selected' : '') + '>' + label + '</option>';
            });
            return html;
        }

        function buildDisbursementRow(idx, nature, description, amount) {
            nature      = nature      || 'other';
            description = description || '';
            amount      = amount      !== undefined ? amount : '';
            return (
                '<div class="disbursement-row row mb-2 align-items-center">' +
                    '<div class="col-md-4 col-12 mb-1 mb-md-0">' +
                        '<select name="disbursements[' + idx + '][nature]" class="form-control form-control-sm disbursement-nature-select">' +
                            buildNatureOptions(nature) +
                        '</select>' +
                    '</div>' +
                    '<div class="col-md-4 col-12 mb-1 mb-md-0">' +
                        '<input type="text" name="disbursements[' + idx + '][description]" class="form-control form-control-sm" placeholder="Description (optional)" value="' + description + '">' +
                    '</div>' +
                    '<div class="col-md-3 col-10 mb-1 mb-md-0">' +
                        '<input type="number" name="disbursements[' + idx + '][amount]" class="form-control form-control-sm disbursement-amount-input" placeholder="0.00" step="0.01" min="0" value="' + amount + '">' +
                    '</div>' +
                    '<div class="col-md-1 col-2 text-right">' +
                        '<button type="button" class="btn btn-outline-danger btn-sm btn-remove-disbursement-row"><i class="fa-solid fa-xmark"></i></button>' +
                    '</div>' +
                '</div>'
            );
        }

        function populateDisbursementRows(lines, $container) {
            $container.empty();
            if (lines && lines.length > 0) {
                $.each(lines, function(idx, line) {
                    $container.append(buildDisbursementRow(idx, line.nature, line.description, line.amount));
                });
            } else {
                $container.append(buildDisbursementRow(0, 'other', '', ''));
            }
            rebindDisbursementRowNames($container);
        }

        function rebindDisbursementRowNames($container) {
            $container.find('.disbursement-row').each(function(idx) {
                $(this).find('[name^="disbursements"]').each(function() {
                    var oldName = $(this).attr('name');
                    var newName = oldName.replace(/disbursements\[\d+\]/, 'disbursements[' + idx + ']');
                    $(this).attr('name', newName);
                });
            });
        }

        // ──────────────────────────────────────────────────────────────────
        //  Disbursement UI — Add / Remove row handlers (client modal)
        // ──────────────────────────────────────────────────────────────────
        $(document).on('click', '.btn-add-disbursement-row, .btn-add-disbursement-row-lead', function() {
            var isLead = $(this).hasClass('btn-add-disbursement-row-lead');
            var rowContainerId = isLead ? '#disbursement-rows-lead' : '#disbursement-rows';
            var $modal = $(this).closest('.modal');
            var $container = $modal.length ? $modal.find(rowContainerId) : $(rowContainerId);
            if (!$container.length) $container = $(rowContainerId);
            var idx = $container.find('.disbursement-row').length;
            $container.append(buildDisbursementRow(idx, 'other', '', ''));
            rebindDisbursementRowNames($container);
        });

        $(document).on('click', '.btn-remove-disbursement-row', function() {
            var $container = $(this).closest('#disbursement-rows, #disbursement-rows-lead');
            $(this).closest('.disbursement-row').remove();
            rebindDisbursementRowNames($container);
            calculateTotalDisbursements($(this).closest('.modal').length ? '#' + $(this).closest('.modal').attr('id') : null);
        });

        $(document).on('input change keyup', '.disbursement-amount-input', function() {
            var modalId = $(this).closest('.modal').attr('id');
            calculateTotalDisbursements(modalId ? '#' + modalId : null);
        });

        // ──────────────────────────────────────────────────────────────────
        //  Cost assignment calculation functions
        // ──────────────────────────────────────────────────────────────────
        function initializeCostAssignmentCalculations(containerScope) {
            var $scope = (containerScope && $(containerScope).length) ? $(containerScope) : $(document);

            $scope.find('#Block_1_Ex_Tax, #Block_2_Ex_Tax, #Block_3_Ex_Tax').off('input change keyup').on('input change keyup', function() {
                calculateTotalBlockFee(containerScope);
            });

            calculateTotalBlockFee(containerScope);
            calculateTotalDisbursements(containerScope);
        }

        function calculateTotalBlockFee(containerScope) {
            var $scope = (containerScope && $(containerScope).length) ? $(containerScope) : $(document);
            var block1 = parseFloat($scope.find('#Block_1_Ex_Tax').val()) || 0;
            var block2 = parseFloat($scope.find('#Block_2_Ex_Tax').val()) || 0;
            var block3 = parseFloat($scope.find('#Block_3_Ex_Tax').val()) || 0;
            $scope.find('#TotalBLOCKFEE').val((block1 + block2 + block3).toFixed(2));
        }

        function calculateTotalDisbursements(containerScope) {
            var $scope = (containerScope && $(containerScope).length) ? $(containerScope) : $(document);
            var total = 0;
            $scope.find('.disbursement-amount-input').each(function() {
                total += parseFloat($(this).val()) || 0;
            });
            // Update whichever total field exists within the scope
            var $total = $scope.find('#TotalDisbursements, #TotalDisbursements_lead').first();
            if ($total.length) {
                $total.val(total.toFixed(2));
            } else {
                // Fall back to document-level if not scoped
                $('#TotalDisbursements, #TotalDisbursements_lead').val(total.toFixed(2));
            }
        }

        window.initializeCostAssignmentCalculations = initializeCostAssignmentCalculations;
        window.calculateTotalBlockFee               = calculateTotalBlockFee;
        window.calculateTotalDisbursements          = calculateTotalDisbursements;
        window.populateDisbursementRows             = populateDisbursementRows;
        window.getCostAssignmentLegalPractitionerDetail = getCostAssignmentLegalPractitionerDetail;

        // ──────────────────────────────────────────────────────────────────
        //  Cost assignment form submit handler
        // ──────────────────────────────────────────────────────────────────
        $(document).on('submit', '#costAssignmentform', function(e) {
            e.preventDefault();
            $.ajax({
                url: $(this).attr('action'),
                method: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(response) {
                    var obj = safeParseJsonResponse(response);
                    if (obj && obj.status) {
                        var $modal = $('#costAssignmentCreateFormModel');
                        if ($modal.length && $modal.hasClass('show')) {
                            $modal.modal('hide');
                        }
                        localStorage.setItem('activeTab', 'account');
                        setTimeout(function() { location.reload(); }, 500);
                    }
                },
                error: function(xhr) {
                    $('.custom-error-msg').html('');
                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        var errors = xhr.responseJSON.errors;
                        for (var field in errors) {
                            $('.custom-error-msg').append('<p class="text-red-600">' + errors[field][0] + '</p>');
                        }
                    } else {
                        $('.custom-error-msg').append('<p class="text-red-600">An error occurred while submitting the form.</p>');
                    }
                }
            });
        });

        // ──────────────────────────────────────────────────────────────────
        //  Lead section — cost assignment
        // ──────────────────────────────────────────────────────────────────
        $(document).delegate('.costAssignmentCreateFormLead', 'click', function() {
            $('#cost_assignment_lead_id').val(window.ClientDetailConfig.clientId);
            $('#sel_legal_practitioner_id_lead,#sel_person_responsible_id_lead,#sel_person_assisting_id_lead,#sel_office_id_lead,#sel_matter_id_lead').each(function () {
                initTS(this, typeof buildPlainSingleTomSelectConfig === 'function'
                    ? buildPlainSingleTomSelectConfig({ dropdownParent: '#costAssignmentCreateFormModelLead' })
                    : { dropdownParent: '#costAssignmentCreateFormModelLead', create: false, maxItems: 1, allowEmptyOption: true });
            });
            $('#costAssignmentCreateFormModelLead').modal('show');
        });

        $(document).delegate('#sel_matter_id_lead', 'change', function() {
            var client_matter_id = $(this).val();
            var client_id = window.ClientDetailConfig.clientId;
            if (client_id && client_matter_id) {
                getCostAssignmentLegalPractitionerDetailLead(client_id, client_matter_id);
            }
        });

        function getCostAssignmentLegalPractitionerDetailLead(client_id, client_matter_id) {
            $.ajax({
                type: 'post',
                url: window.ClientDetailConfig.urls.getCostAssignmentLegalPractitionerLead,
                sync: true,
                data: {client_id: client_id, client_matter_id: client_matter_id},
                success: function(response) {
                    var obj = safeParseJsonResponse(response);
                    if (!obj) return;

                    if (obj.cost_assignment_matterInfo) {
                        $('#Block_1_Ex_Tax_lead').val(obj.cost_assignment_matterInfo.Block_1_Ex_Tax);
                        $('#Block_2_Ex_Tax_lead').val(obj.cost_assignment_matterInfo.Block_2_Ex_Tax);
                        $('#Block_3_Ex_Tax_lead').val(obj.cost_assignment_matterInfo.Block_3_Ex_Tax);
                        $('#additional_fee_1_lead').val(obj.cost_assignment_matterInfo.additional_fee_1);
                        $('#TotalBLOCKFEE_lead').val(obj.cost_assignment_matterInfo.TotalBLOCKFEE);
                        var lines = obj.cost_assignment_matterInfo.disbursement_lines || [];
                        populateDisbursementRows(lines, $('#disbursement-rows-lead'));
                        calculateTotalDisbursements('#costAssignmentCreateFormModelLead');
                    } else {
                        $('#Block_1_Ex_Tax_lead').val(obj.matterInfo ? obj.matterInfo.Block_1_Ex_Tax : '');
                        $('#Block_2_Ex_Tax_lead').val(obj.matterInfo ? obj.matterInfo.Block_2_Ex_Tax : '');
                        $('#Block_3_Ex_Tax_lead').val(obj.matterInfo ? obj.matterInfo.Block_3_Ex_Tax : '');
                        $('#additional_fee_1_lead').val(obj.matterInfo ? obj.matterInfo.additional_fee_1 : '');
                        $('#TotalBLOCKFEE_lead').val(obj.matterInfo ? obj.matterInfo.TotalBLOCKFEE : '');
                        populateDisbursementRows([], $('#disbursement-rows-lead'));
                    }

                    initializeCostAssignmentCalculationsLead();
                }
            });
        }

        function initializeCostAssignmentCalculationsLead() {
            $('#Block_1_Ex_Tax_lead, #Block_2_Ex_Tax_lead, #Block_3_Ex_Tax_lead').off('input change keyup').on('input change keyup', function() {
                calculateTotalBlockFeeLead();
            });
            calculateTotalBlockFeeLead();
            calculateTotalDisbursements('#costAssignmentCreateFormModelLead');
        }

        function calculateTotalBlockFeeLead() {
            var block1 = parseFloat($('#Block_1_Ex_Tax_lead').val()) || 0;
            var block2 = parseFloat($('#Block_2_Ex_Tax_lead').val()) || 0;
            var block3 = parseFloat($('#Block_3_Ex_Tax_lead').val()) || 0;
            $('#TotalBLOCKFEE_lead').val((block1 + block2 + block3).toFixed(2));
        }

        window.initializeCostAssignmentCalculationsLead = initializeCostAssignmentCalculationsLead;
        window.calculateTotalBlockFeeLead               = calculateTotalBlockFeeLead;

        // ──────────────────────────────────────────────────────────────────
        //  Lead form submit handler
        // ──────────────────────────────────────────────────────────────────
        $(document).on('submit', '#costAssignmentformLead, #costAssignmentformlead', function(e) {
            e.preventDefault();
            $.ajax({
                url: $(this).attr('action'),
                method: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(response) {
                    var obj = safeParseJsonResponse(response);
                    if (obj && obj.status) {
                        $('#costAssignmentCreateFormModelLead').modal('hide');
                        localStorage.setItem('activeTab', 'account');
                        setTimeout(function() { location.reload(); }, 500);
                    }
                },
                error: function(xhr) {
                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        var errors = xhr.responseJSON.errors;
                        for (var field in errors) {
                            $('.custom-error-msg-lead').append('<p class="text-red-600">' + errors[field][0] + '</p>');
                        }
                    }
                }
            });
        });

        // Agreement modal: drag-and-drop and click-to-browse; auto-upload on file set
        (function() {
            var $form = $('#agreementUploadForm');
            var $input = $form.find('input[name="agreement_doc"]');
            var $dropZone = $('#agreementDropZone');
            var $fileName = $('#agreementFileName');
            var $err = $('#agreementUploadError');

            function setAgreementFile(file) {
                if (!file) return;
                var name = file.name || 'File chosen';
                var isPdf = file.type === 'application/pdf' || (name.toLowerCase().indexOf('.pdf') === name.length - 4);
                if (!isPdf) {
                    $err.text('Please upload a PDF file.').show();
                    return;
                }
                $err.hide();
                var dt = new DataTransfer();
                dt.items.add(file);
                $input[0].files = dt.files;
                $fileName.text(name);
                $dropZone.addClass('agreement-drop-zone--over');
                setTimeout(function() { $dropZone.removeClass('agreement-drop-zone--over'); }, 300);
                doAgreementUpload();
            }

            function clearAgreementUploadState() {
                $input.val('');
                $fileName.text('');
                $err.hide();
                $dropZone.removeClass('agreement-drop-zone--over');
            }

            $dropZone.on('click', function(e) {
                if ($(e.target).closest('.agreement-file-input').length) return;
                e.preventDefault();
                $input[0].click();
            });
            $dropZone.on('keydown', function(e) { if (e.which === 13 || e.which === 32) { e.preventDefault(); $input[0].click(); } });

            $dropZone.on('dragenter', function(e) { e.preventDefault(); e.stopPropagation(); $dropZone.addClass('agreement-drop-zone--over'); });
            $dropZone.on('dragover', function(e) { e.preventDefault(); e.stopPropagation(); });
            $dropZone.on('dragleave', function(e) {
                e.preventDefault();
                if (!$dropZone[0].contains(e.relatedTarget)) $dropZone.removeClass('agreement-drop-zone--over');
            });
            $dropZone.on('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $dropZone.removeClass('agreement-drop-zone--over');
                var file = (e.originalEvent && e.originalEvent.dataTransfer && e.originalEvent.dataTransfer.files) ? e.originalEvent.dataTransfer.files[0] : null;
                if (file) setAgreementFile(file);
            });

            $(document).on('change', '#agreementUploadForm input[name="agreement_doc"]', function() {
                var f = this.files && this.files[0];
                if (f) {
                    var isPdf = f.type === 'application/pdf' || (f.name && f.name.toLowerCase().indexOf('.pdf') === f.name.length - 4);
                    if (!isPdf) {
                        $err.text('Please upload a PDF file.').show();
                        return;
                    }
                    $fileName.text(f.name);
                    $err.hide();
                    doAgreementUpload();
                } else {
                    $fileName.text('');
                }
            });

            $('#agreementModal').on('hidden.bs.modal', function() { clearAgreementUploadState(); });
        })();

        $(document).delegate('.uploadSentAndFetchMail','click', function(){

            $('#maclient_id_fetch_sent').val(window.ClientDetailConfig.clientId);

            var hidden_client_matter_id = $('#sel_matter_id_client_detail').val();

            $('#upload_sent_mail_client_matter_id').val(hidden_client_matter_id);

            $('#uploadSentAndFetchMailModel').modal('show');

        });



        $(document).delegate('.addnewprevvisa','click', function(){

            var $clone = $('.multiplevisa:eq(0)').clone(true,true);



            $clone.find('.lastfiledcol').after('<div class="col-md-4"><a href="javascript:;" class="removenewprevvisa btn btn-danger btn-sm">Remove</a></div>');

            $clone.find("input:text").val("");

            $clone.find("input.visadatesse").val("");

            $('.multiplevisa:last').after($clone);

        });



        $('#note_deadline_checkbox').on('click', function() {

            if ($(this).is(':checked')) {

                $('#note_deadline').prop('disabled', false);

                $('#note_deadline_checkbox').val(1);

            } else {

                $('#note_deadline').prop('disabled', true);

                $('#note_deadline_checkbox').val(0);

            }

        });



        $(document).on('change', '#noteTypeSimple, #noteTypeEnhanced', function() {

            var selectedValue = $(this).val();

            var $form = $(this).closest('form');

            var additionalFields = $form.find('.additional-fields-container').first();



            // Clear any existing fields

            additionalFields.html("");



            if(selectedValue === "Call") {

                additionalFields.append(`

                    <div class="form-group" style="margin-top:10px;">

                        <label for="mobileNumber">Mobile Number:</label>

                        <select name="mobileNumber" id="mobileNumber" class="form-control" data-valid="required"></select>

                        <span id="mobileNumberError" class="text-danger"></span>

                    </div>

                `);



                //Fetch all contact list of any client at create note popup

                var client_id = $form.find('input[name="client_id"]').val() || $('#client_id').val() || (window.ClientDetailConfig && window.ClientDetailConfig.clientId);

                $('.popuploader').show();

                $.ajax({

                    url: window.ClientDetailConfig.urls.fetchClientContactNo,

                    method: "POST",

                    data: {client_id:client_id},

                    dataType: 'json',

                    success: function(response) {

                        $('.popuploader').hide();

                        var obj = safeParseJsonResponse(response);
                        if (!obj) return;
                        var contactlist = '<option value="">Select Contact</option>';

                        $.each(obj.clientContacts, function(index, subArray) {

                            contactlist += '<option value="'+subArray.phone+'">'+subArray.phone+'</option>';

                        });

                        $('#mobileNumber').append(contactlist);

                    }

                });

            }

        });





        var activeLink = $('.nav-link.active');

        if (activeLink.length > 0) {

            var href = activeLink.attr('href');

            if(href == '#activities' ) {

                $('.filter_btn').css('display','inline-block');

                $('.filter_panel').css('display','none');

            } else {

                $('.filter_btn,.filter_panel').css('display','none');

            }

        } else {

            $('.filter_btn,.filter_panel').css('display','none');

        }





        $(document).delegate('.nav-link','click', function(){

            var activeLink = $('.nav-link.active');

            if (activeLink.length > 0) {

                var href = activeLink.attr('href');

                if(href == '#activities' ) {

                    $('.filter_btn').css('display','inline-block');

                    $('.filter_panel').css('display','none');

                } else {

                    $('.filter_btn,.filter_panel').css('display','none');

                }

            } else {

                $('.filter_btn,.filter_panel').css('display','none');

            }

        });



        /*$(document).delegate('.btn-assignuser','click', function(){

            var note_description = $('#note_description').val();

            // Remove <p> tags using regex

            var cleanedText = note_description.replace(/<\/?p>/g, '');

            // cleanedText = cleanedText.replace(/<\/?p>/g, '');

            $('#assignnote').val(cleanedText);

        });*/



        $(document).delegate('.removenewprevvisa','click', function(){

            $(this).parent().parent().parent().remove();

        });



        // assignStaff function is now handled in addclientmodal.blade.php to avoid conflicts

        $(document).on('click', '#assignStaff', function(e) {

            e.preventDefault();

            e.stopPropagation();

            

            $(".popuploader").show();

            let flag = true;

            let error = "";

            $(".custom-error").remove();



            // Get all checked assignee IDs from checkboxes

            let selectedAssignees = [];

            $('.checkbox-item:checked').each(function() {

                selectedAssignees.push($(this).val());

            });

            

            var selectedValues = selectedAssignees; // Use the checked values

            

            // Validation - Check if at least one assignee is selected

            if (selectedAssignees.length === 0) {

                $('.popuploader').hide();

                error = "At least one assignee must be selected.";

                $('#dropdownMenuButton').after("<span class='custom-error' role='alert' style='color: red; font-size: 12px; display: block; margin-top: 5px;'>" + error + "</span>");

                flag = false;

            }

            

            // Check if assignnote field is empty (handle both regular textarea and TinyMCE)

            var assignnoteValue = '';

            if (isEditorInitialized('#assignnote')) {

                assignnoteValue = getEditorContent('#assignnote');

            } else {

                assignnoteValue = $('#assignnote').val();

            }

            

            if (assignnoteValue.trim() === '') {

                $('.popuploader').hide();

                error = "Note field is required.";

                $('#assignnote').after("<span class='custom-error' role='alert' style='color: red; font-size: 12px; display: block; margin-top: 5px;'>" + error + "</span>");

                flag = false;

            }

            

            if ($('#task_group').val() === '') {

                $('.popuploader').hide();

                error = "Group field is required.";

                $('#task_group').after("<span class='custom-error' role='alert' style='color: red; font-size: 12px; display: block; margin-top: 5px;'>" + error + "</span>");

                flag = false;

            }



            if (flag) {

                $.ajax({

                    type: 'POST',

                    url: window.ClientDetailConfig.urls.followupStore,

                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },

                    data: {

                        note_type: 'follow_up',

                        description: assignnoteValue,

                        client_id: $('#assign_client_id').val(),

                        followup_datetime: $('#popoverdatetime').val(),

                        rem_cat: selectedValues,

                        task_group: $('#task_group option:selected').val(),

                        note_deadline_checkbox: $('#note_deadline_checkbox').val(),

                        note_deadline: $('#note_deadline').val()

                    },

                    success: function(response) {

                        $('.popuploader').hide();

                        $('#create_action_popup').modal('hide');

                        var obj = safeParseJsonResponse(response);
                        if (!obj) return;
                        if (obj.success) {

                            $("[data-role=popover]").each(function() {

                                (($(this).popover('hide').data('bs.popover') || {}).inState || {}).click = false; // fix for BS 3.3.6

                            });

                            

                            // Reset form fields after successful submission

                            $('#assignnote').val('');

                            $('#task_group').val('');

                            $('#popoverdatetime').val((new Date().toISOString().split('T')[0]));

                            $('#note_deadline').val((new Date().toISOString().split('T')[0]));

                            $('#note_deadline_checkbox').prop('checked', false);

                            $('#note_deadline').prop('disabled', true);

                            $('.checkbox-item').prop('checked', false);

                            

                            // Reset assignee selection

                            if (typeof updateSelectedStaff === 'function') {

                                updateSelectedStaff();

                            } else if (typeof updateSelectedUsers === 'function') {

                                updateSelectedUsers();

                            }

                            if (typeof updateHiddenSelect === 'function') {

                                updateHiddenSelect();

                            }

                            
                            
                            // Call the functions to refresh the data
                            // Add a small delay to ensure database transaction is committed
                            setTimeout(function() {
                                // Refresh Activity Feed
                                if (typeof getallactivities === 'function') {
                                    try {
                                        getallactivities();
                                    } catch (e) {
                                        console.error('Error refreshing Activity Feed:', e);
                                        // Try fallback method
                                        if (typeof window.loadActivities === 'function') {
                                            try {
                                                window.loadActivities();
                                            } catch (e2) {
                                                console.error('Error with fallback Activity Feed refresh:', e2);
                                            }
                                        }
                                    }
                                } else if (typeof window.loadActivities === 'function') {
                                    // Fallback if getallactivities is not available
                                    try {
                                        window.loadActivities();
                                    } catch (e) {
                                        console.error('Error with window.loadActivities():', e);
                                    }
                                }

                                // Refresh notes list
                                if (typeof getallnotes === 'function') {
                                    try {
                                        getallnotes();
                                    } catch (e) {
                                        console.error('Error refreshing notes:', e);
                                    }
                                }
                            }, 500); // 500ms delay to ensure DB transaction is committed

                        } else {

                            // Handle failure

                            alert('Error: ' + (obj.message || 'Something went wrong'));

                        }

                    },

                    error: function(xhr, status, error) {

                        $('.popuploader').hide();

                        console.error('Ajax error:', error);

                        alert('Error: ' + error);

                    }

                });

            } else {

                $('.popuploader').hide();

            }

        });



        function getallactivities(){

            $.ajax({

                url: site_url+'/get-activities',

                type:'GET',

                dataType:'json', // Fixed: changed from dataType to dataType (case-sensitive)

                data:{id:window.ClientDetailConfig.clientId},

                success: function(responses){
                    try {
                        var ress = safeParseJsonResponse(responses);
                        if (!ress) return;

                    var html = typeof window.buildActivityFeedListHtml === 'function'
                        ? window.buildActivityFeedListHtml(ress.data || [])
                        : '';

                    $('#activity-feed .feed-list').html(html);
                    if (typeof window.initActivityFeedClamps === 'function') {
                        window.initActivityFeedClamps();
                    }
                    if (window.ActivityFeed && typeof window.ActivityFeed.reapplyFilters === 'function') {
                        window.ActivityFeed.reapplyFilters();
                    }

                    //$('.activities').html(html);

                    $('.popuploader').hide();

                    

                    // Adjust Activity Feed height after content update

                    adjustActivityFeedHeight();

                    } catch (error) {
                        console.error('Error processing activities:', error);
                        $('.popuploader').hide();
                    }

                }

            });

        }



        // .publishdoc, .unpublishdoc, #confirmpublishdocModal .acceptpublishdoc REMOVED - workflow checklist unused

        $(document).delegate('.openassigneeshow', 'click', function(){

            $('.assigneeshow').show();

        });



        $(document).delegate('.closeassigneeshow', 'click', function(){

            $('.assigneeshow').hide();

        });



        $(document).delegate('.saveassignee', 'click', function(){

            var appliid = $(this).attr('data-id');

            $('.popuploader').show();

            $.ajax({

                url: site_url+'/clients/change_assignee',

                type:'GET',

                data:{id: appliid,assinee: $('#changeassignee').val()},

                success: function(response){

                    var obj = safeParseJsonResponse(response);
                    if (!obj) return;
                    if(obj.status){

                        alert(obj.message);

                        location.reload();

                    }else{

                        alert(obj.message);

                    }

                }

            });

        });







        var notuse_doc_id = '';

        var notuse_doc_href = '';

        var notuse_doc_type = '';



        // Move the notuseddoc click handler inside document ready

        $(document).on('click', '.notuseddoc', function(e){

            e.preventDefault();

            

            

            // Check if modal exists

            if($('#confirmNotUseDocModal').length === 0) {

                console.error('Modal #confirmNotUseDocModal not found!');

                return;

            }

            

            $('#confirmNotUseDocModal').modal('show');

            notuse_doc_id = $(this).attr('data-id');

            notuse_doc_href = $(this).attr('data-href');

            notuse_doc_type = $(this).attr('data-doctype');

            

        });



        // Alternative approach using delegate for better compatibility

        $(document).delegate('.notuseddoc', 'click', function(e){

            e.preventDefault();

            

            // Check if modal exists

            if($('#confirmNotUseDocModal').length === 0) {

                console.error('Modal #confirmNotUseDocModal not found!');

                return;

            }

            

            $('#confirmNotUseDocModal').modal('show');

            notuse_doc_id = $(this).attr('data-id');

            notuse_doc_href = $(this).attr('data-href');

            notuse_doc_type = $(this).attr('data-doctype');

        });



        // Test if elements with .notuseddoc class exist

        $('.notuseddoc').each(function(index) {

            // Add a test click handler to see if the element is clickable

            $(this).css('cursor', 'pointer');

        });



        // Additional fallback - bind directly to existing elements

        $('.notuseddoc').off('click').on('click', function(e) {

            e.preventDefault();

            e.stopPropagation();

            $('#confirmNotUseDocModal').modal('show');

            notuse_doc_id = $(this).attr('data-id');

            notuse_doc_href = $(this).attr('data-href');

            notuse_doc_type = $(this).attr('data-doctype');

        });



        $(document).delegate('#confirmNotUseDocModal .accept', 'click', function(){

            $('.popuploader').show();

            $.ajax({

                url: window.ClientDetailConfig.urls.admin + '/documents/not-used',

                type:'POST',

                dataType:'json',

                data:{doc_id:notuse_doc_id, doc_type:notuse_doc_type },

                success:function(response){

                    $('.popuploader').hide();

                    var res = safeParseJsonResponse(response);
                    if (!res) return;
                    $('#confirmNotUseDocModal').modal('hide');

                    if(res.status){

                        // Remove document from current tab (Personal or matter documents)
                        if(res.doc_type == 'personal') {
                            $('.documnetlist_'+res.doc_category+' #id_'+res.doc_id).remove();
                        } else if( res.doc_type == 'matter' || res.doc_type == 'visa' || res.doc_type == 'nomination') {
                            $('.migdocumnetlist1 #id_'+res.doc_id).remove();
                        }

                        // Add document to "Not Used" tab dynamically
                        if(res.docInfo) {
                            var doc = res.docInfo;
                            $('#notUsedEmptyState').hide();
                            $('#notUsedTableWrap').show();
                            $('.notuseddocumnetlist').append(buildNotUsedDocumentRowHtml(doc, res));
                            incrementNotUsedStatChips(doc.doc_type);
                        }

                        // Update activity log without page reload
                        getallactivities();
                        
                        // Show success message
                        if (typeof iziToast !== 'undefined' && typeof iziToast.success === 'function') {
                            iziToast.success({ message: 'Document moved to Not Used tab', position: 'topRight' });
                        } else {
                            alert('Document moved to Not Used tab');
                        }

                    } else {
                        console.error('✗ Failed to move document to Not Used tab', res);
                        if (typeof iziToast !== 'undefined' && typeof iziToast.error === 'function') {
                            iziToast.error({ message: res.message || 'Failed to move document', position: 'topRight' });
                        } else {
                            alert(res.message || 'Failed to move document');
                        }
                    }

                },

                error: function(xhr, status, error) {
                    $('.popuploader').hide();
                    console.error('✗ AJAX error moving document to Not Used tab', {status: status, error: error});
                    if (typeof iziToast !== 'undefined' && typeof iziToast.error === 'function') {
                        iziToast.error({ message: 'Error moving document. Please try again.', position: 'topRight' });
                    } else {
                        alert('Error moving document. Please try again.');
                    }
                }

            });

        });





        var backto_doc_id = '';

        var backto_doc_href = '';

        var backto_doc_type = '';

        function restorePersonalDocumentFromNotUsed(doc, res) {
            if (!doc || !doc.id) {
                return;
            }

            var categoryId = String(res.doc_category || doc.folder_name || '');
            if (!categoryId) {
                return;
            }

            var $listBody = $('.documnetlist_' + categoryId);
            if (!$listBody.length || $listBody.find('#id_' + doc.id).length) {
                return;
            }

            var uploadedBy = res.Added_By || 'NA';
            var uploadedDate = doc.created_at ? formatClientDocDateTime(doc.created_at) : '';
            var uploadTitle = 'Uploaded by: ' + uploadedBy + (uploadedDate ? ' on ' + uploadedDate : '');
            var previewUrl = doc.preview_url || (site_url + '/documents/preview/' + doc.id);
            var fileName = doc.file_name || 'document';
            var fileExt = doc.filetype || '';
            var displayName = fileName + (fileExt ? '.' + fileExt : '');
            var dlFilename = doc.myfile_key || displayName;
            var checklist = doc.checklist || 'N/A';
            var docNameWithoutExt = fileName.replace(/\s+/g, '_').toLowerCase();

            var trRow = '<tr class="drow" id="id_' + doc.id + '">' +
                '<td style="white-space: initial;">' +
                    '<div data-id="' + doc.id + '" data-personalchecklistname="' + checklist + '" class="personalchecklist-row" title="' + uploadTitle + '" style="display: flex; align-items: center; gap: 8px;">' +
                        '<span style="flex: 1;">' + checklist + '</span>' +
                    '</div>' +
                '</td>' +
                '<td style="white-space: initial;">' +
                    '<div data-id="' + doc.id + '" data-name="' + docNameWithoutExt + '" class="doc-row" title="' + uploadTitle + '" oncontextmenu="showFileContextMenu(event, ' + doc.id + ', \'' + fileExt + '\', \'' + previewUrl + '\', \'' + categoryId + '\', \'' + (doc.status || 'draft') + '\'); return false;">' +
                        '<a href="javascript:void(0);" onclick="previewFile(\'' + fileExt + '\', \'' + previewUrl + '\', \'preview-container-' + categoryId + '\')">' +
                            '<i class="fa-solid ' + documentFileIconClass(fileExt) + '"></i> <span>' + displayName + '</span>' +
                        '</a>' +
                    '</div>' +
                '</td>' +
                '<td>' +
                    '<a class="renamechecklist" data-id="' + doc.id + '" href="javascript:;" style="display: none;"></a>' +
                    '<a class="renamedoc" data-id="' + doc.id + '" href="javascript:;" style="display: none;"></a>' +
                    '<a class="download-file" data-document-id="' + doc.id + '" data-id="' + doc.id + '" data-filename="' + dlFilename + '" href="#" style="display: none;"></a>' +
                    '<a class="notuseddoc" data-id="' + doc.id + '" data-doctype="personal" data-doccategory="' + categoryId + '" data-href="documents/not-used" href="javascript:;" style="display: none;"></a>' +
                '</td>' +
            '</tr>';

            $listBody.append(trRow);

            var $grid = $('.griddata_' + categoryId);
            if ($grid.length && !$grid.find('#gid_' + doc.id).length) {
                var gridHtml = '<div class="grid_list" id="gid_' + doc.id + '">' +
                    '<div class="grid_col">' +
                        '<div class="grid_icon"><i class="fa-solid ' + documentFileIconClass(fileExt) + '"></i></div>' +
                        '<div class="grid_content">' +
                            '<span id="grid_' + doc.id + '" class="gridfilename">' + fileName + '</span>' +
                            '<div class="dropdown d-inline dropdown_ellipsis_icon">' +
                                '<a class="dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="fa-solid fa-ellipsis-vertical"></i></a>' +
                                '<div class="dropdown-menu">' +
                                    '<a href="javascript:void(0);" class="dropdown-item" onclick="previewFile(\'' + fileExt + '\', \'' + previewUrl + '\', \'preview-container-' + categoryId + '\')">Preview</a>' +
                                    '<a href="#" class="dropdown-item download-file" data-document-id="' + doc.id + '" data-filename="' + dlFilename + '">Download</a>' +
                                    '<a data-id="' + doc.id + '" class="dropdown-item notuseddoc" data-doctype="personal" data-doccategory="' + categoryId + '" data-href="notuseddoc" href="javascript:;">Not Used</a>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>';
                $grid.find('.clearfix').first().before(gridHtml);
            }

            if (typeof activatePersonalDocumentFolder === 'function') {
                activatePersonalDocumentFolder(categoryId);
            }
        }

        function notUsedTypeLabel(docType) {
            if (docType === 'personal') return 'Personal';
            if (docType === 'nomination') return 'Nomination';
            return 'Matter';
        }

        function notUsedTypeClass(docType) {
            if (docType === 'personal') return 'personal';
            if (docType === 'nomination') return 'nomination';
            return 'matter';
        }

        function notUsedFolderLabel(doc, res) {
            if (doc.doc_type !== 'personal') {
                return '—';
            }
            var categoryId = String(res.doc_category || doc.folder_name || '');
            var $btn = $('#personaldocuments-tab .subtab2-button[data-subtab2="' + categoryId + '"]');
            return $btn.length ? $btn.text().trim() : 'Personal folder';
        }

        function incrementNotUsedStatChips(docType) {
            var $total = $('#notuseddocuments-tab .not-used-stat-total');
            if ($total.length) {
                var match = ($total.text().match(/(\d+)/) || [0, 0]);
                $total.html('<i class="fa-solid fa-layer-group"></i> ' + (parseInt(match[1], 10) + 1) + ' total');
            }
            if (docType === 'personal') {
                var $personal = $('#notuseddocuments-tab .not-used-stat-personal');
                if ($personal.length) {
                    var pMatch = ($personal.text().match(/(\d+)/) || [0, 0]);
                    $personal.html('<i class="fa-solid fa-user"></i> ' + (parseInt(pMatch[1], 10) + 1) + ' personal');
                }
            } else {
                var $matter = $('#notuseddocuments-tab .not-used-stat-matter');
                if ($matter.length) {
                    var mMatch = ($matter.text().match(/(\d+)/) || [0, 0]);
                    $matter.html('<i class="fa-solid fa-briefcase"></i> ' + (parseInt(mMatch[1], 10) + 1) + ' matter');
                }
            }
        }

        function buildNotUsedDocumentActionsHtml(doc) {
            var folderId = doc.folder_name || '';
            var fileExt = doc.filetype || '';
            var fileName = doc.file_name || '';
            var previewUrl = doc.preview_url || (site_url + '/documents/preview/' + doc.id);
            var dlName = doc.myfile_key || (fileName + (fileExt ? '.' + fileExt : ''));
            var previewBtn = fileName
                ? '<button type="button" class="btn-not-used-action btn-not-used-preview" title="Preview" onclick="previewFile(\'' + fileExt + '\', \'' + previewUrl + '\', \'preview-container-notuseddocumnetlist\')"><i class="fa-solid fa-eye"></i></button>'
                : '';
            var downloadBtn = doc.myfile
                ? '<button type="button" class="btn-not-used-action btn-not-used-download download-file" data-document-id="' + doc.id + '" data-id="' + doc.id + '" data-filename="' + dlName + '" title="Download"><i class="fa-solid fa-download"></i></button>'
                : '';

            return '<a class="download-file" data-document-id="' + doc.id + '" data-id="' + doc.id + '" data-filename="' + dlName + '" href="#" style="display: none;"></a>' +
                '<a data-id="' + doc.id + '" class="deletenote" data-doccategory="' + doc.doc_type + '" data-href="deletedocs" href="javascript:;" style="display: none;"></a>' +
                '<a data-id="' + doc.id + '" class="backtodoc" data-doctype="' + doc.doc_type + '" data-doccategory="' + folderId + '" data-href="backtodoc" href="javascript:;" style="display: none;"></a>' +
                '<div class="not-used-actions">' +
                    previewBtn +
                    downloadBtn +
                    '<button type="button" class="btn-not-used-action btn-not-used-revert backtodoc" data-id="' + doc.id + '" data-doctype="' + doc.doc_type + '" data-doccategory="' + folderId + '" title="Revert to original folder">' +
                        '<i class="fa-solid fa-undo"></i> Revert' +
                    '</button>' +
                    '<button type="button" class="btn-not-used-action btn-not-used-delete" title="Delete permanently" onclick="$(\'.deletenote[data-id=\\\'' + doc.id + '\\\']\').trigger(\'click\');">' +
                        '<i class="fa-solid fa-trash-can"></i>' +
                    '</button>' +
                '</div>';
        }

        function buildNotUsedDocumentRowHtml(doc, res) {
            var previewUrl = doc.preview_url || (site_url + '/documents/preview/' + doc.id);
            var uploadedBy = res.Added_By || 'NA';
            var uploadedDate = doc.created_at ? formatClientDocDateTime(doc.created_at) : '';
            var uploadTitle = 'Uploaded by: ' + uploadedBy + (uploadedDate ? ' on ' + uploadedDate : '');
            var fileName = doc.file_name || '';
            var fileExt = doc.filetype || '';
            var displayName = fileName + (fileExt ? '.' + fileExt : '');
            var typeLabel = notUsedTypeLabel(doc.doc_type);
            var typeClass = notUsedTypeClass(doc.doc_type);
            var folderLabel = notUsedFolderLabel(doc, res);
            var searchBlob = [
                doc.checklist || '',
                fileName,
                fileExt,
                typeLabel,
                folderLabel
            ].join(' ').toLowerCase();

            var fileCell = '<span class="text-muted">N/A</span>';
            if (fileName) {
                fileCell = '<div data-id="' + doc.id + '" data-name="' + fileName + '" class="doc-row not-used-file-link" title="' + uploadTitle + '" ' +
                    'oncontextmenu="showNotUsedFileContextMenu(event, ' + doc.id + ', \'' + fileExt + '\', \'' + previewUrl + '\', \'' + doc.doc_type + '\', \'' + (doc.status || 'draft') + '\'); return false;">' +
                    '<a href="javascript:void(0);" onclick="previewFile(\'' + fileExt + '\', \'' + previewUrl + '\', \'preview-container-notuseddocumnetlist\')">' +
                        '<i class="fa-solid ' + documentFileIconClass(fileExt) + '"></i> <span>' + displayName + '</span>' +
                    '</a></div>';
            }

            return '<tr class="drow not-used-row" id="id_' + doc.id + '" data-search="' + searchBlob.replace(/"/g, '&quot;') + '">' +
                '<td><div class="not-used-checklist" title="' + uploadTitle + '">' +
                    '<span class="not-used-checklist-name">' + (doc.checklist || 'N/A') + '</span>' +
                    '<span class="not-used-meta">' + uploadTitle + '</span>' +
                '</div></td>' +
                '<td><span class="not-used-type-badge not-used-type-' + typeClass + '">' + typeLabel + '</span></td>' +
                '<td><span class="not-used-folder-label">' + folderLabel + '</span></td>' +
                '<td>' + fileCell + '</td>' +
                '<td class="not-used-actions-col">' + buildNotUsedDocumentActionsHtml(doc) + '</td>' +
            '</tr>';
        }

        $(document).on('click', '.backtodoc', function(e) {

            e.preventDefault();

            e.stopPropagation();

            $('#confirmBackToDocModal').modal('show');

            backto_doc_id = $(this).attr('data-id');

            backto_doc_href = $(this).attr('data-href');

            backto_doc_type = $(this).attr('data-doctype');

        });



        $(document).delegate('#confirmBackToDocModal .accept', 'click', function(){

            $('.popuploader').show();

            $.ajax({

                url: window.ClientDetailConfig.urls.admin + '/documents/back-to-doc',

                type:'POST',

                dataType:'json',

                data:{doc_id:backto_doc_id, doc_type:backto_doc_type },

                success:function(response){

                    $('.popuploader').hide();

                    var res = safeParseJsonResponse(response);
                    if (!res) return;
                    $('#confirmBackToDocModal').modal('hide');

                    if(res.status){

                        // Remove document from "Not Used" tab
                        $('.notuseddocumnetlist #id_'+res.doc_id).remove();

                        if ($('.notuseddocumnetlist .not-used-row').length === 0) {
                            $('#notUsedTableWrap').hide();
                            $('#notUsedEmptyState').show();
                            $('#notUsedDocsSearch').val('');
                        }

                        if (res.doc_type === 'personal' && res.docInfo) {
                            restorePersonalDocumentFromNotUsed(res.docInfo, res);
                        }

                        // Update activity log without page reload
                        getallactivities();
                        
                        // Show success message with info
                        var folderLabel = res.doc_category_title ? (' (' + res.doc_category_title + ')') : '';
                        var docTypeLabel = res.doc_type === 'personal'
                            ? 'Personal Documents' + folderLabel
                            : (res.doc_type === 'nomination' ? 'Nomination Documents' : 'Matter Documents');
                        if (typeof iziToast !== 'undefined' && typeof iziToast.success === 'function') {
                            iziToast.success({ message: 'Document reverted to ' + docTypeLabel, position: 'topRight' });
                        } else {
                            alert('Document reverted to ' + docTypeLabel);
                        }
                        

                    } else {
                        console.error('✗ Failed to move document back', res);
                        if (typeof iziToast !== 'undefined' && typeof iziToast.error === 'function') {
                            iziToast.error({ message: res.message || 'Failed to move document back', position: 'topRight' });
                        } else {
                            alert(res.message || 'Failed to move document back');
                        }
                    }

                },

                error: function(xhr, status, error) {
                    $('.popuploader').hide();
                    console.error('✗ AJAX error moving document back', {status: status, error: error});
                    if (typeof iziToast !== 'undefined' && typeof iziToast.error === 'function') {
                        iziToast.error({ message: 'Error moving document back. Please try again.', position: 'topRight' });
                    } else {
                        alert('Error moving document back. Please try again.');
                    }
                }

            });

        });





        var notid = '';

        var delhref = '';

        $('.deletenote').off('click').on('click', function(e) { 

            e.preventDefault();

            e.stopPropagation();

            $('#confirmModal').modal('show');

            notid = $(this).attr('data-id');

            delhref = $(this).attr('data-href');

           

        });



        $(document).on('click', '.deletenote', function(e) {

            e.preventDefault();

            e.stopPropagation();

            $('#confirmModal').modal('show');

            notid = $(this).attr('data-id');

            delhref = $(this).attr('data-href');

            

        });



        // Cost Agreement Deletion Handler

        var costAgreementId = '';

        $('.deleteCostAgreement').off('click').on('click', function(e) { 

            e.preventDefault();

            e.stopPropagation();

            $('#confirmCostAgreementModal').modal('show');

            costAgreementId = $(this).attr('data-id');

        });



        $(document).on('click', '.deleteCostAgreement', function(e) {

            e.preventDefault();

            e.stopPropagation();

            $('#confirmCostAgreementModal').modal('show');

            costAgreementId = $(this).attr('data-id');

        });



        // Cost Agreement Deletion Confirmation Handler

        $(document).delegate('#confirmCostAgreementModal .acceptCostAgreementDelete', 'click', function(){

            $('.popuploader').show();

            $.ajax({

                url: window.ClientDetailConfig.urls.deleteCostagreement,

                type:'GET',

                dataType:'json',

                data:{cost_agreement_id:costAgreementId},

                success:function(response){

                    $('.popuploader').hide();

                    var res = safeParseJsonResponse(response);
                    if (!res) return;
                    $('#confirmCostAgreementModal').modal('hide');

                    if(res.status){

                        // Remove the table row from the DOM

                        $('button[data-id="'+costAgreementId+'"]').closest('tr').remove();

                        

                        // Check if there are any remaining rows, if not show empty message

                        if($('.costform-table tbody tr').length === 0){

                            $('.costform-table').closest('.bg-white').html('<p class="text-gray-600 text-center py-6">No Cost Assignment records found for this client.</p>');

                        }

                        

                        // Show success message

                        alert('Cost Agreement deleted successfully!');

                    } else {

                        alert('Error: ' + (res.message || 'Failed to delete Cost Agreement'));

                    }

                },

                error: function(xhr, status, error) {

                    $('.popuploader').hide();

                    $('#confirmCostAgreementModal').modal('hide');

                    alert('Error: Failed to delete Cost Agreement. Please try again.');

                }

            });

        });
        $(document).delegate('#confirmModal .accept', 'click', function(){

            $('.popuploader').show();

            // Determine the correct URL based on delhref
            var deleteUrl;
            if(delhref == 'deletenote'){
                deleteUrl = window.ClientDetailConfig.urls.deleteNote;
            } else if(delhref == 'deleteclientportaldocs'){
                // Workflow checklist unused - route removed; no-op
                $('.popuploader').hide();
                $('#confirmModal').modal('hide');
                return;
            } else {
                deleteUrl = window.ClientDetailConfig.urls.admin + '/documents/delete';
            }

            $.ajax({

                url: deleteUrl,

                type:'GET',

                dataType:'json',

                data:{note_id:notid},

                success:function(response){

                    $('.popuploader').hide();

                    var res = safeParseJsonResponse(response);
                    if (!res) return;
                    $('#confirmModal').modal('hide');

                    if(res.status){

                        $('#note_id_'+notid).remove();

                        if(res.status == true){

                            $('#id_'+notid).remove();

                        }



                        if(delhref == 'deletedocs'){

                            $('.documnetlist_'+res.doc_categry+' #id_'+notid).remove();

                        }

                        // deleteservices block REMOVED - route and controller method no longer exist; /get-services route also removed

                        // DEPRECATED: Appointment system removed - deleteappointment route no longer exists
                        if(delhref == 'deleteappointment'){

                            // Commented out - appointment system removed
                            /*
                            $.ajax({

                                url: site_url+'/get-appointments',

                                type:'GET',

                                data:{clientid:window.ClientDetailConfig.clientId},

                                success: function(responses){

                                    $('.appointmentlist').html(responses);

                                }

                            });
                            */
                            console.warn('deleteappointment route has been removed - appointment system deprecated');

                        } else if(delhref == 'deleteclientportaldocs'){
                            // REMOVED - workflow checklist unused
                        } else if(delhref == 'deletenote'){

                            getallnotes();

                            

                        } else {

                            getallnotes();

                            

                        }

                        getallactivities();

                    }

                }

            });

        });







        var activitylogid = '';

        var delloghref = '';

        $(document).delegate('.deleteactivitylog', 'click', function(){

            $('#confirmLogModal').modal('show');

            activitylogid = $(this).attr('data-id');

            delloghref = $(this).attr('data-href');

        });



        $(document).delegate('#confirmLogModal .accept', 'click', function(){

            $('.popuploader').show();

            $.ajax({

                url: window.ClientDetailConfig.urls.admin + '/' + delloghref,

                type:'GET',

                dataType:'json',

                data:{activitylogid:activitylogid},

                success:function(response){

                    //$('.popuploader').hide();

                    var res = safeParseJsonResponse(response);
                    if (!res) return;
                    $('#confirmLogModal').modal('hide');

                    //location.reload();

                    if(res.status){

                        $('#activity_'+activitylogid).remove();

                        if(res.status == true){

                            $('#activity_'+activitylogid).remove();

                        }

                        getallactivities();

                    }

                }

            });

        });





        $(document).on('click', '.pinnote', function(e) {

            e.preventDefault();

            var noteId = $(this).attr('data-id');

            if (!noteId) {

                console.error('[PinNote] Missing data-id on pinnote element');

                return;

            }

            $('.popuploader').show();

            $.ajax({

                url: window.ClientDetailConfig.urls.pinNote,

                type:'GET',

                dataType:'json',

                data:{note_id: noteId},

                success:function(response){

                    if (response && response.status) {

                        if (typeof getallnotes === 'function') {

                            getallnotes();

                        }

                    } else {

                        $('.popuploader').hide();

                        if (typeof iziToast !== 'undefined' && typeof iziToast.error === 'function') {
                            iziToast.error({ message: response && response.message ? response.message : 'Failed to pin note', position: 'topRight' });
                        } else {
                            alert(response && response.message ? response.message : 'Failed to pin note');
                        }

                    }

                },

                error: function(xhr, status, error) {

                    $('.popuploader').hide();

                    console.error('[PinNote] AJAX error:', status, error, xhr.responseText);

                    if (typeof iziToast !== 'undefined' && typeof iziToast.error === 'function') {
                        iziToast.error({ message: 'Failed to pin note. Please try again.', position: 'topRight' });
                    } else {
                        alert('Failed to pin note. Please try again.');
                    }

                }

            });

        });



        //Pin activity log click

        $(document).delegate('.pinactivitylog', 'click', function(){

            $('.popuploader').show();

            $.ajax({

                url: window.ClientDetailConfig.urls.pinActivityLog + '/',

                type:'GET',

                dataType:'json',

                data:{activity_id:$(this).attr('data-id')},

                success:function(response){

                    getallactivities();

                }

            });

        });



        // createapplicationnewinvoice handler removed - Create Invoice from Schedule flow unused


        /** Shared Tom Select config for /clients/get-recipients (vendored helper in ts-init.js). */
        function crmDetailRecipientTomSelectOptions(dropdownParent, enableRemoteLoad) {
            var url = (window.ClientDetailConfig && window.ClientDetailConfig.urls && window.ClientDetailConfig.urls.getRecipients) || '';
            if (typeof buildCrmGetRecipientsMultiTomSelectConfig !== 'function') {
                return {};
            }
            return buildCrmGetRecipientsMultiTomSelectConfig({
                url: url,
                dropdownParent: dropdownParent,
                enableRemoteLoad: enableRemoteLoad,
                loadThrottle: 300
            });
        }

        $('.js-data-example-ajaxccapp').each(function () {
            initTS(this, crmDetailRecipientTomSelectOptions('#matteremailmodal', true));
        });

        $('.js-data-example-ajaxcontact').each(function () {
            initTS(this, crmDetailRecipientTomSelectOptions('#opentaskmodal', true));
        });



        //Function is used for complete the session

        $(document).delegate('.complete_session', 'click', function(){

            var client_id = $(this).attr('data-clientid'); //alert(client_id);

            if(client_id !=""){

                $.ajax({

                    type:'post',

                    url: window.ClientDetailConfig.urls.updateSessionCompleted,

                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},

                    data: {client_id:client_id },

                    success: function(response){

                        var obj = safeParseJsonResponse(response);
                        location.reload();

                    }

                });

            }

        });



        $(document).delegate('.clientemail', 'click', function(){

            if ($('.general_matter_checkbox_client_detail').is(':checked')) {

                var selectedMatterL = $('.general_matter_checkbox_client_detail').val();

            } else {

                var selectedMatterL = $('#sel_matter_id_client_detail').val();

            }

            $('#emailmodal #compose_client_matter_id').val(selectedMatterL);

            $('#emailmodal').modal('show');

            var array = [];

            var data = [];



            var id = $(this).attr('data-id');

            array.push(id);

            var email = $(this).attr('data-email');

            var name = $(this).attr('data-name');

            var status = 'Client';



            data.push({ id: id, name: name, email: email, status: status });



            var $to = $('.js-data-example-ajax');

            if ($to.length) {

                var elTo = $to[0];

                destroyTS(elTo);

                initTS(elTo, $.extend({}, crmDetailRecipientTomSelectOptions('#emailmodal', false), {

                    options: data,

                    items: array.map(function (x) { return String(x); })

                }));

                $to.trigger('change');

            }

        });



        $(document).delegate('.send-google-review', 'click', function(){

            var $btn = $(this);

            var templateId = $btn.data('template-id');

            if (!templateId) {

                if (typeof iziToast !== 'undefined') {

                    iziToast.warning({ message: 'Google Review template not found. Please create a CRM Email Template with name containing "Google Review" or alias "google_review".', position: 'topRight' });

                } else {

                    alert('Google Review template not found. Please create a CRM Email Template with name containing "Google Review" or alias "google_review" in Admin Console.');

                }

                return;

            }

            $('#emailmodal #compose_client_matter_id').val('');

            $('#emailmodal').modal('show');

            var array = [];

            var data = [];

            var id = $btn.attr('data-id');

            array.push(id);

            var email = $btn.attr('data-email');

            var name = $btn.attr('data-name');

            var status = 'Client';

            data.push({ id: id, name: name, email: email, status: status });

            var $toGr = $('.js-data-example-ajax');

            if ($toGr.length) {

                var elToGr = $toGr[0];

                destroyTS(elToGr);

                initTS(elToGr, $.extend({}, crmDetailRecipientTomSelectOptions('#emailmodal', false), {

                    options: data,

                    items: array.map(function (x) { return String(x); })

                }));

                $toGr.trigger('change');

            }

            $('#emailmodal').one('shown.bs.modal', function(){

                var $templateSelect = $('#emailmodal select.selecttemplate');

                if ($templateSelect.length && templateId) {

                    // Update Tom Select UI first (if initialized), then sync native val + fire handler
                    var _ts = (typeof getTomSelectInstance === 'function') ? getTomSelectInstance($templateSelect[0]) : null;
                    if (_ts) { _ts.setValue(String(templateId), true); }
                    $templateSelect.val(templateId).trigger('change');

                }

            });

        });



        $(document).delegate('.change_client_status', 'click', function(e){



            var v = $(this).attr('rating');

            $('.change_client_status').removeClass('active');

            $(this).addClass('active');



            $.ajax({

                url: window.ClientDetailConfig.urls.changeClientStatus,

                type:'GET',

                dataType:'json',

                data:{id:window.ClientDetailConfig.clientId,rating:v},

                success: function(response){

                    var res = safeParseJsonResponse(response);
                    if (!res) return;
                    if(res.status){



                        $('.custom-error-msg').html('<span class="alert alert-success">'+res.message+'</span>');

                        getallactivities();

                    }else{

                        $('.custom-error-msg').html('<span class="alert alert-danger">'+response.message+'</span>');

                    }



                }

            });

        });



        /*$(document).delegate('.selecttemplate', 'change', function(){

            var v = $(this).val();

            $.ajax({

                url: window.ClientDetailConfig.urls.getTemplates,

                type:'GET',

                dataType:'json',

                data:{id:v},

                success: function(response){

                    var res = safeParseJsonResponse(response);
                    if (!res) return;
                    $('.selectedsubject').val(res.subject);

                    clearEditor("#emailmodal .tinymce-editor");

                    setEditorContent("#emailmodal .tinymce-editor", res.description);

                    $("#emailmodal .tinymce-editor").val(res.description);

                }

            });

        });*/



        $(document).delegate('.selecttemplate', 'change', function(){

            var client_id = $(this).data('clientid'); //alert(client_id);

            var client_firstname = $(this).data('clientfirstname'); //alert(client_firstname);

            if (client_firstname) {

                client_firstname = client_firstname.charAt(0).toUpperCase() + client_firstname.slice(1);

            }

            var client_reference_number = $(this).data('clientreference_number'); //alert(client_reference_number);

            var company_name = window.__CRM_APP_NAME__ || '';

            var visa_valid_upto = $(this).data('clientvisaExpiry');

            if ( visa_valid_upto != '' && visa_valid_upto != '0000-00-00') {

                visa_valid_upto = visa_valid_upto;

            } else {

                visa_valid_upto = '';

            }



            var clientassignee_name = $(this).data('clientassignee_name');

            if ( clientassignee_name != '') {

                clientassignee_name = clientassignee_name;

            } else {

                clientassignee_name = '';

            }



            var v = $(this).val();

            $.ajax({

                url: window.ClientDetailConfig.urls.getTemplates,

                type:'GET',

                dataType:'json',

                data:{id:v},

                success: function(response){

                    var res = safeParseJsonResponse(response);
                    if (!res) return;



                    // Replace {Client First Name} with actual client name

                    //var subjct_message = res.subject

                    //.replace('{Client First Name}', client_firstname)

                    //.replace(/Ref:\s*\.{1,}\s*/, 'Ref: ' + client_reference_number)

                    //.replace(/Ref_\s*-{1,}\s*/, 'Ref_' + client_reference_number)

                    //.replace('{client reference}', client_reference_number);



                    var subjct_message = res.subject.replace('{Client First Name}', client_firstname).replace('{client reference}', client_reference_number);

                    var subjct_description = res.description
                    .replace('{Client First Name}', client_firstname)
                    .replace('{Company Name}', company_name)
                    .replace('{Visa Valid Upto}', visa_valid_upto)
                    .replace('{Client Assignee Name}', clientassignee_name)
                    .replace('{client reference}', client_reference_number);

                    // Apply First email macro values when available (from getComposeDefaults)
                    var macroVals = $('#emailmodal').data('composeMacroValues');
                    if (macroVals) {
                        var repl = function(str) {
                            if (!str) return '';
                            str = str.replace(/\{ClientID\}/g, macroVals.ClientID || '');
                            str = str.replace(/\{ApplicantGivenNames\}/g, macroVals.ApplicantGivenNames || macroVals.client_firstname || client_firstname || '');
                            str = str.replace(/\{visa_apply\}/g, macroVals.visa_apply || '');
                            var blockTotalFees = macroVals.Blocktotalfeesinclgst || macroVals.Blocktotalfeesincltax || '';
                            str = str.replace(/\{Blocktotalfeesincltax\}/g, blockTotalFees);
                            str = str.replace(/\$\{Blocktotalfeesincltax\}/g, blockTotalFees);
                            str = str.replace(/\{Blocktotalfeesinclgst\}/g, blockTotalFees);
                            str = str.replace(/\$\{Blocktotalfeesinclgst\}/g, blockTotalFees);
                            var b1 = macroVals.Block1feesinclgst || macroVals.Block1feesincltax || '';
                            var b2 = macroVals.Block2feesinclgst || macroVals.Block2feesincltax || '';
                            var b3 = macroVals.Block3feesinclgst || macroVals.Block3feesincltax || '';
                            str = str.replace(/\{Block1feesinclgst\}/g, b1);
                            str = str.replace(/\$\{Block1feesinclgst\}/g, b1);
                            str = str.replace(/\{Block1feesincltax\}/g, b1);
                            str = str.replace(/\$\{Block1feesincltax\}/g, b1);
                            str = str.replace(/\{Block2feesinclgst\}/g, b2);
                            str = str.replace(/\$\{Block2feesinclgst\}/g, b2);
                            str = str.replace(/\{Block2feesincltax\}/g, b2);
                            str = str.replace(/\$\{Block2feesincltax\}/g, b2);
                            str = str.replace(/\{Block3feesinclgst\}/g, b3);
                            str = str.replace(/\$\{Block3feesinclgst\}/g, b3);
                            str = str.replace(/\{Block3feesincltax\}/g, b3);
                            str = str.replace(/\$\{Block3feesincltax\}/g, b3);
                            str = str.replace(/\{TotalDoHASurcharges\}/g, macroVals.TotalDoHASurcharges || '');
                            str = str.replace(/\$\{TotalDoHASurcharges\}/g, macroVals.TotalDoHASurcharges || '');
                            str = str.replace(/\{TotalEstimatedOthCosts\}/g, macroVals.TotalEstimatedOthCosts || '');
                            str = str.replace(/\$\{TotalEstimatedOthCosts\}/g, macroVals.TotalEstimatedOthCosts || '');
                            str = str.replace(/\{GrandTotalFeesAndCosts\}/g, macroVals.GrandTotalFeesAndCosts || '');
                            str = str.replace(/\$\{GrandTotalFeesAndCosts\}/g, macroVals.GrandTotalFeesAndCosts || '');
                            var pdfUrl = macroVals.PDF_url_for_sign || '';
                            var pdfLink = pdfUrl ? '<a href="' + pdfUrl + '" target="_blank" rel="noopener noreferrer" style="color:#2563eb;text-decoration:underline;word-break:break-all;">' + pdfUrl + '</a>' : '';
                            str = str.replace(/\{PDF_url_for_sign\}/g, pdfLink);
                            return str;
                        };
                        subjct_message = repl(subjct_message);
                        subjct_description = repl(subjct_description);
                    }

                    $('.selectedsubject').val(subjct_message);

                    clearEditor("#emailmodal .tinymce-editor");



                    // Set content in TinyMCE editor
                    if (typeof setTinyMCEContent === 'function') {
                        setTinyMCEContent('compose_email_message', subjct_description);
                    } else if (typeof tinymce !== 'undefined' && tinymce.get('compose_email_message')) {
                        tinymce.get('compose_email_message').setContent(subjct_description);
                    } else {
                        $("#compose_email_message").val(subjct_description);
                    }

                }

            });

        });



        $(document).delegate('.selectmattertemplate', 'change', function(){

            var v = $(this).val();

            $.ajax({

                url: window.ClientDetailConfig.urls.getTemplates,

                type:'GET',

                dataType:'json',

                data:{id:v},

                success: function(response){

                    var res = safeParseJsonResponse(response);
                    if (!res) return;
                    $('.selectedappsubject').val(res.subject);

                    // Set content in TinyMCE editor
                    if (typeof setTinyMCEContent === 'function') {
                        setTinyMCEContent('matter_email_message', res.description);
                    } else if (typeof tinymce !== 'undefined' && tinymce.get('matter_email_message')) {
                        tinymce.get('matter_email_message').setContent(res.description);
                    } else {
                        $("#matter_email_message").val(res.description);
                    }

                }

            });

        });



        $('.js-data-example-ajax').each(function () {

            initTS(this, crmDetailRecipientTomSelectOptions('#emailmodal', true));

        });



        $('.js-data-example-ajaxccd').each(function () {

            initTS(this, crmDetailRecipientTomSelectOptions('#emailmodal', true));

        });

        $('.js-data-example-ajaxbcc').each(function () {

            initTS(this, crmDetailRecipientTomSelectOptions('#emailmodal', true));

        });



        /* $(".table-2").dataTable({

            "searching": false,

            "lengthChange": false,

        "columnDefs": [

            { "sortable": false, "targets": [0, 2, 3] }

        ],

        order: [[1, "desc"]] //column indexes is zero based



        }); */

        // Custom search: filter checklist table by matter when composeChecklistFilterIds is set (register once)
        if (!window.composeChecklistSearchRegistered && $.fn.dataTable && $.fn.dataTable.ext) {
            window.composeChecklistSearchRegistered = true;
            $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
            if ($(settings.nTable).attr('id') !== 'mychecklist-datatable') return true;
            var filterIds = window.composeChecklistFilterIds;
            if (filterIds === undefined || filterIds === null) return true;
            var rowNode = null;
            try { rowNode = new $.fn.dataTable.Api(settings).row(dataIndex).node(); } catch(e) {}
            if (!rowNode) return true;
            var id = $(rowNode).attr('data-checklist-id') || $(rowNode).find('.checklistfile-cb').val();
            if (!id) return true;
            var idNum = parseInt(id, 10);
            return filterIds.some(function(f) { return f == idNum || String(f) === String(id); });
            });
        }

        if ($.fn.dataTable && $('#mychecklist-datatable').length && !$.fn.dataTable.isDataTable('#mychecklist-datatable')) {
        $('#mychecklist-datatable').dataTable({"searching": true,});
        }

        if ($.fn.dataTable && $('.invoicetable').length) {
            $('.invoicetable').each(function () {
                if ($.fn.dataTable.isDataTable(this)) {
                    return;
                }
                $(this).dataTable({
                    "searching": false,
                    "lengthChange": false,
                    "columnDefs": [
                        { "orderable": false, "targets": [0, 2, 3] }
                    ],
                    order: [[1, "desc"]]
                });
            });
        }





        $(document).delegate('#intrested_workflow', 'change', function(){
			var v = $('#intrested_workflow option:selected').val();

            if(v != ''){

                $('.popuploader').show();

                $.ajax({

                    url: window.ClientDetailConfig.urls.getPartner,

                    type:'GET',

                    data:{cat_id:v},

                    success:function(response){

                        $('.popuploader').hide();

                        $('#intrested_partner').html(response);



                        $("#intrested_partner").val('').trigger('change');

                    $("#intrested_product").val('').trigger('change');

                    $("#intrested_branch").val('').trigger('change');

                    }

                });

            }

	    });



        $(document).delegate('#edit_intrested_workflow', 'change', function(){



                    var v = $('#edit_intrested_workflow option:selected').val();



                    if(v != ''){

                            $('.popuploader').show();

            $.ajax({

                url: window.ClientDetailConfig.urls.getPartner,

                type:'GET',

                data:{cat_id:v},

                success:function(response){

                    $('.popuploader').hide();

                    $('#edit_intrested_partner').html(response);



                    $("#edit_intrested_partner").val('').trigger('change');

                $("#edit_intrested_product").val('').trigger('change');

                $("#edit_intrested_branch").val('').trigger('change');

                }

            });

                    }

        });



        // REMOVED: Interested partner/product/branch dropdowns (orphaned - no routes exist)
        // The add_interested_service modal exists but has no UI triggers
        // Routes getProduct and getBranch were never implemented





        



        // Ensure the event listener is attached to all .add-document buttons

        $(document).on('click', '.add-document', function(e) {

            e.preventDefault(); // Prevent default anchor behavior

            var fileid = $(this).data('fileid');

            $('#upload_form_' + fileid).find('.docupload').click();

        });



        // Use on() instead of delegate() for better compatibility
        $(document).on('change', '.docupload', function () {

            var fileInput = this;

            var file = fileInput.files[0];

            if (!file) {

                return;
            }


            var fileidL = $(this).attr("data-fileid");
            var doccategoryL = $(this).attr("data-doccategory");
            

            var $form = $(this).closest('form');
            if (!$form.length) {
                console.error('❌ Form not found for file input');
                alert('Error: Upload form not found. Please refresh the page.');
                return;
            }

            var formData = new FormData($form[0]);



            var validNameRegex = /^[a-zA-Z0-9_\-\.\s\$\(\),&+]+$/;

            if (!validNameRegex.test(file.name)) {

                alert("File name can only contain letters, numbers, dashes (-), underscores (_), spaces, dots (.), dollar signs ($), parentheses (( )), commas (,), ampersands (&), and plus signs (+). Please rename the file and try again.");

                $(this).val('');

                return false;

            }

            var doctype = $form.find('[name="doctype"]').val();
            if (doctype === 'personal' && typeof isPersonalDocVideoFile === 'function' && isPersonalDocVideoFile(file)) {
                var $zone = $form.find('.personal-doc-drag-zone');
                $(fileInput).val('');
                if (!$zone.length) {
                    alert('Upload zone not found. Please refresh the page.');
                    return false;
                }
                uploadPersonalDocFromZone($zone, file);
                return false;
            }



            // Show immediate feedback that upload is starting

            $('.custom-error-msg').html('<span class="alert alert-info"><i class="fa-solid fa-clock"></i> Uploading document...</span>');



            $.ajax({

                url: site_url + '/documents/upload-edu-document',

                type: 'POST',

                dataType: 'json',

                data: formData,

                contentType: false,

                processData: false,

                success: function (ress) {

                    if (ress.status) {

                        $('.custom-error-msg').html('<span class="alert alert-success">' + ress.message + '</span>');



                        var row = $('#id_' + fileidL);

                        var docNameWithoutExt = ress.filename.replace(/\.[^/.]+$/, "").replace(/\s+/g, "_").toLowerCase();

                        var previewUrl = ress.preview_url || (site_url + '/documents/preview/' + (ress.document_id || fileidL));
                        var documentId = ress.document_id || fileidL;



                        // Replace upload TD content (Column 1 = File Name)

                        var uploadTd = row.find('td').eq(1);

                        uploadTd.html(

                            '<div data-id="' + fileidL + '" data-name="' + docNameWithoutExt + '" class="doc-row" title="Uploaded by: ' + (ress.uploaded_by || 'Staff') + (ress.uploaded_at ? ' on ' + formatClientDocDateTime(ress.uploaded_at) : '') + '" oncontextmenu="showFileContextMenu(event, ' + fileidL + ', \'' + ress.filetype + '\', \'' + previewUrl + '\', \'' + doccategoryL + '\', \'' + (ress.status_value || 'draft') + '\'); return false;">' +

                                '<a href="javascript:void(0);" onclick="previewFile(\'' + ress.filetype + '\', \'' + previewUrl + '\', \'preview-container-' + doccategoryL + '\')">' +

                                    '<i class="fa-solid ' + documentFileIconClass(ress.filetype) + '"></i> <span>' + ress.filename + '</span>' +

                                '</a>' +

                            '</div>'

                        );



                        // Add hidden elements for context menu actions (Column 2 = Actions)

                        var actionTd = row.find('td').eq(2);

                        actionTd.html(

                            '<a class="renamechecklist" data-id="' + fileidL + '" href="javascript:;" style="display: none;"></a>' +

                            '<a class="renamedoc" data-id="' + fileidL + '" href="javascript:;" style="display: none;"></a>' +

                            '<a class="download-file" data-id="' + documentId + '" data-document-id="' + documentId + '" data-filename="' + ress.filekey + '" href="#" style="display: none;"></a>' +

                            '<a class="notuseddoc" data-id="' + fileidL + '" data-doctype="' + ress.doctype + '" data-href="notuseddoc" href="javascript:;" style="display: none;"></a>'

                        );

                        

                        // Ensure the row has the proper class for event delegation

                        row.addClass('drow');

                    } else {

                        $('.custom-error-msg').html('<span class="alert alert-danger">' + ress.message + '</span>');

                    }

                    getallactivities();

                }

            }).fail(function(xhr, status, error) {
                console.error('❌ AJAX Upload Error:', {
                    status: status,
                    error: error,
                    response: xhr.responseText
                });
                $('.custom-error-msg').html('<span class="alert alert-danger">Upload failed: ' + (error || 'Please try again') + '</span>');
            }).always(function() {
                // Clear input after upload attempt (success or failure)
                fileInput.value = '';
            });

        });




        // --- DRAG AND DROP: Personal & matter documents ---

        // Prevent browser's default drag behavior (required for file drops to work)
        // This must be on document level, but we let drop zones handle their own events
        $(document).on('dragover', function(e) {
            // Allow drop zones to handle their own dragover events
            if ($(e.target).closest('.personal-doc-drag-zone, .visa-doc-drag-zone, .nomination-doc-drag-zone, .bulk-upload-dropzone, .bulk-upload-dropzone-visa, .bulk-upload-dropzone-nomination, .outlook-container, .inline-drop-zone, #dragDropOverlay').length) {
                e.preventDefault();
                return;
            }
            // For other areas, prevent default to allow file drops
            e.preventDefault();
        });

        $(document).on('drop', function(e) {
            // Allow drop zones to handle their own drop events
            if ($(e.target).closest('.personal-doc-drag-zone, .visa-doc-drag-zone, .nomination-doc-drag-zone, .bulk-upload-dropzone, .bulk-upload-dropzone-visa, .bulk-upload-dropzone-nomination, .outlook-container, .inline-drop-zone, #dragDropOverlay').length) {
                return; // Let the drop zone handler take over
            }
            // For other areas, prevent default to prevent browser from opening file
            e.preventDefault();
        });

        // Personal Documents - Drag and Drop Handlers
        
        // Debug: Check if handlers are being attached

        
        $(document).on('dragover', '.personal-doc-drag-zone', function(e) {

            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('drag_over');
            return false;
        });
        
        $(document).on('dragenter', '.personal-doc-drag-zone', function(e) {

            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('drag_over');
            return false;
        });
        
        $(document).on('dragleave', '.personal-doc-drag-zone', function(e) {

            e.preventDefault();
            e.stopPropagation();
            // Only remove class if leaving the drop zone itself, not child elements
            var rect = this.getBoundingClientRect();
            var x = e.originalEvent.clientX;
            var y = e.originalEvent.clientY;
            
            if (x <= rect.left || x >= rect.right || y <= rect.top || y >= rect.bottom) {
                $(this).removeClass('drag_over');
            }
            return false;
        });
        
        $(document).on('drop', '.personal-doc-drag-zone', function(e) {

            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('drag_over');
            
            var files = e.originalEvent.dataTransfer.files;
            if (files && files.length > 0) {

                handlePersonalDocDragDrop($(this), files[0]);
            } else {
                console.error('❌ No files in drop event');
            }
            return false;
        });
        
        $(document).on('click', '.personal-doc-drag-zone', function(e) {

            e.preventDefault();
            e.stopPropagation();
            var fileid = $(this).data('fileid');

            var fileInput = $('#upload_form_' + fileid).find('.docupload');

            if (fileInput.length > 0) {
                fileInput.click();
            } else {
                console.error('❌ File input not found for fileid:', fileid);
            }
            return false;
        });
        
        // Matter documents - drag and drop handlers
        
        $(document).delegate('.visa-doc-drag-zone', 'dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('drag_over');
            return false;
        });
        
        $(document).delegate('.visa-doc-drag-zone', 'dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('drag_over');
            return false;
        });
        
        $(document).delegate('.visa-doc-drag-zone', 'drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('drag_over');
            
            var files = e.originalEvent.dataTransfer.files;
            if (files && files.length > 0) {
                handleVisaDocDragDrop($(this), files[0]);
            }
            return false;
        });
        
        $(document).delegate('.visa-doc-drag-zone', 'click', function(e) {
            e.preventDefault();
            var fileid = $(this).data('fileid');
            var fileInput = $('#mig_upload_form_' + fileid).find('.migdocupload');
            fileInput.click();
        });

        $(document).delegate('.nomination-doc-drag-zone', 'dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('drag_over');
            return false;
        });

        $(document).delegate('.nomination-doc-drag-zone', 'dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('drag_over');
            return false;
        });

        $(document).delegate('.nomination-doc-drag-zone', 'drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('drag_over');

            var files = e.originalEvent.dataTransfer.files;
            if (files && files.length > 0) {
                handleVisaDocDragDrop($(this), files[0]);
            }
            return false;
        });

        $(document).delegate('.nomination-doc-drag-zone', 'click', function(e) {
            e.preventDefault();
            var fileid = $(this).data('fileid');
            var fileInput = $('#mig_upload_form_' + fileid).find('.migdocupload');
            fileInput.click();
        });
        
        // Personal Documents - video folder selection + upload helpers

        var _videoFolderPromptCallback = null;
        var _videoFolderPromptCancel = null;

        function isPersonalDocVideoFile(file) {
            if (!file || !file.name) {
                return false;
            }
            var ext = (file.name.split('.').pop() || '').toLowerCase();
            return /^(mp4|webm|mov|m4v|avi|mkv)$/.test(ext);
        }

        function getPersonalDocumentFolders() {
            var folders = [];
            $('#personaldocuments-tab .subtab2-button').each(function() {
                var id = $(this).data('subtab2');
                var title = $(this).text().trim();
                if (id != null && title) {
                    folders.push({ id: String(id), title: title });
                }
            });
            return folders;
        }

        function activatePersonalDocumentFolder(categoryId) {
            var $btn = $('#personaldocuments-tab .subtab2-button[data-subtab2="' + categoryId + '"]');
            if ($btn.length) {
                $btn.trigger('click');
            }
        }

        function findEmptyPersonalDocChecklistId(categoryId) {
            var found = null;
            $('.documnetlist_' + categoryId + ' tr.drow').each(function() {
                var $row = $(this);
                if ($row.find('.personal-doc-drag-zone').length && !$row.find('.doc-row').length) {
                    found = ($row.attr('id') || '').replace('id_', '');
                    return false;
                }
            });
            return found;
        }

        function createPersonalDocChecklist(categoryId, checklistName, clientId, callback) {
            $.ajax({
                type: 'POST',
                url: site_url + '/documents/add-edu-checklist',
                traditional: true,
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    clientid: clientId,
                    folder_name: categoryId,
                    doccategory: categoryId,
                    doctype: 'personal',
                    type: 'client',
                    checklist: [checklistName]
                },
                success: function(response) {
                    var obj = typeof response === 'string' ? JSON.parse(response) : response;
                    if (!obj.status) {
                        callback(obj.message || 'Failed to create checklist');
                        return;
                    }

                    $('.documnetlist_' + categoryId).html(obj.data);
                    $('.griddata_' + categoryId).html(obj.griddata);
                    if (typeof initPersonalDocDragDrop === 'function') {
                        setTimeout(initPersonalDocDragDrop, 100);
                    }

                    var fileId = null;
                    $('.documnetlist_' + categoryId + ' .personalchecklist-row').each(function() {
                        if ($(this).data('personalchecklistname') === checklistName) {
                            var $row = $(this).closest('tr');
                            if ($row.find('.personal-doc-drag-zone').length) {
                                fileId = ($row.attr('id') || '').replace('id_', '');
                                return false;
                            }
                        }
                    });
                    if (!fileId) {
                        fileId = findEmptyPersonalDocChecklistId(categoryId);
                    }
                    callback(null, fileId);
                },
                error: function() {
                    callback('Failed to create checklist. Please try again.');
                }
            });
        }

        function resolvePersonalVideoUploadTarget(selectedCategoryId, file, context, callback) {
            activatePersonalDocumentFolder(selectedCategoryId);

            var currentCategoryId = context.doccategory != null ? String(context.doccategory) : '';
            var currentFileId = context.fileid != null ? String(context.fileid) : '';

            if (selectedCategoryId === currentCategoryId && currentFileId) {
                var $currentRow = $('#id_' + currentFileId);
                if ($currentRow.find('.personal-doc-drag-zone').length && !$currentRow.find('.doc-row').length) {
                    callback(null, currentFileId, selectedCategoryId);
                    return;
                }
            }

            var emptyId = findEmptyPersonalDocChecklistId(selectedCategoryId);
            if (emptyId) {
                callback(null, emptyId, selectedCategoryId);
                return;
            }

            var checklistName = (file.name.replace(/\.[^/.]+$/, '').trim() || 'Video');
            createPersonalDocChecklist(selectedCategoryId, checklistName, context.clientId, function(err, fileId) {
                if (err) {
                    callback(err);
                    return;
                }
                if (!fileId) {
                    callback('No checklist available in the selected folder.');
                    return;
                }
                callback(null, fileId, selectedCategoryId);
            });
        }

        function promptPersonalVideoUploadFolder(defaultCategoryId, onSelected, onCancel) {
            var folders = getPersonalDocumentFolders();
            if (!folders.length) {
                alert('No personal document folders found.');
                if (typeof onCancel === 'function') {
                    onCancel();
                }
                return;
            }

            var $select = $('#videoUploadFolderSelect').empty();
            folders.forEach(function(folder) {
                var selected = String(folder.id) === String(defaultCategoryId) ? ' selected' : '';
                $select.append('<option value="' + folder.id + '"' + selected + '>' + $('<div/>').text(folder.title).html() + '</option>');
            });

            $('#videoUploadFolderError').hide();
            _videoFolderPromptCallback = onSelected;
            _videoFolderPromptCancel = onCancel;
            $('#videoUploadFolderModal').modal('show');
        }

        function showPersonalDocVideoToast(success, message) {
            if (typeof crmNotify !== 'undefined') {
                if (success && typeof crmNotify.success === 'function') {
                    crmNotify.success({
                        title: 'Success',
                        message: message,
                        position: 'topRight',
                        timeout: 6000,
                        transitionIn: 'fadeInDown',
                        transitionOut: 'fadeOutUp'
                    });
                } else if (typeof crmNotify.error === 'function') {
                    crmNotify.error({
                        title: 'Error',
                        message: message,
                        position: 'topRight',
                        timeout: 8000,
                        transitionIn: 'fadeInDown',
                        transitionOut: 'fadeOutUp'
                    });
                }
            } else {
                alert(message);
            }
        }

        var _pvuProcessingTimer = null;
        var _pvuCurrentPercent = 0;

        function formatPersonalVideoFileSize(bytes) {
            if (!bytes || bytes <= 0) {
                return '';
            }
            var units = ['Bytes', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(1024));
            var value = bytes / Math.pow(1024, i);
            return Math.round(value * 100) / 100 + ' ' + units[i];
        }

        function clearPersonalVideoProcessingPulse() {
            if (_pvuProcessingTimer) {
                clearInterval(_pvuProcessingTimer);
                _pvuProcessingTimer = null;
            }
        }

        function updatePersonalVideoUploadLoader(step, percent, message) {
            var $overlay = $('#personalVideoUploadOverlay');
            if (!$overlay.length) {
                return;
            }

            _pvuCurrentPercent = Math.max(_pvuCurrentPercent, Math.max(0, Math.min(100, percent || 0)));
            $('#pvuProgressBar').css('width', _pvuCurrentPercent + '%');
            $('#pvuPercent').text(Math.round(_pvuCurrentPercent) + '%');
            if (message) {
                $('#pvuStatusMessage').text(message);
            }

            var stepOrder = ['upload', 'queued', 'processing', 'complete'];
            var activeIndex = stepOrder.indexOf(step);

            $('.pvu-step').each(function() {
                var liStep = $(this).data('step');
                var idx = stepOrder.indexOf(liStep);
                $(this).removeClass('active done error');
                if (step === 'error' && idx === activeIndex) {
                    $(this).addClass('error');
                } else if (idx < activeIndex) {
                    $(this).addClass('done');
                } else if (idx === activeIndex) {
                    $(this).addClass('active');
                }
            });

            var $panel = $('.personal-video-upload-panel');
            $panel.removeClass('is-success is-error');
            if (step === 'complete') {
                $panel.addClass('is-success');
            } else if (step === 'error') {
                $panel.addClass('is-error');
            }
        }

        function startPersonalVideoProcessingPulse(step, fromPercent, toPercent, message) {
            clearPersonalVideoProcessingPulse();
            var current = Math.max(_pvuCurrentPercent, fromPercent);
            updatePersonalVideoUploadLoader(step, current, message);
            _pvuProcessingTimer = setInterval(function() {
                if (current < toPercent) {
                    current += 0.6;
                    updatePersonalVideoUploadLoader(step, current, message);
                }
            }, 350);
        }

        function showPersonalVideoUploadLoader(options) {
            options = options || {};
            clearPersonalVideoProcessingPulse();
            _pvuCurrentPercent = 0;

            var $overlay = $('#personalVideoUploadOverlay');
            if (!$overlay.length) {
                return;
            }

            $('#pvuTitle').text(options.title || 'Uploading Video');
            $('#pvuFilename').text(options.filename || 'Video file');
            $('#pvuMeta').text(options.meta || (options.fileSize ? formatPersonalVideoFileSize(options.fileSize) : ''));
            $('.personal-video-upload-panel').removeClass('is-success is-error');
            $overlay.addClass('is-visible').attr('aria-hidden', 'false');
            updatePersonalVideoUploadLoader('upload', 0, options.message || 'Preparing upload…');
        }

        function hidePersonalVideoUploadLoader(delayMs) {
            clearPersonalVideoProcessingPulse();
            var $overlay = $('#personalVideoUploadOverlay');
            if (!$overlay.length) {
                return;
            }

            var hide = function() {
                $overlay.removeClass('is-visible').attr('aria-hidden', 'true');
                _pvuCurrentPercent = 0;
                $('.personal-video-upload-panel').removeClass('is-success is-error');
            };

            if (delayMs && delayMs > 0) {
                setTimeout(hide, delayMs);
            } else {
                hide();
            }
        }

        function pollPersonalVideoUploadStatus(uploadToken, callback, onStatus) {
            var attempts = 0;
            var maxAttempts = 900;
            var pollIntervalMs = 800;
            var lastStatus = '';

            function handleStatus(status, res) {
                if (status === lastStatus) {
                    return;
                }
                lastStatus = status;

                if (typeof onStatus === 'function') {
                    onStatus(status, res);
                }

                if (status === 'queued') {
                    startPersonalVideoProcessingPulse('queued', 46, 58, 'Video queued — waiting to process…');
                } else if (status === 'processing') {
                    startPersonalVideoProcessingPulse('processing', 58, 90, 'Processing video and saving to documents…');
                }
            }

            function poll() {
                $.ajax({
                    url: site_url + '/documents/personal-video-upload-status/' + encodeURIComponent(uploadToken),
                    method: 'GET',
                    dataType: 'json',
                    success: function(res) {
                        var status = (res.status || '').toLowerCase();
                        handleStatus(status, res);

                        if (status === 'completed') {
                            clearPersonalVideoProcessingPulse();
                            updatePersonalVideoUploadLoader('complete', 100, 'Video uploaded successfully!');
                            callback(true, res.message || 'Video uploaded successfully.');
                            return;
                        }
                        if (status === 'failed') {
                            clearPersonalVideoProcessingPulse();
                            updatePersonalVideoUploadLoader('error', _pvuCurrentPercent, res.message || 'Video upload failed.');
                            callback(false, res.message || 'Video upload failed.');
                            return;
                        }

                        attempts++;
                        if (attempts >= maxAttempts) {
                            clearPersonalVideoProcessingPulse();
                            updatePersonalVideoUploadLoader('error', _pvuCurrentPercent, 'Upload timed out.');
                            callback(false, 'Video upload timed out. Please refresh and check the document list.');
                            return;
                        }
                        setTimeout(poll, pollIntervalMs);
                    },
                    error: function(xhr) {
                        if (xhr.status === 403 || xhr.status === 404) {
                            clearPersonalVideoProcessingPulse();
                            var deniedMessage = (xhr.responseJSON && xhr.responseJSON.message)
                                ? xhr.responseJSON.message
                                : 'Unable to check upload status.';
                            updatePersonalVideoUploadLoader('error', _pvuCurrentPercent, deniedMessage);
                            callback(false, deniedMessage);
                            return;
                        }
                        attempts++;
                        if (attempts >= maxAttempts) {
                            clearPersonalVideoProcessingPulse();
                            updatePersonalVideoUploadLoader('error', _pvuCurrentPercent, 'Unable to check upload status.');
                            callback(false, 'Unable to check upload status.');
                            return;
                        }
                        setTimeout(poll, pollIntervalMs);
                    }
                });
            }

            poll();
        }

        function waitForPersonalVideoUploads(uploadTokens, callback, options) {
            options = options || {};
            if (!uploadTokens || uploadTokens.length === 0) {
                callback(true, 'Upload complete.');
                return;
            }

            if (uploadTokens.length === 1 && !options.skipLoader) {
                showPersonalVideoUploadLoader({
                    title: 'Processing Video',
                    filename: options.filename || 'Video file',
                    fileSize: options.fileSize || 0,
                    message: 'Finishing background processing…'
                });
                updatePersonalVideoUploadLoader('queued', 46, 'Video queued — waiting to process…');
            } else if (!options.skipLoader) {
                showPersonalVideoUploadLoader({
                    title: 'Processing Videos',
                    filename: uploadTokens.length + ' video file(s)',
                    message: 'Processing uploaded videos…'
                });
                updatePersonalVideoUploadLoader('queued', 46, 'Videos queued — waiting to process…');
            }

            var remaining = uploadTokens.length;
            var failed = false;
            var resultMessage = null;
            var completedCount = 0;

            uploadTokens.forEach(function(token) {
                pollPersonalVideoUploadStatus(token, function(success, message) {
                    if (!success) {
                        failed = true;
                    }
                    if (!resultMessage) {
                        resultMessage = message;
                    }
                    completedCount++;
                    remaining--;

                    if (uploadTokens.length > 1 && !options.skipLoader) {
                        var batchPercent = 46 + Math.round((completedCount / uploadTokens.length) * 54);
                        updatePersonalVideoUploadLoader(
                            remaining === 0 && !failed ? 'complete' : 'processing',
                            batchPercent,
                            'Processed ' + completedCount + ' of ' + uploadTokens.length + ' video(s)…'
                        );
                    }

                    if (remaining === 0) {
                        if (!failed && uploadTokens.length > 1) {
                            updatePersonalVideoUploadLoader('complete', 100, 'All videos uploaded successfully!');
                        }
                        callback(!failed, failed ? (resultMessage || 'One or more video uploads failed.') : (resultMessage || 'Video uploaded successfully.'));
                    }
                }, function(status) {
                    if (uploadTokens.length > 1 && (status === 'queued' || status === 'processing')) {
                        updatePersonalVideoUploadLoader('processing', 58 + completedCount * 5, 'Processing videos in queue…');
                    }
                });
            });
        }

        window.showPersonalDocVideoToast = showPersonalDocVideoToast;
        window.waitForPersonalVideoUploads = waitForPersonalVideoUploads;
        window.showPersonalVideoUploadLoader = showPersonalVideoUploadLoader;
        window.hidePersonalVideoUploadLoader = hidePersonalVideoUploadLoader;
        window.updatePersonalVideoUploadLoader = updatePersonalVideoUploadLoader;

        function performPersonalDocUpload(file, targetFileId, targetCategoryId, dragZone, options) {
            options = options || {};
            var isVideoUpload = isPersonalDocVideoFile(file);
            var form = $('#upload_form_' + targetFileId);
            if (!form.length) {
                alert('Upload form not found for the selected folder. Please refresh the page and try again.');
                if (dragZone && dragZone.length) {
                    dragZone.removeClass('uploading');
                }
                return;
            }

            var formData = new FormData(form[0]);
            formData.set('fileid', targetFileId);
            formData.set('document_upload', file);

            if (dragZone && dragZone.length) {
                dragZone.addClass('uploading');
            }
            if (isVideoUpload) {
                showPersonalVideoUploadLoader({
                    filename: file.name,
                    fileSize: file.size,
                    message: 'Uploading video to server…'
                });
            } else {
                $('.custom-error-msg').html('<span class="alert alert-info"><i class="fa-solid fa-clock"></i> Uploading document...</span>');
            }

            $.ajax({
                url: site_url + '/documents/upload-edu-document',
                type: 'POST',
                dataType: 'json',
                data: formData,
                contentType: false,
                processData: false,
                timeout: isVideoUpload ? 0 : undefined,
                xhr: function() {
                    var xhr = new window.XMLHttpRequest();
                    if (isVideoUpload) {
                        xhr.upload.addEventListener('progress', function(e) {
                            if (e.lengthComputable) {
                                var uploadPct = Math.round((e.loaded / e.total) * 100);
                                var overallPct = Math.round((e.loaded / e.total) * 45);
                                updatePersonalVideoUploadLoader(
                                    'upload',
                                    overallPct,
                                    'Uploading video… ' + uploadPct + '%'
                                );
                                if (uploadPct >= 100) {
                                    startPersonalVideoProcessingPulse(
                                        'processing',
                                        48,
                                        88,
                                        'Saving video to cloud storage…'
                                    );
                                }
                            }
                        }, false);
                    }
                    return xhr;
                },
                success: function(ress) {
                    if (ress.queued && ress.upload_token && isVideoUpload) {
                        updatePersonalVideoUploadLoader('queued', 44, 'Upload complete. Starting background processing…');
                        setTimeout(function() {
                            pollPersonalVideoUploadStatus(ress.upload_token, function(success, message) {
                                if (dragZone && dragZone.length) {
                                    dragZone.removeClass('uploading');
                                }
                                hidePersonalVideoUploadLoader(success ? 700 : 900);
                                showPersonalDocVideoToast(success, message);
                                if (success) {
                                    setTimeout(function() {
                                        location.reload();
                                    }, 800);
                                }
                            });
                        }, 300);
                        return;
                    }

                    if (dragZone && dragZone.length) {
                        dragZone.removeClass('uploading');
                    }

                    if (!ress.status) {
                        if (isVideoUpload) {
                            clearPersonalVideoProcessingPulse();
                            updatePersonalVideoUploadLoader('error', _pvuCurrentPercent, ress.message || 'Video upload failed.');
                            hidePersonalVideoUploadLoader(900);
                            showPersonalDocVideoToast(false, ress.message || 'Video upload failed.');
                        } else {
                            $('.custom-error-msg').html('<span class="alert alert-danger">' + ress.message + '</span>');
                        }
                        return;
                    }

                    if (isVideoUpload) {
                        clearPersonalVideoProcessingPulse();
                        updatePersonalVideoUploadLoader('complete', 100, 'Video uploaded successfully!');
                        hidePersonalVideoUploadLoader(700);
                        showPersonalDocVideoToast(true, ress.message || 'Video uploaded successfully.');
                    } else {
                        $('.custom-error-msg').html('<span class="alert alert-success">' + ress.message + '</span>');
                    }

                    if (options.reloadOnSuccess || String(targetCategoryId) !== String(options.originalCategoryId)) {
                        setTimeout(function() {
                            location.reload();
                        }, 800);
                        return;
                    }

                    var row = $('#id_' + targetFileId);
                    var docNameWithoutExt = ress.filename.replace(/\.[^/.]+$/, '').replace(/\s+/g, '_').toLowerCase();
                    var previewUrl = ress.preview_url || (site_url + '/documents/preview/' + (ress.document_id || targetFileId));
                    var documentId = ress.document_id || targetFileId;
                    var uploadTd = row.find('td').eq(1);

                    uploadTd.html(
                        '<div data-id="' + targetFileId + '" data-name="' + docNameWithoutExt + '" class="doc-row" title="Uploaded by: ' + (ress.uploaded_by || 'Staff') + (ress.uploaded_at ? ' on ' + formatClientDocDateTime(ress.uploaded_at) : '') + '" oncontextmenu="showFileContextMenu(event, ' + targetFileId + ', \'' + ress.filetype + '\', \'' + previewUrl + '\', \'' + targetCategoryId + '\', \'' + (ress.status_value || 'draft') + '\'); return false;">' +
                            '<a href="javascript:void(0);" onclick="previewFile(\'' + ress.filetype + '\', \'' + previewUrl + '\', \'preview-container-' + targetCategoryId + '\')">' +
                                '<i class="fa-solid ' + documentFileIconClass(ress.filetype) + '"></i> <span>' + ress.filename + '</span>' +
                            '</a>' +
                        '</div>'
                    );

                    var actionTd = row.find('td').eq(2);
                    actionTd.html(
                        '<a class="renamechecklist" data-id="' + targetFileId + '" href="javascript:;" style="display: none;"></a>' +
                        '<a class="renamedoc" data-id="' + targetFileId + '" href="javascript:;" style="display: none;"></a>' +
                        '<a class="download-file" data-id="' + documentId + '" data-document-id="' + documentId + '" data-filename="' + ress.filekey + '" href="#" style="display: none;"></a>' +
                        '<a class="notuseddoc" data-id="' + targetFileId + '" data-doctype="' + ress.doctype + '" data-href="notuseddoc" href="javascript:;" style="display: none;"></a>'
                    );
                    row.addClass('drow');
                    if (typeof getallactivities === 'function') {
                        getallactivities();
                    }
                },
                error: function(xhr, status, error) {
                    if (dragZone && dragZone.length) {
                        dragZone.removeClass('uploading');
                    }
                    var errorMessage = (xhr.responseJSON && xhr.responseJSON.message)
                        ? xhr.responseJSON.message
                        : 'Upload failed. Please try again.';
                    if (status === 'timeout') {
                        errorMessage = 'Upload timed out. Large videos can take several minutes — please keep this tab open and try again on a stable connection.';
                    }
                    if (isVideoUpload) {
                        clearPersonalVideoProcessingPulse();
                        updatePersonalVideoUploadLoader('error', _pvuCurrentPercent, errorMessage);
                        hidePersonalVideoUploadLoader(900);
                        showPersonalDocVideoToast(false, errorMessage);
                    } else {
                        $('.custom-error-msg').html('<span class="alert alert-danger">' + errorMessage + '</span>');
                    }
                    console.error('Personal doc upload error:', error);
                }
            });
        }

        function uploadPersonalDocFromZone(dragZone, file) {
            var validNameRegex = /^[a-zA-Z0-9_\-\.\s\$\(\),&+]+$/;
            if (!validNameRegex.test(file.name)) {
                alert('File name can only contain letters, numbers, dashes (-), underscores (_), spaces, dots (.), dollar signs ($), parentheses (( )), commas (,), ampersands (&), and plus signs (+). Please rename the file and try again.');
                return false;
            }

            var fileid = dragZone.data('fileid');
            var doccategory = dragZone.data('doccategory');
            var formId = dragZone.data('formid');
            var form = $('#' + formId);
            if (!form.length) {
                alert('Error: Upload form not found. Please refresh the page.');
                return false;
            }

            var context = {
                fileid: fileid,
                doccategory: doccategory,
                clientId: form.find('[name="clientid"]').val()
            };

            var startUpload = function(targetFileId, targetCategoryId) {
                var targetZone = $('#upload_form_' + targetFileId).find('.personal-doc-drag-zone');
                if (!targetZone.length) {
                    targetZone = dragZone;
                }
                performPersonalDocUpload(file, targetFileId, targetCategoryId, targetZone, {
                    originalCategoryId: doccategory,
                    reloadOnSuccess: String(targetCategoryId) !== String(doccategory) || String(targetFileId) !== String(fileid)
                });
            };

            if (isPersonalDocVideoFile(file)) {
                promptPersonalVideoUploadFolder(doccategory, function(selectedCategoryId) {
                    resolvePersonalVideoUploadTarget(selectedCategoryId, file, context, function(err, targetFileId, targetCategoryId) {
                        if (err) {
                            alert(err);
                            return;
                        }
                        startUpload(targetFileId, targetCategoryId);
                    });
                });
                return true;
            }

            startUpload(fileid, doccategory);
            return true;
        }

        window.isPersonalDocVideoFile = isPersonalDocVideoFile;
        window.promptPersonalVideoUploadFolder = promptPersonalVideoUploadFolder;
        window.uploadPersonalDocFromZone = uploadPersonalDocFromZone;
        window.activatePersonalDocumentFolder = activatePersonalDocumentFolder;

        $(document).on('click', '#confirmVideoUploadFolder', function() {
            var selectedCategoryId = $('#videoUploadFolderSelect').val();
            if (!selectedCategoryId) {
                $('#videoUploadFolderError').text('Please select a folder.').show();
                return;
            }
            $('#videoUploadFolderError').hide();
            $('#videoUploadFolderModal').modal('hide');
            if (typeof _videoFolderPromptCallback === 'function') {
                var callback = _videoFolderPromptCallback;
                _videoFolderPromptCallback = null;
                _videoFolderPromptCancel = null;
                callback(selectedCategoryId);
            }
        });

        $('#videoUploadFolderModal').on('hidden.bs.modal', function() {
            if (typeof _videoFolderPromptCancel === 'function') {
                var cancelFn = _videoFolderPromptCancel;
                _videoFolderPromptCallback = null;
                _videoFolderPromptCancel = null;
                cancelFn();
            }
        });

        // Personal Documents - Upload Handler
        
        function handlePersonalDocDragDrop(dragZone, file) {
            uploadPersonalDocFromZone(dragZone, file);
        }
        
        // Matter documents - upload handler
        
        function handleVisaDocDragDrop(dragZone, file) {
            var fileid = dragZone.data('fileid');
            var visa_doc_cat = dragZone.data('doccategory');
            var formId = dragZone.data('formid');
            var form = $('#' + formId);
            var laneDocType = (form.find('input[name="doctype"]').val() || 'matter').toLowerCase();
            if (laneDocType === 'visa') { laneDocType = 'matter'; }
            var uploadUrl = laneDocType === 'nomination'
                ? site_url + '/documents/upload-nomination-document'
                : site_url + '/documents/upload-matter-document';
            var previewPane = laneDocType === 'nomination'
                ? 'preview-container-nomdocumnetlist'
                : 'preview-container-matter-' + visa_doc_cat;
            var contextMenuFn = laneDocType === 'nomination' ? 'showNominationFileContextMenu' : 'showVisaFileContextMenu';
            
            // Validate filename
            var validNameRegex = /^[a-zA-Z0-9_\-\.\s\$\(\),&+]+$/;
            if (!validNameRegex.test(file.name)) {
                alert("File name can only contain letters, numbers, dashes (-), underscores (_), spaces, dots (.), dollar signs ($), parentheses (( )), commas (,), ampersands (&), and plus signs (+). Please rename the file and try again.");
                return false;
            }
            
            // Create FormData with all form fields
            var formData = new FormData(form[0]);
            
            // Override the file input with dragged file
            formData.set('document_upload', file);
            
            // Add extra data
            formData.append('visa_doc_cat', visa_doc_cat);
            
            // Visual feedback
            dragZone.addClass('uploading');
            $('.custom-error-msg').html('<span class="alert alert-info"><i class="fa-solid fa-clock"></i> Uploading document...</span>');
            
            // Upload via AJAX
            $.ajax({
                url: uploadUrl,
                type: 'POST',
                dataType: 'json',
                data: formData,
                contentType: false,
                processData: false,
                success: function(ress) {
                    dragZone.removeClass('uploading');
                    
                    if (ress.status) {
                        $('.custom-error-msg').html('<span class="alert alert-success">' + ress.message + '</span>');
                        
                        var row = $('#id_' + fileid);
                        var docNameWithoutExt = ress.filename.replace(/\.[^/.]+$/, "").replace(/\s+/g, "_").toLowerCase();
                        var previewUrl = ress.preview_url || (site_url + '/documents/preview/' + (ress.document_id || fileid));
                        var documentId = ress.document_id || fileid;
                        
                        // Replace upload TD content (Column 1 = File Name)
                        var uploadTd = row.find('td').eq(1);
                        uploadTd.html(
                            '<div data-id="' + fileid + '" data-name="' + docNameWithoutExt + '" class="doc-row" title="Uploaded by: ' + (ress.uploaded_by || 'Staff') + (ress.uploaded_at ? ' on ' + formatClientDocDateTime(ress.uploaded_at) : '') + '" oncontextmenu="' + contextMenuFn + '(event, ' + fileid + ', \'' + ress.filetype + '\', \'' + previewUrl + '\', \'' + visa_doc_cat + '\', \'' + (ress.status_value || 'draft') + '\'); return false;">' +
                                '<a href="javascript:void(0);" onclick="previewFile(\'' + ress.filetype + '\', \'' + previewUrl + '\', \'' + previewPane + '\')">' +
                                    '<i class="fa-solid ' + documentFileIconClass(ress.filetype) + '"></i> <span>' + ress.filename + '</span>' +
                                '</a>' +
                            '</div>'
                        );
                        
                        // Add hidden elements for context menu actions (Column 2 = Actions)
                        var actionTd = row.find('td').eq(2);
                        actionTd.html(
                            '<a class="renamechecklist" data-id="' + fileid + '" href="javascript:;" style="display: none;"></a>' +
                            '<a class="renamedoc" data-id="' + fileid + '" href="javascript:;" style="display: none;"></a>' +
                            '<a class="download-file" data-id="' + documentId + '" data-document-id="' + documentId + '" data-filename="' + ress.filekey + '" href="#" style="display: none;"></a>' +
                            '<a class="notuseddoc" data-id="' + fileid + '" data-doctype="' + laneDocType + '" data-href="notuseddoc" href="javascript:;" style="display: none;"></a>'
                        );
                        
                        row.addClass('drow');
                    } else {
                        $('.custom-error-msg').html('<span class="alert alert-danger">' + ress.message + '</span>');
                    }
                    
                    getallactivities();
                },
                error: function(xhr, status, error) {
                    dragZone.removeClass('uploading');
                    $('.custom-error-msg').html('<span class="alert alert-danger">Upload failed. Please try again.</span>');
                    console.error('Visa doc upload error:', error);
                }
            });
        }





        $(document).delegate('.add_education_doc', 'click', function (e) {

            e.preventDefault(); // Prevent default button behavior and page refresh

            $("#doccategory").val($(this).attr('data-categoryid'));

            $("#folder_name").val($(this).attr('data-categoryid'));

            var $modal = $('#openeducationdocsmodal');
            $modal.one('shown.bs.modal', function () {
                initTS('#checklist', {
                    plugins: ['remove_button'],
                    allowEmptyOption: true,
                    closeAfterSelect: false,
                    dropdownParent: this,
                    create: false
                });
            });
            $modal.modal('show');

        });



        //Add Personal Document folder

        $(document).delegate('.add_personal_doc_cat', 'click', function (e) {

            e.preventDefault(); // Prevent default button behavior and page refresh

            $('.addpersonaldoccatmodel').modal('show');

        });

        // Add matter document folder (opens addvisadoccatmodel)

        $(document).delegate('.add-visa-doc-category', 'click', function (e) {

            e.preventDefault(); // Prevent default button behavior and page refresh

            let selectedMatterFM;



            if ($('.general_matter_checkbox_client_detail').is(':checked')) {

                // If checkbox is checked, get its value

                selectedMatterFM = $('.general_matter_checkbox_client_detail').val();

            } else {

                // If checkbox is not checked, get the value from the dropdown

                selectedMatterFM = $('#sel_matter_id_client_detail').val();

            }

            $('#visaclientmatterid').val(selectedMatterFM);

            $('.addvisadoccatmodel').modal('show');

        });

        $(document).delegate('.add-nomination-doc-category', 'click', function (e) {

            e.preventDefault();

            let selectedMatterFM;

            if ($('.general_matter_checkbox_client_detail').is(':checked')) {
                selectedMatterFM = $('.general_matter_checkbox_client_detail').val();
            } else {
                selectedMatterFM = $('#sel_matter_id_client_detail').val();
            }

            $('#nominationclientmatterid').val(selectedMatterFM);

            $('.addnominationdoccatmodel').modal('show');

        });





        $(document).delegate('.add_migration_doc', 'click', function (e) {

            e.preventDefault(); // Prevent default button behavior and page refresh

            var hidden_client_matter_id = $('#sel_matter_id_client_detail').val();

            $('#hidden_client_matter_id').val(hidden_client_matter_id);

            $("#visa_folder_name").val($(this).attr('data-categoryid'));

            var $modal = $('#openmigrationdocsmodal');
            $modal.one('shown.bs.modal', function () {
                initTS('#visa_checklist', {
                    plugins: ['remove_button'],
                    allowEmptyOption: true,
                    closeAfterSelect: false,
                    dropdownParent: this,
                    create: false
                });
            });
            $modal.modal('show');

        });

        $(document).delegate('.add_nomination_doc', 'click', function (e) {

            e.preventDefault();

            var hidden_client_matter_id = $('#sel_matter_id_client_detail').val();

            $('#hidden_nomination_client_matter_id').val(hidden_client_matter_id);

            $("#nomination_folder_name").val($(this).attr('data-categoryid'));

            var $modal = $('#opennominationdocsmodal');
            $modal.one('shown.bs.modal', function () {
                initTS('#nomination_checklist', {
                    plugins: ['remove_button'],
                    allowEmptyOption: true,
                    closeAfterSelect: false,
                    dropdownParent: this,
                    create: false
                });
            });
            $modal.modal('show');

        });


        // .openchecklist handler REMOVED - workflow checklist unused

        $(document).delegate('.migdocupload', 'click', function() {

            $(this).attr("value", "");

        });



        





        $(document).delegate('.migdocupload', 'change', function() {

            var fileInput = this.files[0];



            if (!fileInput) return; // Prevent empty uploads



            var fileName = fileInput.name;  //alert(fileName);



            // Allowed: letters, numbers, dash, underscore, space, dot, dollar sign, parentheses, comma, ampersand, plus

            var validNameRegex = /^[a-zA-Z0-9_\-\.\s\$\(\),&+]+$/;



            if (!validNameRegex.test(fileName)) {

                alert("File name can only contain letters, numbers, dashes (-), underscores (_), spaces, dots (.), dollar signs ($), parentheses (( )), commas (,), ampersands (&), and plus signs (+). Please rename the file and try again.");

                $(this).val(''); // Clear the file input

                return false;

            }



            var fileidL1 = $(this).attr("data-fileid");

           



            var visa_doc_cat = $(this).attr("data-doccategory");

            



            // Show immediate feedback that upload is starting

            $('.custom-error-msg').html('<span class="alert alert-info"><i class="fa-solid fa-clock"></i> Uploading document...</span>');

            

            // Create FormData before clearing the input

            var $form = $('#mig_upload_form_'+fileidL1);
            var laneDocType = ($form.find('input[name="doctype"]').val() || 'matter').toLowerCase();
            if (laneDocType === 'visa') { laneDocType = 'matter'; }
            var uploadUrl = laneDocType === 'nomination'
                ? site_url+'/documents/upload-nomination-document'
                : site_url+'/documents/upload-matter-document';
            var previewPane = laneDocType === 'nomination'
                ? 'preview-container-nomdocumnetlist'
                : 'preview-container-matter-' + visa_doc_cat;
            var contextMenuFn = laneDocType === 'nomination' ? 'showNominationFileContextMenu' : 'showVisaFileContextMenu';
            var formData = new FormData($form[0]);

            // Append extra data manually

            formData.append('visa_doc_cat', visa_doc_cat);

            

            // Clear the file input after creating FormData to allow next upload

            $(this).val('');

            

            $.ajax({

                url: uploadUrl,

                type:'POST',

                dataType: 'json',

                data: formData,

                contentType: false,

                processData: false,

                success: function(ress) {

                    if (ress.status) {

                        $('.custom-error-msg').html('<span class="alert alert-success">' + ress.message + '</span>');



                        var row = $('#id_' + fileidL1);

                        var docNameWithoutExt = ress.filename.replace(/\.[^/.]+$/, "").replace(/\s+/g, "_").toLowerCase();
                        var previewUrl = ress.preview_url || (site_url + '/documents/preview/' + (ress.document_id || fileidL1));
                        var documentId = ress.document_id || fileidL1;



                        // Replace upload TD content (Column 1 = File Name)

                        var uploadTd = row.find('td').eq(1);

                        uploadTd.html(

                            '<div data-id="' + fileidL1 + '" data-name="' + docNameWithoutExt + '" class="doc-row" title="Uploaded by: ' + (ress.uploaded_by || 'Staff') + (ress.uploaded_at ? ' on ' + formatClientDocDateTime(ress.uploaded_at) : '') + '" oncontextmenu="' + contextMenuFn + '(event, ' + fileidL1 + ', \'' + ress.filetype + '\', \'' + previewUrl + '\', \'' + visa_doc_cat + '\', \'' + (ress.status_value || 'draft') + '\'); return false;">' +

                                '<a href="javascript:void(0);" onclick="previewFile(\'' + ress.filetype + '\', \'' + previewUrl + '\', \'' + previewPane + '\')">' +

                                    '<i class="fa-solid ' + documentFileIconClass(ress.filetype) + '"></i> <span>' + ress.filename + '</span>' +

                                '</a>' +

                            '</div>'

                        );



                        // Add hidden elements for context menu actions (Column 2 = Actions)

                        var actionTd = row.find('td').eq(2);

                        actionTd.html(

                            '<a class="renamechecklist" data-id="' + fileidL1 + '" href="javascript:;" style="display: none;"></a>' +

                            '<a class="renamedoc" data-id="' + fileidL1 + '" href="javascript:;" style="display: none;"></a>' +

                            '<a class="download-file" data-id="' + documentId + '" data-document-id="' + documentId + '" data-filename="' + ress.filekey + '" href="#" style="display: none;"></a>' +

                            '<a class="notuseddoc" data-id="' + fileidL1 + '" data-doctype="' + laneDocType + '" data-href="notuseddoc" href="javascript:;" style="display: none;"></a>'

                        );

                        

                        // Ensure the row has the proper class for event delegation

                        row.addClass('drow');

                    } else {

                        $('.custom-error-msg').html('<span class="alert alert-danger">' + ress.message + '</span>');

                    }

                    getallactivities();

                },

                error: function(xhr, status, error) {

                    $('.custom-error-msg').html('<span class="alert alert-danger">Upload failed. Please try again.</span>');

                    console.error('Upload error:', error);

                    getallactivities();

                }

            });

        });

    }); // End jQuery(document).ready from line ~2366
