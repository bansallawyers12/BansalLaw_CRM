/**
 * Flatpickr helper utilities for client detail pages.
 * Extracted from detail-main.js - Phase 2 refactoring.
 * Requires: jQuery, Flatpickr
 */
(function($) {
    'use strict';
    if (!$) return;

    /**
     * Initialize Flatpickr for elements matching selector.
     * @param {string} selector - jQuery selector for datepicker elements
     * @param {Object} [options] - Flatpickr options (merged with defaults)
     */
    function initFlatpickrForClass(selector, options) {
        if (typeof flatpickr === 'undefined') {
            console.warn('⚠️ Flatpickr not loaded for', selector);
            return;
        }

        var defaults = {
            dateFormat: 'd/m/Y',
            allowInput: true,
            clickOpens: true,
            locale: {
                firstDayOfWeek: 1
            }
        };

        var config = $.extend({}, defaults, options || {});

        $(selector).each(function() {
            var $this = $(this);
            var element = this;

            if ($this.data('flatpickr')) {
                return;
            }

            // Per-element copy so defaultDate mutations do not leak across fields
            var elementConfig = $.extend({}, config);

            if (!$this.val() && elementConfig.defaultDate === undefined) {
                elementConfig.defaultDate = null;
            } else if ($this.val() && elementConfig.defaultDate === undefined) {
                elementConfig.defaultDate = $this.val();
            }

            var fp = flatpickr(element, elementConfig);
            $this.data('flatpickr', fp);
        });
    }

    /**
     * Initialize Flatpickr with AJAX callback on date change.
     * @param {string} selector - jQuery selector
     * @param {string} ajaxUrl - URL for AJAX request
     * @param {Function} [ajaxDataCallback] - Optional callback(dateStr) to build AJAX data
     * @param {Object} [options] - Flatpickr options
     */
    function initFlatpickrWithAjax(selector, ajaxUrl, ajaxDataCallback, options) {
        if (typeof flatpickr === 'undefined') {
            console.warn('⚠️ Flatpickr not loaded for', selector);
            return;
        }

        var defaults = {
            dateFormat: 'Y-m-d',
            allowInput: true,
            clickOpens: true,
            locale: { firstDayOfWeek: 1 }
        };

        var config = $.extend({}, defaults, options || {});

        $(selector).each(function() {
            var $this = $(this);
            var element = this;

            if ($this.data('flatpickr')) {
                return;
            }

            var originalOnChange = config.onChange;
            config.onChange = function(selectedDates, dateStr, instance) {
                $this.val(dateStr);

                if (dateStr && ajaxUrl) {
                    $('#popuploader').show();
                    var ajaxData = ajaxDataCallback ? ajaxDataCallback(dateStr) : { from: dateStr };
                    $.ajax({
                        url: ajaxUrl,
                        method: 'GET',
                        dataType: 'json',
                        data: ajaxData,
                        success: function() {
                            $('#popuploader').hide();
                        }
                    });
                }

                if (originalOnChange) {
                    originalOnChange(selectedDates, dateStr, instance);
                }
            };

            var fp = flatpickr(element, config);
            $this.data('flatpickr', fp);
        });
    }

    window.initFlatpickrForClass = initFlatpickrForClass;
    window.initFlatpickrWithAjax = initFlatpickrWithAjax;

})(typeof jQuery !== 'undefined' ? jQuery : null);
