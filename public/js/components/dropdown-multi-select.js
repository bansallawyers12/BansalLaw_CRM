/**
 * Dropdown Multi-Select Component
 *
 * Custom multi-select with checkboxes. Syncs to a hidden <select multiple>.
 * Scopes updates to .add-task-layout or the nearest .modal / #create_action_popup
 * so multiple widgets on one page do not overwrite each other.
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

        $(document).on('change', '.checkbox-item', function() {
            syncFromCheckbox($(this));
        });

        $(document).on('change', '#select-all, #add_task_select_all', function() {
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
            var $dropdown = $(this).closest('.dropdown-multi-select');
            var $items = $dropdown.length ? $dropdown.find('.assignee-item') : $('.assignee-item');

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

        $(document).on('show.bs.dropdown', function(e) {
            var $dm = $(e.target).closest('.dropdown-multi-select');
            if (!$dm.length) {
                return;
            }
            $dm.find('.assignee-search-input').val('');
            $dm.find('.assignee-item').show().removeClass('hidden');
        });

        $(document).on('shown.bs.dropdown', function(e) {
            var $dm = $(e.target).closest('.dropdown-multi-select');
            if (!$dm.length) {
                return;
            }
            var $input = $dm.find('.assignee-search-input').first();
            setTimeout(function() {
                $input.trigger('focus');
            }, 100);
        });
    }

    /**
     * @param {JQuery} $checkbox
     */
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
            setSelectedCountText($root.find('.dropdown-multi-select .selected-count').first(), selectedValues.length);
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
            $el.text(' (' + count + ' selected)');
        } else {
            $el.text('');
        }
    }

    window.DropdownMultiSelect = {
        init: initDropdownMultiSelect,
        updateValues: function() {
            syncFromCheckbox($('.checkbox-item').first());
        }
    };

})(jQuery);
