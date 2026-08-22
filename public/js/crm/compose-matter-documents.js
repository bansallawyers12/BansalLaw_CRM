(function (window, $) {
    'use strict';

    function escapeComposeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function renderComposeMatterDocuments(docs) {
        var $section = $('#compose-matter-documents-section');
        var $tbody = $('#compose-matter-documents-tbody');
        if (!$section.length || !$tbody.length) {
            return;
        }
        $tbody.empty();
        if (!docs || !docs.length) {
            $section.hide();
            return;
        }
        docs.forEach(function (doc) {
            var checklist = escapeComposeHtml(doc.checklist || doc.file_name || 'Document');
            var fileName = escapeComposeHtml(doc.file_name || 'View');
            var previewUrl = doc.preview_url ? escapeComposeHtml(doc.preview_url) : '';
            var fileCell = previewUrl
                ? '<a target="_blank" rel="noopener" href="' + previewUrl + '">' + fileName + '</a>'
                : fileName;
            $tbody.append(
                '<tr data-document-id="' + escapeComposeHtml(doc.id) + '">' +
                '<td><input type="checkbox" name="checklistfile_document[]" value="' + escapeComposeHtml(doc.id) + '" class="checklistfile-document-cb"></td>' +
                '<td>' + checklist + '</td>' +
                '<td>' + fileCell + '</td>' +
                '</tr>'
            );
        });
        $section.show();
    }

    function loadComposeMatterDocuments(params) {
        var url = (window.ClientDetailConfig && window.ClientDetailConfig.urls && window.ClientDetailConfig.urls.getComposeDefaults)
            || (window.ClientsListingSpaConfig && window.ClientsListingSpaConfig.routes && window.ClientsListingSpaConfig.routes.getComposeDefaults)
            || '/get-compose-defaults';
        return $.get(url, params || {}).done(function (res) {
            renderComposeMatterDocuments((res && res.matter_documents) || []);
            $('#emailmodal .checklistfile-document-cb').prop('checked', false);
            return res;
        }).fail(function () {
            renderComposeMatterDocuments([]);
        });
    }

    window.escapeComposeHtml = window.escapeComposeHtml || escapeComposeHtml;
    window.renderComposeMatterDocuments = renderComposeMatterDocuments;
    window.loadComposeMatterDocuments = loadComposeMatterDocuments;
})(window, jQuery);
