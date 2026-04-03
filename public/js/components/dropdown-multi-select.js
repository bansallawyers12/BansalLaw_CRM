/**
 * Dropdown Multi-Select Component
 * 
 * A reusable component for custom multi-select dropdowns with checkbox functionality.
 * Used in action pages for assignee selection.
 */

(function($) {
    'use strict';

    // Initialize on document ready
    $(document).ready(function() {
        console.log('✓ Dropdown multi-select script loaded');
        initDropdownMultiSelect();
    });

    /**
     * Initialize dropdown multi-select functionality
     */
    function initDropdownMultiSelect() {
        
        // Prevent dropdown from closing when clicking inside
        $(document).on('click', '.dropdown-menu', function(e) {
            e.stopPropagation();
        });

        // Handle checkbox changes
        $(document).on('change', '.checkbox-item', function() {
            updateSelectedValues();
        });

        // Handle "Select All"
        $(document).on('change', '#select-all', function() {
            var isChecked = $(this).is(':checked');
            $('.assignee-item:visible .checkbox-item').prop('checked', isChecked).trigger('change');
        });

        // CRITICAL: Search functionality - using direct event binding
        $(document).on('input', '.assignee-search-input', function(e) {
            e.stopPropagation();
            
            var searchTerm = $(this).val().toLowerCase();
            console.log('🔍 Searching for:', searchTerm);
            
            // Find all assignee items in the document
            var $items = $('.assignee-item');
            console.log('Found items:', $items.length);
            
            if ($items.length === 0) {
                console.error('❌ ERROR: No .assignee-item elements found!');
                return;
            }
            
            var matched = 0;
            var hidden = 0;
            
            $items.each(function() {
                var $item = $(this);
                var itemText = $item.text().toLowerCase();
                
                if (searchTerm === '' || itemText.indexOf(searchTerm) > -1) {
                    $item.show().removeClass('hidden');
                    matched++;
                } else {
                    $item.hide().addClass('hidden');
                    hidden++;
                }
            });
            
            console.log('✓ Matched:', matched, '| Hidden:', hidden);
        });

        // Clear search on dropdown open
        $(document).on('show.bs.dropdown', function() {
            $('.assignee-search-input').val('');
            $('.assignee-item').show().removeClass('hidden');
            console.log('Dropdown opened, search cleared');
        });
        
        // Focus search input after dropdown opens
        $(document).on('shown.bs.dropdown', function() {
            setTimeout(function() {
                $('.assignee-search-input').focus();
            }, 100);
        });
    }

    /**
     * Update the hidden select with selected values
     */
    function updateSelectedValues() {
        var selectedValues = [];
        $('.checkbox-item:checked').each(function() {
            selectedValues.push($(this).val());
        });
        
        $('#rem_cat').val(selectedValues).trigger('change');
        
        var count = selectedValues.length;
        if (count > 0) {
            $('.selected-count').text(' (' + count + ' selected)');
        } else {
            $('.selected-count').text('');
        }
    }

    // Expose globally
    window.DropdownMultiSelect = {
        init: initDropdownMultiSelect,
        updateValues: updateSelectedValues
    };

})(jQuery);
