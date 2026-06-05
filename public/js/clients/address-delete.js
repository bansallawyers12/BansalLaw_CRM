/**
 * Delete current address — loaded after edit-client.js so saveSectionData is available.
 * Uses window assignment fallback so cached edit-client.js builds still work.
 */
window.deleteCurrentAddress = window.deleteCurrentAddress || function () {
    var $entry = window.jQuery ? window.jQuery('#addresses-container .address-entry-wrapper').first() : null;
    if (!$entry || !$entry.length) {
        if (typeof showNotification === 'function') {
            showNotification('Address form not found. Please refresh the page and try again.', 'error');
        } else {
            alert('Address form not found. Please refresh the page and try again.');
        }
        return;
    }

    var addressId = $entry.find('input[name="address_id[]"]').val();
    if (!addressId) {
        if (typeof showNotification === 'function') {
            showNotification('No address found to delete.', 'error');
        } else {
            alert('No address found to delete.');
        }
        return;
    }

    if (!confirm('Are you sure you want to delete the current address? This action cannot be undone.')) {
        return;
    }

    if (typeof saveSectionData !== 'function') {
        if (typeof showNotification === 'function') {
            showNotification('Error: Save function not available. Please refresh the page and try again.', 'error');
        } else {
            alert('Error: Save function not available. Please refresh the page and try again.');
        }
        return;
    }

    var formData = new FormData();
    formData.append('delete_address', '1');
    formData.append('address_id', addressId);

    saveSectionData('addressInfo', formData, function () {
        window.location.reload();
    });
};
