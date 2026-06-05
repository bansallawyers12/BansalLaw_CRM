/**
 * Shared client-detail utilities (matter URL parsing, notes filtering).
 */
(function (global) {
    'use strict';

    /** Keep in sync with ClientsController::detail() / detail.blade.php $validTabNames */
    var CLIENT_DETAIL_TAB_SLUGS = [
        'personaldetails', 'overview', 'companydetails', 'activityfeed', 'clientaction', 'noteterm',
        'personaldocuments', 'matterdocuments', 'documents', 'nominationdocuments',
        'emails', 'client_portal', 'legalforms', 'formgenerations', 'formgenerationsl',
        'application', 'workflow', 'checklists', 'account', 'notuseddocuments', 'visadocuments',
        'emailhandling'
    ];

    function isClientDetailTabSlug(segment) {
        if (segment == null || segment === '') {
            return false;
        }
        return CLIENT_DETAIL_TAB_SLUGS.indexOf(String(segment).toLowerCase()) !== -1;
    }

    /**
     * Matter reference from URL or ClientDetailConfig (e.g. FAM_1), not a tab slug.
     * Prefers the live URL path so tab navigation (pushState) stays in sync with filters.
     */
    function parseClientDetailMatterRefFromUrl(href) {
        var path = String(href || global.location.href).split('?')[0].replace(/\/$/, '');
        var segments = path.split('/').filter(Boolean);
        var detailIdx = segments.indexOf('detail');
        if (detailIdx !== -1) {
            var segAfterClient = segments[detailIdx + 2] || '';
            var segAfterThat = segments[detailIdx + 3] || '';

            if (segAfterClient) {
                if (segAfterThat && isClientDetailTabSlug(segAfterThat)) {
                    if (!isClientDetailTabSlug(segAfterClient)) {
                        return segAfterClient;
                    }
                } else if (!isClientDetailTabSlug(segAfterClient)) {
                    return segAfterClient;
                }
            }
        }

        var config = global.ClientDetailConfig;
        if (config && config.matterRefNo != null && config.matterRefNo !== '') {
            var fromConfig = String(config.matterRefNo).trim();
            if (fromConfig && !isClientDetailTabSlug(fromConfig)) {
                return fromConfig;
            }
        }
        if (config && config.matterId != null && config.matterId !== '') {
            var fromMatterId = String(config.matterId).trim();
            if (fromMatterId && !isClientDetailTabSlug(fromMatterId)) {
                return fromMatterId;
            }
        }

        return '';
    }

    /**
     * Whether a note card's matter_id matches the selected matter dropdown value.
     * Notes without a matter_id are shown for any selected matter (legacy / general notes).
     */
    function noteMatchesSelectedMatter(cardMatterId, selectedMatter) {
        if (selectedMatter == null || selectedMatter === '') {
            return true;
        }
        var card = cardMatterId == null ? '' : String(cardMatterId).trim();
        if (card === '' || card === 'null' || card === '0') {
            return true;
        }
        return card === String(selectedMatter);
    }

    function getSelectedClientDetailMatterId() {
        if (typeof global.jQuery !== 'undefined') {
            var $ = global.jQuery;
            if ($('.general_matter_checkbox_client_detail').is(':checked')) {
                return $('.general_matter_checkbox_client_detail').val() || '';
            }
            return $('#sel_matter_id_client_detail').val() || '';
        }
        return '';
    }

    function selectClientDetailMatterByRef(matterRef, options) {
        options = options || {};
        if (!matterRef || typeof global.jQuery === 'undefined') {
            return '';
        }
        var $ = global.jQuery;
        var $select = $('#sel_matter_id_client_detail');
        var selected = '';

        $select.find('option').each(function () {
            if ($(this).data('clientuniquematterno') === matterRef) {
                $select.val($(this).val());
                selected = $(this).val();
                return false;
            }
        });

        if (selected) {
            if (!options.silent) {
                $select.trigger('change');
            }
            return selected;
        }

        var checkboxMatch = false;
        $('.general_matter_checkbox_client_detail').each(function () {
            if ($(this).data('clientuniquematterno') === matterRef) {
                $(this).prop('checked', true);
                if (!options.silent) {
                    $(this).trigger('change');
                }
                selected = $(this).val();
                checkboxMatch = true;
                return false;
            }
        });

        if (checkboxMatch) {
            return selected;
        }

        return $select.val() || '';
    }

    function ensureNotesAllTypeTabActive() {
        if (typeof global.jQuery === 'undefined') {
            return;
        }
        var $ = global.jQuery;
        var $activeTab = $('.subtab8-button.active');
        if (!$activeTab.length || $activeTab.data('subtab8') !== 'All') {
            $('.subtab8-button.pill-tab').removeClass('active');
            $('.subtab8-button.pill-tab[data-subtab8="All"]').addClass('active');
        }
    }

    /**
     * Re-apply Notes tab filters the same way as a full page load on .../noteterm.
     * @param {{ syncMatterFromUrl?: boolean, deferredPass?: boolean }} options
     */
    function refreshNotesTabDisplay(options) {
        options = options || {};
        if (options.syncMatterFromUrl) {
            var matterRef = parseClientDetailMatterRefFromUrl(global.location.href);
            if (matterRef) {
                selectClientDetailMatterByRef(matterRef, { silent: true });
            }
        }

        ensureNotesAllTypeTabActive();

        if (typeof global.filterNotes !== 'function') {
            return;
        }

        global.filterNotes();

        if (options.deferredPass !== false) {
            global.setTimeout(function () {
                ensureNotesAllTypeTabActive();
                if (typeof global.filterNotes === 'function') {
                    global.filterNotes();
                }
            }, 200);
        }
    }

    global.ClientDetailShared = {
        CLIENT_DETAIL_TAB_SLUGS: CLIENT_DETAIL_TAB_SLUGS,
        isClientDetailTabSlug: isClientDetailTabSlug,
        parseClientDetailMatterRefFromUrl: parseClientDetailMatterRefFromUrl,
        noteMatchesSelectedMatter: noteMatchesSelectedMatter,
        getSelectedClientDetailMatterId: getSelectedClientDetailMatterId,
        selectClientDetailMatterByRef: selectClientDetailMatterByRef,
        ensureNotesAllTypeTabActive: ensureNotesAllTypeTabActive,
        refreshNotesTabDisplay: refreshNotesTabDisplay
    };
})(typeof window !== 'undefined' ? window : this);
