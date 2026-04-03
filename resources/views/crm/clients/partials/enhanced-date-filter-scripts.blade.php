{{-- Enhanced Date Filter Scripts - JavaScript only (Flatpickr already loaded in layout) --}}
// Initialize Flatpickr for custom date range
$(document).ready(function() {
    if (typeof flatpickr !== 'undefined') {
        // Initialize From Date
        if ($('#from_date').length && !$('#from_date').data('flatpickr')) {
            flatpickr('#from_date', {
                dateFormat: 'd/m/Y',
                allowInput: true,
                clickOpens: true,
                defaultDate: $('#from_date').val() || null,
                locale: {
                    firstDayOfWeek: 1 // Monday
                },
                onChange: function(selectedDates, dateStr, instance) {
                    $('#from_date').val(dateStr);
                    $('#from_date').trigger('change');
                }
            });
        }
        
        // Initialize To Date
        if ($('#to_date').length && !$('#to_date').data('flatpickr')) {
            flatpickr('#to_date', {
                dateFormat: 'd/m/Y',
                allowInput: true,
                clickOpens: true,
                defaultDate: $('#to_date').val() || null,
                locale: {
                    firstDayOfWeek: 1 // Monday
                },
                onChange: function(selectedDates, dateStr, instance) {
                    $('#to_date').val(dateStr);
                    $('#to_date').trigger('change');
                }
            });
        }
    } else {
        console.warn('⚠️ Flatpickr not loaded for date filter');
    }
});

// Quick Filter Chips Functionality
$('.quick-filter-chip').on('click', function() {
    var filterType = $(this).data('filter');
    
    // Remove active class from all chips
    $('.quick-filter-chip').removeClass('active');
    
    // Add active class to clicked chip
    $(this).addClass('active');
    
    // Set the hidden input value
    $('#date_filter_type').val(filterType);
    
    // Clear custom date fields and financial year when using quick filters
    $('#from_date').val('');
    $('#to_date').val('');
    $('#financial_year').val('');
    
    // Auto-submit form
    $('#filterForm').submit();
});

// Custom Date Range - Clear quick filters and FY when dates are entered
$('#from_date, #to_date').on('change', function() {
    if ($('#from_date').val() || $('#to_date').val()) {
        $('.quick-filter-chip').removeClass('active');
        $('#date_filter_type').val('custom');
        $('#financial_year').val('');
    }
});

// Financial Year - Clear other date filters when FY is selected
$('#financial_year').on('change', function() {
    if ($(this).val()) {
        $('.quick-filter-chip').removeClass('active');
        $('#date_filter_type').val('financial_year');
        $('#from_date').val('');
        $('#to_date').val('');
    }
});

// Clear Date Filters Button
$('#clearDateFilters').on('click', function() {
    $('.quick-filter-chip').removeClass('active');
    $('#date_filter_type').val('');
    $('#from_date').val('');
    $('#to_date').val('');
    $('#financial_year').val('');
    $('#filterForm').submit();
});

// Validation before form submit
$('#filterForm').on('submit', function(e) {
    var fromDate = $('#from_date').val();
    var toDate = $('#to_date').val();
    
    // If one custom date is filled, both should be filled
    if ((fromDate && !toDate) || (!fromDate && toDate)) {
        e.preventDefault();
        alert('Please select both From Date and To Date for custom range filtering.');
        return false;
    }
    
    // Validate date order if both are filled
    if (fromDate && toDate) {
        var from = parseDate(fromDate);
        var to = parseDate(toDate);
        
        if (from > to) {
            e.preventDefault();
            alert('From Date cannot be later than To Date.');
            return false;
        }
    }
});

// Helper function to parse d/m/Y format (DD/MM/YYYY)
function parseDate(dateStr) {
    if (!dateStr) return null;
    var parts = dateStr.split('/');
    if (parts.length !== 3) return null;
    // month is 0-based in JavaScript Date
    // Format: day/month/year
    var day = parseInt(parts[0], 10);
    var month = parseInt(parts[1], 10) - 1; // 0-based
    var year = parseInt(parts[2], 10);
    return new Date(year, month, day);
}
