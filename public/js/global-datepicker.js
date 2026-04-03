/**
 * Global DatePicker Configuration for CRM
 * Version: 1.0
 * Created: 2025-01-20
 * 
 * Usage Guide:
 * - For NEW code: Use CRM_DatePicker methods
 * - For EXISTING client detail: Use bootstrap-datepicker (don't change)
 * - For EXISTING other pages: Use daterangepicker via scripts.js (don't change)
 * 
 * This helper is for NEW features going forward to ensure consistency.
 */

window.CRM_DatePicker = {
    /**
     * Standard single date picker (DD/MM/YYYY format)
     * Use for: New forms, modals, any new date inputs
     * 
     * Example: CRM_DatePicker.initStandard('.my-date-field');
     * 
     * @param {string|jQuery} selector - CSS selector or jQuery object
     * @param {object} options - Optional configuration overrides
     */
    initStandard: function(selector, options) {
        var defaults = {
            singleDatePicker: true,
            showDropdowns: true,
            locale: {
                format: 'DD/MM/YYYY',
                applyLabel: 'Apply',
                cancelLabel: 'Clear',
                daysOfWeek: ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'],
                monthNames: [
                    'January', 'February', 'March', 'April', 'May', 'June',
                    'July', 'August', 'September', 'October', 'November', 'December'
                ],
                firstDay: 1
            },
            autoApply: true,
            autoUpdateInput: false,
            minDate: '01/01/1900',
            minYear: 1900,
            maxYear: parseInt(moment().format('YYYY')) + 50
        };
        
        var config = $.extend({}, defaults, options || {});
        
        $(selector).daterangepicker(config)
            .on('apply.daterangepicker', function(ev, picker) {
                $(this).val(picker.startDate.format('DD/MM/YYYY'));
                $(this).trigger('change'); // Trigger change event for validation
            })
            .on('cancel.daterangepicker', function() {
                $(this).val('');
                $(this).trigger('change');
            });
        
        console.log('✅ CRM_DatePicker.initStandard:', selector);
    },

    /**
     * DOB picker with automatic age calculation
     * Use for: Date of birth fields
     * 
     * Example: CRM_DatePicker.initDOB('#dob', '#age');
     * 
     * @param {string|jQuery} dobSelector - DOB input selector
     * @param {string|jQuery} ageSelector - Age output field selector (optional)
     * @param {object} options - Optional configuration overrides
     */
    initDOB: function(dobSelector, ageSelector, options) {
        var defaults = {
            singleDatePicker: true,
            showDropdowns: true,
            locale: {
                format: 'DD/MM/YYYY',
                firstDay: 1
            },
            autoApply: true,
            autoUpdateInput: false,
            minDate: '01/01/1900',
            maxDate: moment().format('DD/MM/YYYY'),
            maxYear: parseInt(moment().format('YYYY'))
        };
        
        var config = $.extend({}, defaults, options || {});
        
        $(dobSelector).daterangepicker(config)
            .on('apply.daterangepicker', function(ev, picker) {
                var dateStr = picker.startDate.format('DD/MM/YYYY');
                $(this).val(dateStr);
                
                // Calculate age if age field is provided
                if (ageSelector && $(ageSelector).length) {
                    var age = CRM_DatePicker.calculateAge(dateStr);
                    $(ageSelector).val(age);
                }
                
                $(this).trigger('change');
            });
        
        console.log('✅ CRM_DatePicker.initDOB:', dobSelector, '→', ageSelector);
    },

    /**
     * DateTime picker (date + time selection)
     * Use for: Date and time selection (appointments, schedules)
     * 
     * Example: CRM_DatePicker.initDateTime('.datetime-field');
     * 
     * @param {string|jQuery} selector - CSS selector or jQuery object
     * @param {object} options - Optional configuration overrides
     */
    initDateTime: function(selector, options) {
        var defaults = {
            singleDatePicker: true,
            showDropdowns: true,
            timePicker: true,
            timePicker24Hour: false,
            timePickerIncrement: 15,
            locale: {
                format: 'DD/MM/YYYY h:mm A',
                firstDay: 1
            },
            autoApply: false,
            autoUpdateInput: false,
            minDate: '01/01/1900'
        };
        
        var config = $.extend({}, defaults, options || {});
        
        $(selector).daterangepicker(config)
            .on('apply.daterangepicker', function(ev, picker) {
                $(this).val(picker.startDate.format('DD/MM/YYYY h:mm A'));
                $(this).trigger('change');
            })
            .on('cancel.daterangepicker', function() {
                $(this).val('');
                $(this).trigger('change');
            });
        
        console.log('✅ CRM_DatePicker.initDateTime:', selector);
    },

    /**
     * Date range picker
     * Use for: Report filters, date range selection
     * 
     * Example: CRM_DatePicker.initRange('.date-range-field');
     * 
     * @param {string|jQuery} selector - CSS selector or jQuery object
     * @param {object} options - Optional configuration overrides
     */
    initRange: function(selector, options) {
        var defaults = {
            locale: {
                format: 'DD/MM/YYYY',
                separator: ' - ',
                cancelLabel: 'Clear'
            },
            autoApply: false,
            autoUpdateInput: false,
            showDropdowns: true
        };
        
        var config = $.extend({}, defaults, options || {});
        
        $(selector).daterangepicker(config)
            .on('apply.daterangepicker', function(ev, picker) {
                $(this).val(
                    picker.startDate.format('DD/MM/YYYY') + ' - ' + 
                    picker.endDate.format('DD/MM/YYYY')
                );
                $(this).trigger('change');
            })
            .on('cancel.daterangepicker', function() {
                $(this).val('');
                $(this).trigger('change');
            });
        
        console.log('✅ CRM_DatePicker.initRange:', selector);
    },

    /**
     * Calculate age from DD/MM/YYYY date string
     * Returns: "XX years YY months" format
     * 
     * @param {string} dob - Date of birth in DD/MM/YYYY format
     * @returns {string} Age string or empty string if invalid
     */
    calculateAge: function(dob) {
        if (!dob || !/^\d{2}\/\d{2}\/\d{4}$/.test(dob)) return '';
        
        try {
            const [day, month, year] = dob.split('/').map(Number);
            const dobDate = new Date(year, month - 1, day);
            if (isNaN(dobDate.getTime())) return '';

            const today = new Date();
            let years = today.getFullYear() - dobDate.getFullYear();
            let months = today.getMonth() - dobDate.getMonth();

            if (months < 0) {
                years--;
                months += 12;
            }
            if (today.getDate() < dobDate.getDate()) {
                months--;
                if (months < 0) {
                    years--;
                    months += 12;
                }
            }

            return years + ' years ' + months + ' months';
        } catch (e) {
            console.error('Age calculation error:', e);
            return '';
        }
    },

    /**
     * Convert DD/MM/YYYY to YYYY-MM-DD for database storage
     * 
     * @param {string} dateStr - Date in DD/MM/YYYY format
     * @returns {string|null} Date in YYYY-MM-DD format or null
     */
    toDatabase: function(dateStr) {
        if (!dateStr || !/^\d{2}\/\d{2}\/\d{4}$/.test(dateStr)) return null;
        const [day, month, year] = dateStr.split('/');
        return `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
    },

    /**
     * Convert YYYY-MM-DD to DD/MM/YYYY for display
     * 
     * @param {string} dateStr - Date in YYYY-MM-DD format
     * @returns {string} Date in DD/MM/YYYY format or empty string
     */
    toDisplay: function(dateStr) {
        if (!dateStr || !/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) return '';
        const [year, month, day] = dateStr.split('-');
        return `${day}/${month}/${year}`;
    },

    /**
     * Validate date string format
     * 
     * @param {string} dateStr - Date string to validate
     * @param {string} format - Expected format ('DD/MM/YYYY' or 'YYYY-MM-DD')
     * @returns {boolean} True if valid
     */
    isValid: function(dateStr, format) {
        if (!dateStr) return false;
        
        if (format === 'DD/MM/YYYY') {
            if (!/^\d{2}\/\d{2}\/\d{4}$/.test(dateStr)) return false;
            const [day, month, year] = dateStr.split('/').map(Number);
            const date = new Date(year, month - 1, day);
            return date.getFullYear() === year && 
                   date.getMonth() === month - 1 && 
                   date.getDate() === day;
        } else if (format === 'YYYY-MM-DD') {
            if (!/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) return false;
            const [year, month, day] = dateStr.split('-').map(Number);
            const date = new Date(year, month - 1, day);
            return date.getFullYear() === year && 
                   date.getMonth() === month - 1 && 
                   date.getDate() === day;
        }
        
        return false;
    }
};

/**
 * Auto-initialize datepickers with data attributes
 * This allows simple HTML-based initialization without JavaScript
 * 
 * Usage in HTML:
 * <input type="text" data-datepicker="standard" placeholder="dd/mm/yyyy">
 * <input type="text" data-datepicker="dob" data-age-field="#age">
 * <input type="text" data-datepicker="datetime">
 * <input type="text" data-datepicker="range">
 */
$(document).ready(function() {
    // Check if daterangepicker is available
    if (typeof $.fn.daterangepicker === 'undefined') {
        console.warn('⚠️ CRM_DatePicker: daterangepicker library not loaded');
        return;
    }

    // Auto-initialize elements with data-datepicker attributes
    $('[data-datepicker="standard"]').each(function() {
        CRM_DatePicker.initStandard(this);
    });
    
    $('[data-datepicker="dob"]').each(function() {
        var ageField = $(this).data('age-field');
        CRM_DatePicker.initDOB(this, ageField);
    });
    
    $('[data-datepicker="datetime"]').each(function() {
        CRM_DatePicker.initDateTime(this);
    });
    
    $('[data-datepicker="range"]').each(function() {
        CRM_DatePicker.initRange(this);
    });
    
    console.log('✅ CRM_DatePicker auto-initialization complete');
});

// Export for debugging
if (typeof window !== 'undefined') {
    console.log('✅ CRM_DatePicker loaded successfully - Use CRM_DatePicker.initStandard() for new code');
}

