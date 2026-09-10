/**
 * Client detail tags modal + hero red-tags toggle.
 * Extracted from detail-main.js (CLI-4 progressive extraction).
 * Requires: jQuery.
 */
(function ($) {
    'use strict';
    if (!$) {
        return;
    }

    // Tags modal: open for normal tags
    $(document).delegate('.opentagspopup', 'click', function (e) {
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
    $(document).delegate('.openredtagspopup', 'click', function (e) {
        e.preventDefault();
        var entityId = $(this).attr('data-id');
        if (entityId) {
            $('#tags_clients #client_id').val(entityId);
            $('#tags_clients #create_new_as_red').val('1');
            $('#tags_clients #tags_red_mode_hint').show();
            $('#tags_clients').modal('show');
        }
    });

    // Hero: toggle visibility of red tags (hidden by default)
    (function initHeroRedTagsToggle() {
        var toggleBtn = document.getElementById('cdnToggleHeroRedTags');
        var redTagsSection = document.getElementById('cdn-hero-red-tags');
        if (!toggleBtn || !redTagsSection) {
            return;
        }

        var clientId = toggleBtn.getAttribute('data-client-id') || '';
        var storageKey = 'redTagsVisible_' + clientId;
        var redCount = toggleBtn.getAttribute('data-red-count') || '0';
        var isVisible = false;

        function setVisible(visible) {
            isVisible = !!visible;
            redTagsSection.classList.toggle('is-visible', isVisible);
            redTagsSection.setAttribute('aria-hidden', isVisible ? 'false' : 'true');
            toggleBtn.classList.toggle('is-visible', isVisible);
            toggleBtn.setAttribute('aria-expanded', isVisible ? 'true' : 'false');
            toggleBtn.title = isVisible
                ? 'Hide ' + redCount + ' red tag' + (redCount === '1' ? '' : 's')
                : 'Show ' + redCount + ' red tag' + (redCount === '1' ? '' : 's');
            toggleBtn.setAttribute('aria-label', isVisible ? 'Hide red tags' : 'Show red tags');
            var icon = toggleBtn.querySelector('i');
            if (icon) {
                icon.className = isVisible ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
            }
            try {
                sessionStorage.setItem(storageKey, isVisible ? 'true' : 'false');
            } catch (e) { /* private mode / quota */ }
        }

        var storedVisible = false;
        try {
            storedVisible = sessionStorage.getItem(storageKey) === 'true';
        } catch (e) { /* private mode */ }
        setVisible(storedVisible);

        toggleBtn.addEventListener('click', function () {
            setVisible(!isVisible);
        });
    })();

    // Tags modal: add tag pill(s) from input on comma or Enter
    $(document).on('keydown', '#tags_modal_container #tag_input', function (e) {
        var $input = $(this);
        var val = ($input.val() || '').trim();
        if (e.which === 188 || e.which === 13) {
            e.preventDefault();
            if (val) {
                var parts = val.split(',').map(function (t) { return t.trim(); }).filter(function (t) { return t.length > 0; });
                var $container = $('#tags_modal_container .tags-pills-inner');
                var existing = [];
                $container.find('.tag-pill').each(function () { existing.push($(this).attr('data-tag-name')); });
                parts.forEach(function (tagName) {
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
    $(document).on('blur', '#tags_modal_container #tag_input', function () {
        var $input = $(this);
        var val = ($input.val() || '').trim();
        if (!val) return;
        var parts = val.split(',').map(function (t) { return t.trim(); }).filter(function (t) { return t.length > 0; });
        if (parts.length === 0) return;
        var $container = $('#tags_modal_container .tags-pills-inner');
        var existing = [];
        $container.find('.tag-pill').each(function () { existing.push($(this).attr('data-tag-name')); });
        parts.forEach(function (tagName) {
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
    $(document).delegate('#tags_modal_container .tag-pill-remove', 'click', function (e) {
        e.preventDefault();
        $(this).closest('.tag-pill').remove();
        var count = $('#tags_modal_container .tag-pill').length;
        $('#tags_validation').val(count > 0 ? '1' : '');
    });

    // Tags form: collect tags from pills and submit
    $(document).on('submit', '#stags_matter', function (e) {
        var $form = $(this);
        $form.find('#tag_input').trigger('blur');
        var $container = $form.find('#tags_modal_container');
        if ($container.length) {
            e.preventDefault();
            $form.find('input[name="tag_normal[]"]').remove();
            $form.find('input[name="tag_red[]"]').remove();
            $container.find('.tag-pill').each(function () {
                var n = $(this).attr('data-tag-name');
                if (!n) return;
                var isRed = $(this).attr('data-tag-red') === '1';
                var nm = isRed ? 'tag_red[]' : 'tag_normal[]';
                $('<input type="hidden">').attr('name', nm).val(n).appendTo($form);
            });
            $form[0].submit();
        }
    });
})(jQuery);
