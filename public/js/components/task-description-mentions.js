/**
 * Task Description @mentions (Add My Task)
 *
 * Type @ in the description field to pick staff from the Assignees list.
 * Selecting a person inserts @Name and checks them as an assignee.
 * Assignees remains the source of truth for ownership.
 *
 * The picker is attached to document.body (so it is not clipped by the popover).
 * Mouse events are bound on the menu node itself so they do not bubble to
 * document — Bootstrap click-triggered popovers would otherwise close.
 */
(function ($) {
    'use strict';

    if (typeof $ === 'undefined') {
        return;
    }

    var MENU_ID = 'task-desc-mention-menu';
    var TEXTAREA_SEL = '.add-task-layout #add_task_assignnote, .add-task-layout #assignnote';
    var MAX_RESULTS = 80;
    var activeState = null;
    var listenersBound = false;
    var $menuCached = null;
    var applyingMention = false;

    $(function () {
        initTaskDescriptionMentions();
    });

    function initTaskDescriptionMentions() {
        if (listenersBound) {
            return;
        }
        listenersBound = true;

        $(document).on('input', TEXTAREA_SEL, function () {
            if (applyingMention) {
                return;
            }
            handleInput(this);
        });

        $(document).on('keydown', TEXTAREA_SEL, function (e) {
            handleKeydown(e, this);
        });

        $(document).on('blur', TEXTAREA_SEL, function () {
            setTimeout(function () {
                var active = document.activeElement;
                if (active && $menuCached && $menuCached[0] && $.contains($menuCached[0], active)) {
                    return;
                }
                hideMenu();
            }, 150);
        });

        $(document).on('hide.bs.popover', '.add_my_task', function () {
            hideMenu();
        });

        $(document).on('click', function (e) {
            if (isMenuEvent(e) || isTaskDescriptionTextarea(e.target)) {
                return;
            }
            hideMenu();
        });
    }

    function ensureMenu() {
        if ($menuCached && $menuCached.length && document.body.contains($menuCached[0])) {
            return $menuCached;
        }

        var $existing = $('#' + MENU_ID);
        if ($existing.length) {
            $menuCached = $existing;
        } else {
            $menuCached = $('<div id="' + MENU_ID + '" class="task-desc-mention-menu" role="listbox" aria-label="Tag staff"></div>');
            $('body').append($menuCached);
        }

        $menuCached.off('.taskMentions');
        $menuCached.on('mousedown.taskMentions click.taskMentions touchstart.taskMentions', function (e) {
            e.stopPropagation();
        });
        $menuCached.on('mousedown.taskMentions', '.task-desc-mention-item', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var staffId = $(this).attr('data-staff-id');
            var staffName = $(this).attr('data-staff-name') || '';
            if (activeState && staffId) {
                applyMention(activeState.textarea, activeState.mention, staffId, staffName);
            }
            hideMenu();
        });

        return $menuCached;
    }

    function isMenuEvent(e) {
        var $menu = $menuCached && $menuCached.length ? $menuCached : $('#' + MENU_ID);
        if (!$menu.length || !e || !e.target) {
            return false;
        }
        return e.target === $menu[0] || $.contains($menu[0], e.target);
    }

    function isTaskDescriptionTextarea(el) {
        if (!el) {
            return false;
        }
        return $(el).is(TEXTAREA_SEL);
    }

    /**
     * @param {HTMLTextAreaElement} textarea
     */
    function handleInput(textarea) {
        var mention = getMentionAtCaret(textarea);
        if (!mention) {
            hideMenu();
            return;
        }

        var $root = $(textarea).closest('.add-task-layout');
        if (!$root.length) {
            hideMenu();
            return;
        }

        var staff = collectStaff($root);
        var filtered = filterStaff(staff, mention.query);
        if (!filtered.length) {
            hideMenu();
            return;
        }

        activeState = { textarea: textarea, mention: mention, items: filtered, index: 0 };
        renderMenu(textarea, filtered, 0);
    }

    /**
     * @param {JQuery.Event} e
     * @param {HTMLTextAreaElement} textarea
     */
    function handleKeydown(e, textarea) {
        var $menu = $menuCached;
        if (!$menu || !$menu.length || !$menu.is(':visible')) {
            return;
        }
        if (!activeState || activeState.textarea !== textarea) {
            return;
        }

        var key = e.key;
        if (key === 'ArrowDown') {
            e.preventDefault();
            activeState.index = (activeState.index + 1) % activeState.items.length;
            highlightMenu(activeState.index);
            return;
        }
        if (key === 'ArrowUp') {
            e.preventDefault();
            activeState.index = (activeState.index - 1 + activeState.items.length) % activeState.items.length;
            highlightMenu(activeState.index);
            return;
        }
        if (key === 'Enter' || key === 'Tab') {
            e.preventDefault();
            var item = activeState.items[activeState.index];
            if (item) {
                applyMention(textarea, activeState.mention, item.id, item.name);
            }
            hideMenu();
            return;
        }
        if (key === 'Escape') {
            e.preventDefault();
            hideMenu();
        }
    }

    /**
     * @param {HTMLTextAreaElement} textarea
     * @returns {{ start: number, end: number, query: string }|null}
     */
    function getMentionAtCaret(textarea) {
        var value = textarea.value || '';
        var pos = typeof textarea.selectionStart === 'number' ? textarea.selectionStart : value.length;
        var before = value.slice(0, pos);
        var match = before.match(/(^|[\s(\[{])@([^\s@]*)$/);
        if (!match) {
            return null;
        }
        var atIndex = before.lastIndexOf('@');
        return {
            start: atIndex,
            end: pos,
            query: match[2] || ''
        };
    }

    /**
     * @param {JQuery} $root
     * @returns {Array<{id: string, name: string, search: string}>}
     */
    function collectStaff($root) {
        var list = [];
        var seen = {};
        $root.find('.assignee-item').each(function () {
            var $item = $(this);
            var $cb = $item.find('.checkbox-item').first();
            if (!$cb.length) {
                return;
            }
            var id = String($cb.val() || '');
            if (!id || seen[id]) {
                return;
            }
            var name = ($item.attr('data-staff-name') || '').trim();
            if (!name) {
                var raw = ($item.text() || '').replace(/\s+/g, ' ').trim();
                name = raw.replace(/\s*\([^)]*\)\s*$/, '').trim();
            }
            if (!name) {
                return;
            }
            seen[id] = true;
            var search = ($item.attr('data-searchtext') || (name + id)).toLowerCase().replace(/\s+/g, '');
            list.push({ id: id, name: name, search: search });
        });
        return list;
    }

    /**
     * @param {Array<{id: string, name: string, search: string}>} staff
     * @param {string} query
     */
    function filterStaff(staff, query) {
        var q = String(query || '').toLowerCase().replace(/\s+/g, '');
        var matched = !q ? staff.slice() : staff.filter(function (s) {
            return s.search.indexOf(q) !== -1 || s.name.toLowerCase().replace(/\s+/g, '').indexOf(q) !== -1;
        });
        return matched.slice(0, MAX_RESULTS);
    }

    /**
     * @param {HTMLTextAreaElement} textarea
     * @param {Array} items
     * @param {number} activeIndex
     */
    function renderMenu(textarea, items, activeIndex) {
        var $menu = ensureMenu();
        var html = items.map(function (item, i) {
            var activeClass = i === activeIndex ? ' is-active' : '';
            return '<button type="button" class="task-desc-mention-item' + activeClass + '" role="option"' +
                ' data-staff-id="' + escapeAttr(item.id) + '"' +
                ' data-staff-name="' + escapeAttr(item.name) + '">' +
                '<i class="fa-solid fa-at" aria-hidden="true"></i>' +
                '<span>' + escapeHtml(item.name) + '</span>' +
                '</button>';
        }).join('');

        $menu.html(html).show();
        positionMenu(textarea, $menu);
    }

    /**
     * @param {number} index
     */
    function highlightMenu(index) {
        var $menu = ensureMenu();
        $menu.find('.task-desc-mention-item').removeClass('is-active')
            .eq(index).addClass('is-active');
        var el = $menu.find('.task-desc-mention-item').get(index);
        if (el && typeof el.scrollIntoView === 'function') {
            el.scrollIntoView({ block: 'nearest' });
        }
    }

    /**
     * @param {HTMLTextAreaElement} textarea
     * @param {JQuery} $menu
     */
    function positionMenu(textarea, $menu) {
        var rect = textarea.getBoundingClientRect();
        var menuHeight = $menu.outerHeight() || 200;
        var spaceBelow = window.innerHeight - rect.bottom;
        var top = spaceBelow < menuHeight && rect.top > menuHeight
            ? (rect.top - menuHeight - 4)
            : (rect.bottom + 4);
        var width = Math.min(Math.max(rect.width, 220), 320);
        var left = Math.min(rect.left, window.innerWidth - width - 8);
        $menu.css({
            position: 'fixed',
            top: Math.max(8, top) + 'px',
            left: Math.max(8, left) + 'px',
            width: width + 'px',
            zIndex: 10060
        });
    }

    /**
     * @param {HTMLTextAreaElement} textarea
     * @param {{ start: number, end: number, query: string }} mention
     * @param {string} staffId
     * @param {string} staffName
     */
    function applyMention(textarea, mention, staffId, staffName) {
        applyingMention = true;
        try {
            var value = textarea.value || '';
            var insert = '@' + staffName + ' ';
            textarea.value = value.slice(0, mention.start) + insert + value.slice(mention.end);
            var caret = mention.start + insert.length;
            textarea.focus();
            if (typeof textarea.setSelectionRange === 'function') {
                textarea.setSelectionRange(caret, caret);
            }

            var $root = $(textarea).closest('.add-task-layout');
            if (!$root.length) {
                return;
            }
            var $cb = $root.find('.checkbox-item').filter(function () {
                return String($(this).val()) === String(staffId);
            }).first();
            if ($cb.length && !$cb.prop('checked')) {
                $cb.prop('checked', true).trigger('change');
            }
        } finally {
            applyingMention = false;
        }
    }

    function hideMenu() {
        if ($menuCached && $menuCached.length) {
            $menuCached.hide().empty();
        } else {
            $('#' + MENU_ID).hide().empty();
        }
        activeState = null;
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function escapeAttr(str) {
        return escapeHtml(str).replace(/'/g, '&#39;');
    }

    window.TaskDescriptionMentions = {
        init: initTaskDescriptionMentions,
        hide: hideMenu
    };
})(window.jQuery);
