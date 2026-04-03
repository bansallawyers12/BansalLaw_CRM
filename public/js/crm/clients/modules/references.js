/**
 * Sidebar References - Chip Style UI
 * Extracted from detail-main.js - Phase 3 refactoring.
 * Requires: jQuery, ClientDetailConfig, iziToast (optional)
 */
(function() {
    'use strict';

    // Check if reference section exists
    if (!$('#references-container').length) {
        return;
    }

    // State
    let isEditing = false;
    let editingType = null;

    // Initialize on page load
    function initializeSidebarReferences() {
        renderReferences();
    }

    // Render reference chips from hidden inputs
    function renderReferences() {
        const container = $('#references-container');
        container.empty();

        const deptRef = $('#department_reference').val();
        const otherRef = $('#other_reference').val();

        let chipCount = 0;

        // Render department reference
        if (deptRef && deptRef.trim() !== '') {
            chipCount++;
            container.append(createChip('department_reference', deptRef, 'Dept'));
        }

        // Render other reference
        if (otherRef && otherRef.trim() !== '') {
            chipCount++;
            container.append(createChip('other_reference', otherRef, 'Other'));
        }

        // Show/hide add button
        if (chipCount >= 2 || isEditing) {
            $('#btn-add-reference').addClass('hidden');
        } else {
            $('#btn-add-reference').removeClass('hidden');
        }
    }

    // Create chip element
    function createChip(type, value, label) {
        return $('<div>')
            .addClass('reference-chip')
            .attr('data-type', type)
            .attr('data-value', value)
            .attr('data-label', label)
            .attr('title', 'Click to edit • Double-click to delete')
            .text(value)
            .on('click', function(e) {
                if (e.detail === 1) {
                    const self = $(this);
                    self.data('clickTimer', setTimeout(function() {
                        editReference(type, value);
                    }, 250));
                }
            })
            .on('dblclick', function(e) {
                clearTimeout($(this).data('clickTimer'));
                confirmDeleteReference(type, value);
            });
    }

    // Add new reference button click
    $(document).on('click', '#btn-add-reference', function() {
        if (isEditing) return;

        isEditing = true;
        editingType = null;

        $(this).addClass('hidden');
        $('#reference-input-container').show();
        $('#reference-input').val('').focus();
    });

    // Edit existing reference
    function editReference(type, currentValue) {
        if (isEditing) return;

        isEditing = true;
        editingType = type;

        $(`.reference-chip[data-type="${type}"]`).addClass('editing');
        $('#btn-add-reference').addClass('hidden');
        $('#reference-input-container').show();
        $('#reference-input').val(currentValue).focus().select();
    }

    // Confirm delete reference
    function confirmDeleteReference(type, value) {
        if (isEditing) return;

        if (!confirm(`Delete reference "${value}"?`)) {
            return;
        }

        $('#' + type).val('');
        $('.saveReferenceValue').click();

        if (typeof iziToast !== 'undefined') {
            iziToast.info({
                title: 'Deleting',
                message: 'Reference removed',
                position: 'topRight',
                timeout: 2000
            });
        }

        setTimeout(function() {
            renderReferences();
        }, 500);
    }

    // Handle Enter key press in input
    $(document).on('keypress', '#reference-input', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            saveReference();
        }
    });

    // Handle Escape key press
    $(document).on('keydown', '#reference-input', function(e) {
        if (e.which === 27) {
            e.preventDefault();
            cancelInput();
        }
    });

    // Cancel button click
    $(document).on('click', '.btn-cancel-input', function() {
        cancelInput();
    });

    // Save reference
    function saveReference() {
        const value = $('#reference-input').val().trim();

        if (!value) {
            if (typeof iziToast !== 'undefined') {
                iziToast.warning({
                    title: 'Warning',
                    message: 'Please enter a reference value',
                    position: 'topRight',
                    timeout: 2000
                });
            } else {
                alert('Please enter a reference value');
            }
            $('#reference-input').focus();
            return;
        }

        let type = editingType;
        if (!type) {
            const deptRef = $('#department_reference').val();
            const otherRef = $('#other_reference').val();

            if (!deptRef || deptRef.trim() === '') {
                type = 'department_reference';
            } else if (!otherRef || otherRef.trim() === '') {
                type = 'other_reference';
            } else {
                if (typeof iziToast !== 'undefined') {
                    iziToast.error({
                        title: 'Error',
                        message: 'Maximum 2 references allowed',
                        position: 'topRight'
                    });
                } else {
                    alert('Maximum 2 references allowed');
                }
                cancelInput();
                return;
            }
        }

        $('#' + type).val(value);
        $('.sidebar-references').addClass('saving');

        let department_reference = $('#department_reference').val() || '';
        let other_reference = $('#other_reference').val() || '';
        let client_id = window.ClientDetailConfig.clientId;
        let selectedMatter = $('#sel_matter_id_client_detail').val();
        let matterIdFromUrl = window.ClientDetailConfig.matterId || '';

        if (!client_id || client_id === '' || client_id === 'null' || client_id === null) {
            $('.sidebar-references').removeClass('saving');
            alert('Error: Client ID is missing. Please refresh the page and try again.');
            console.error('Client ID is missing:', client_id);
            resetInputState();
            return;
        }

        $.ajax({
            url: window.ClientDetailConfig.urls.referencesStore,
            type: 'POST',
            data: {
                department_reference: department_reference,
                other_reference: other_reference,
                client_id: client_id,
                client_matter_id: selectedMatter || null,
                client_unique_matter_no: matterIdFromUrl || null,
                _token: window.ClientDetailConfig.csrfToken
            },
            success: function(response) {
                $('.sidebar-references').removeClass('saving');
                resetInputState();
                renderReferences();

                if (typeof iziToast !== 'undefined') {
                    iziToast.success({
                        title: 'Saved',
                        message: 'Reference saved successfully',
                        position: 'topRight',
                        timeout: 2000
                    });
                }
            },
            error: function(xhr) {
                $('.sidebar-references').removeClass('saving');
                console.error('Error saving references:', xhr.responseText);

                let errorMessage = 'Error saving data';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }

                if (typeof iziToast !== 'undefined') {
                    iziToast.error({
                        title: 'Error',
                        message: errorMessage,
                        position: 'topRight',
                        timeout: 3000
                    });
                } else {
                    alert(errorMessage);
                }
                resetInputState();
            }
        });
    }

    // Cancel input
    function cancelInput() {
        $('.reference-chip').removeClass('editing');
        resetInputState();
        renderReferences();
    }

    // Reset input state
    function resetInputState() {
        isEditing = false;
        editingType = null;
        $('#reference-input-container').hide();
        $('#reference-input').val('');
    }

    // Listen for matter changes
    $(document).on('change', '#sel_matter_id_client_detail', function() {
        cancelInput();
        setTimeout(function() {
            renderReferences();
        }, 500);
    });

    // Re-render when accounts tab updates references
    $(document).on('click', '.saveReferenceValue', function() {
        setTimeout(function() {
            renderReferences();
        }, 1000);
    });

    // Initialize
    $(document).ready(function() {
        initializeSidebarReferences();
    });

})();
