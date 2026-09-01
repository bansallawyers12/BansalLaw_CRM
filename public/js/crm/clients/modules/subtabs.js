/**
 * Subtabs module - Document/Notes/Form subtab switching (inbox, sent, migrationdocuments, notes, personal, visa, form generation)
 * Extracted from detail-main.js - Phase 3i refactoring.
 * Requires: jQuery, ClientDetailConfig
 *
 * Uses delegated clicks so folder tabs still work after lazy-tab HTML insert
 * and for folders created after the initial page load.
 */
(function($) {
    'use strict';
    if (!$) return;

    function activatePaneById(paneId) {
        if (!paneId) {
            return $();
        }
        // Numeric IDs are invalid as bare CSS #id selectors in querySelector;
        // getElementById is reliable for "123-subtab6" style ids.
        var el = document.getElementById(String(paneId));
        if (el) {
            return $(el).addClass('active');
        }
        return $('#' + String(paneId).replace(/(:|\.|\[|\]|,|=|@)/g, '\\$1')).addClass('active');
    }

    function selectedMatterId() {
        if ($('.general_matter_checkbox_client_detail').is(':checked')) {
            return $('.general_matter_checkbox_client_detail').val();
        }
        return $('#sel_matter_id_client_detail').val();
    }

    function adjustDocPanels() {
        if (typeof adjustPreviewContainers === 'function') {
            adjustPreviewContainers();
        }
        if (typeof scheduleClientDocumentsPanelHeightAdjust === 'function') {
            scheduleClientDocumentsPanelHeightAdjust();
        } else if (typeof adjustClientDocumentsPanelHeight === 'function') {
            adjustClientDocumentsPanelHeight();
        }
    }

    // Delegated: works for SSR, lazy-loaded panes, and dynamically added folders
    $(document).on('click', '.subtab-button', function() {
        $('.subtab-button').removeClass('active');
        $('.subtab-pane').removeClass('active');
        $(this).addClass('active');

        var subtabId = $(this).data('subtab');
        activatePaneById(subtabId + '-subtab');

        var selectedMatter = selectedMatterId();

        if (subtabId == 'inbox') {
            if (selectedMatter != "") {
                $('#inbox-subtab #email-list').find('.email-card').each(function() {
                    if ($(this).data('matterid') == selectedMatter) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            } else {
                $(this).hide();
            }
        }

        if (subtabId == 'sent') {
            if (selectedMatter != "") {
                $('#sent-subtab #email-list1').find('.email-card').each(function() {
                    if ($(this).data('matterid') == selectedMatter) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            } else {
                $(this).hide();
            }
        }

        if (subtabId == 'migrationdocuments') {
            if (selectedMatter != "") {
                $('#migrationdocuments-subtab .migdocumnetlist1').find('.drow').each(function() {
                    if ($(this).data('matterid') == selectedMatter) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            } else {
                $('#migrationdocuments-subtab .migdocumnetlist1').find('.drow').hide();
            }
        }

        localStorage.setItem('subactiveTab', subtabId);
    });

    // Restore last email/doc subtab once (legacy attribute was data-tab; correct is data-subtab)
    $(function() {
        var subactiveTab = localStorage.getItem('subactiveTab');
        if (!subactiveTab) {
            return;
        }
        var $subtargetButton = $('.subtab-button[data-subtab="' + subactiveTab + '"]');
        if ($subtargetButton.length) {
            $subtargetButton.trigger('click');
        }
        localStorage.removeItem('subactiveTab');
    });

    $(document).on('click', '.subtab3-button', function(e) {
        e.preventDefault();
        $('.subtab3-button').removeClass('active');
        $('.subtab3-pane').removeClass('active');
        $(this).addClass('active');
        var subtabId3 = $(this).data('subtab3');
        activatePaneById(subtabId3 + '-subtab3');
    });

    $(document).on('click', '.subtab8-button', function(e) {
        e.preventDefault();
        $('.subtab8-button').removeClass('active');
        $('.subtab8-pane').removeClass('active');
        $(this).addClass('active');
        var subtabId8 = $(this).data('subtab8');
        activatePaneById(subtabId8 + '-subtab8');
    });

    // Personal document folders
    $(document).on('click', '.subtab2-button', function(e) {
        e.preventDefault();
        $('.subtab2-button').removeClass('active');
        $('.subtab2-pane').removeClass('active');
        $(this).addClass('active');
        var subtabId2 = $(this).data('subtab2');
        activatePaneById(subtabId2 + '-subtab2');
        adjustDocPanels();
    });

    // Matter document folders
    $(document).on('click', '.subtab6-button', function(e) {
        e.preventDefault();
        $('.subtab6-button').removeClass('active');
        $('.subtab6-pane').removeClass('active');
        $(this).addClass('active');
        var subtabId6 = $(this).data('subtab6');
        activatePaneById(subtabId6 + '-subtab6');
        adjustDocPanels();
    });

})(typeof jQuery !== 'undefined' ? jQuery : null);
