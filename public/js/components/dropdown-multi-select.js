/**
 * Dropdown Multi-Select Component
 *
 * Custom multi-select with checkboxes. Syncs to a hidden <select multiple>.
 * Add-task modal uses collapsible assignee picker with chip tags.
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        initDropdownMultiSelect();
    });

    function initDropdownMultiSelect() {
        $(document).on('click', '.dropdown-menu', function(e) {
            e.stopPropagation();
        });

        $(document).on('click', '.assignee-picker-trigger', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $panel = $(this).closest('.assignee-picker-panel');
            var willOpen = !$panel.hasClass('is-open');
            closeAllAssigneePickers();
            if (willOpen) {
                openAssigneePicker($panel);
            }
        });

        $(document).on('click', '.assignee-picker-dropdown', function(e) {
            e.stopPropagation();
        });

        $(document).on('click', function() {
            closeAllAssigneePickers();
        });

        $(document).on('click', '.assignee-chip-remove', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var value = $(this).closest('.assignee-chip').data('value');
            var $panel = $(this).closest('.assignee-picker-panel');
            $panel.find('.checkbox-item[value="' + value + '"]').prop('checked', false).trigger('change');
        });

        $(document).on('change', '.checkbox-item', function() {
            syncFromCheckbox($(this));
        });

        $(document).on('change', '#select-all, #add_task_select_all, .assignee-select-all-input', function() {
            var $root = $(this).closest('.add-task-layout');
            var isChecked = $(this).is(':checked');
            if ($root.length) {
                $root.find('.assignee-item:visible .checkbox-item').prop('checked', isChecked).trigger('change');
                return;
            }
            $('.assignee-item:visible .checkbox-item').prop('checked', isChecked).trigger('change');
        });

        $(document).on('input', '.assignee-search-input', function(e) {
            e.stopPropagation();

            var searchTerm = $(this).val().toLowerCase();
            var $picker = $(this).closest('.assignee-picker-panel, .dropdown-multi-select');
            var $items = $picker.length ? $picker.find('.assignee-item') : $('.assignee-item');

            if ($items.length === 0) {
                return;
            }

            $items.each(function() {
                var $item = $(this);
                var itemText = $item.text().toLowerCase();

                if (searchTerm === '' || itemText.indexOf(searchTerm) > -1) {
                    $item.show().removeClass('hidden');
                } else {
                    $item.hide().addClass('hidden');
                }
            });
        });

        $(document).on('click', '.add-task-modal-close', function(e) {
            e.preventDefault();
            e.stopPropagation();
            closeAllAssigneePickers();
            $('.add_my_task').each(function() {
                var inst = typeof bootstrap !== 'undefined' && bootstrap.Popover
                    ? bootstrap.Popover.getInstance(this)
                    : null;
                if (inst) {
                    inst.hide();
                } else {
                    $(this).popover('hide');
                }
            });
        });

        $(document).on('hidden.bs.popover', '.add_my_task', function() {
            closeAllAssigneePickers();
        });

        $(document).on('show.bs.dropdown', function(e) {
            var $dm = $(e.target).closest('.dropdown-multi-select');
            if (!$dm.length || $dm.hasClass('assignee-picker-panel')) {
                return;
            }
            $dm.find('.assignee-search-input').val('');
            $dm.find('.assignee-item').show().removeClass('hidden');
        });

        $(document).on('shown.bs.dropdown', function(e) {
            var $dm = $(e.target).closest('.dropdown-multi-select');
            if (!$dm.length || $dm.hasClass('assignee-picker-panel')) {
                return;
            }
            var $input = $dm.find('.assignee-search-input').first();
            setTimeout(function() {
                $input.trigger('focus');
            }, 100);
        });
    }

    function closeAllAssigneePickers() {
        $('.assignee-picker-panel.is-open').each(function() {
            closeAssigneePicker($(this));
        });
    }

    function openAssigneePicker($panel) {
        if (!$panel || !$panel.length) {
            return;
        }
        $panel.addClass('is-open');
        $panel.find('.assignee-picker-trigger').attr('aria-expanded', 'true');
        $panel.find('.assignee-search-input').val('');
        $panel.find('.assignee-item').show().removeClass('hidden');
        setTimeout(function() {
            $panel.find('.assignee-search-input').first().trigger('focus');
        }, 50);
    }

    function closeAssigneePicker($panel) {
        if (!$panel || !$panel.length) {
            return;
        }
        $panel.removeClass('is-open');
        $panel.find('.assignee-picker-trigger').attr('aria-expanded', 'false');
    }

    function renderAssigneeChips($root) {
        var $panel = $root.find('.assignee-picker-panel').first();
        if (!$panel.length) {
            return;
        }

        var $chipsHost = $panel.find('.assignee-picker-chips');
        var chipsHtml = '';
        var count = 0;

        $root.find('.checkbox-item:checked').each(function() {
            var $item = $(this).closest('.assignee-item');
            var name = $.trim($item.data('staff-name') || $item.find('.assignee-picker-row__text').text());
            var value = $(this).val();
            if (!value) {
                return;
            }
            count += 1;
            chipsHtml += '<span class="assignee-chip" data-value="' + value + '">' +
                '<span class="assignee-chip__label">' + $('<div/>').text(name).html() + '</span>' +
                '<button type="button" class="assignee-chip-remove" aria-label="Remove ' + $('<div/>').text(name).html() + '">&times;</button>' +
                '</span>';
        });

        $chipsHost.html(chipsHtml);
        $panel.toggleClass('has-selection', count > 0);
        setSelectedCountText($root.find('.selected-count').first(), count);
        syncSelectAllState($root);
    }

    function syncSelectAllState($root) {
        var $visible = $root.find('.assignee-item:visible .checkbox-item');
        var $checked = $visible.filter(':checked');
        var $selectAll = $root.find('.assignee-select-all-input, #select-all, #add_task_select_all').first();
        if (!$selectAll.length || !$visible.length) {
            return;
        }
        $selectAll.prop('checked', $visible.length === $checked.length);
        $selectAll.prop('indeterminate', $checked.length > 0 && $checked.length < $visible.length);
    }

    function syncFromCheckbox($checkbox) {
        var $root = $checkbox.closest('.add-task-layout');
        if ($root.length) {
            var selectedValues = [];
            $root.find('.checkbox-item:checked').each(function() {
                selectedValues.push($(this).val());
            });
            var $hidden = $root.find('#add_task_rem_cat, #rem_cat').first();
            if ($hidden.length) {
                $hidden.val(selectedValues).trigger('change');
            }
            renderAssigneeChips($root);
            return;
        }

        var $container = $checkbox.closest('#create_action_popup, .modal, .popover-body');
        if ($container.length) {
            var $hiddenModal = $container.find('select[name="rem_cat[]"]').first();
            if ($hiddenModal.length) {
                var vals = [];
                $container.find('.checkbox-item:checked').each(function() {
                    vals.push($(this).val());
                });
                $hiddenModal.val(vals).trigger('change');
                setSelectedCountText($container.find('.selected-count').first(), vals.length);
                return;
            }
        }

        var selectedValues = [];
        $('.checkbox-item:checked').each(function() {
            selectedValues.push($(this).val());
        });
        var $legacy = $('#rem_cat');
        if ($legacy.length) {
            $legacy.val(selectedValues).trigger('change');
        }
        setSelectedCountText($('.selected-count').first(), selectedValues.length);
    }

    function setSelectedCountText($el, count) {
        if (!$el || !$el.length) {
            return;
        }
        if (count > 0) {
            $el.text(count + ' selected').addClass('is-visible');
        } else {
            $el.text('').removeClass('is-visible');
        }
    }

    window.DropdownMultiSelect = {
        init: initDropdownMultiSelect,
        updateValues: function() {
            syncFromCheckbox($('.checkbox-item').first());
        },
        closeAllAssigneePickers: closeAllAssigneePickers
    };

})(jQuery);
