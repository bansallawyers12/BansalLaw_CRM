// ANZSCO Admin JavaScript Functions

// Show notification
function showAnzscoNotification(message, type = 'info') {
    if (typeof toastr !== 'undefined') {
        toastr[type](message);
    } else {
        alert(message);
    }
}

// Format file size
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

// Validate ANZSCO code format
function validateAnzscoCode(code) {
    // ANZSCO codes are typically 6 digits
    const pattern = /^\d{6}$/;
    return pattern.test(code);
}

// Export table to CSV
function exportAnzscoToCSV() {
    if (typeof $ === 'undefined' || typeof $.fn.dataTable === 'undefined') {
        showAnzscoNotification('DataTables library not loaded', 'error');
        return;
    }
    
    const table = $('#anzscoTable').DataTable();
    const data = table.buttons.exportData();
    
    let csv = [];
    csv.push(data.header.join(','));
    
    data.body.forEach(row => {
        csv.push(row.join(','));
    });
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.setAttribute('hidden', '');
    a.setAttribute('href', url);
    a.setAttribute('download', 'anzsco_occupations_' + new Date().toISOString().split('T')[0] + '.csv');
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

// Initialize tooltips if Bootstrap is available
$(document).ready(function() {
    if (typeof $('[data-toggle="tooltip"]').tooltip === 'function') {
        $('[data-toggle="tooltip"]').tooltip();
    }
});

