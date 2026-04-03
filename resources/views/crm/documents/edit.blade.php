<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Designate Signatures - E-Signature App</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body.user-select-none {
            user-select: none;
            -webkit-user-select: none;
            -webkit-touch-callout: none;
            cursor: grabbing !important;
        }
        
        .preview-container {
            position: relative;
            display: inline-block;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
            background: white;
            min-height: 500px;
            min-width: 400px;
        }
        
        #preview-image {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
        }
        
        .signature-field-preview {
            position: absolute;
            border: 2px dashed #3b82f6;
            background-color: rgba(59, 130, 246, 0.1) !important;
            cursor: grab;
            transition: border-color 0.2s ease, background-color 0.2s ease;
            z-index: 10;
            user-select: none;
            -webkit-user-select: none;
        }
        
        .signature-field-preview.dragging {
            cursor: grabbing;
        }
        
        .signature-field-preview:hover {
            border-color: #1d4ed8;
            background-color: rgba(59, 130, 246, 0.2);
        }
        
        .signature-field-preview.active {
            border-color: #10b981;
            background-color: rgba(16, 185, 129, 0.2);
        }
        
        .signature-field-preview .field-label {
            position: absolute;
            top: -20px;
            left: 0;
            background: #3b82f6;
            color: white;
            padding: 2px 6px;
            font-size: 10px;
            border-radius: 3px;
            white-space: nowrap;
        }
        
        .signature-field-preview.active .field-label {
            background: #10b981;
        }
        
        .field-controls {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .field-controls button {
            padding: 4px 8px;
            font-size: 12px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }
        
        .edit-btn {
            background: #3b82f6;
            color: white;
        }
        
        .delete-btn {
            background: #ef4444;
            color: white;
        }
        
        .preview-btn {
            background: #10b981;
            color: white;
        }
        
        .layout-container {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 20px;
            max-width: 1600px;
            margin: 0 auto;
        }
        
        .preview-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            min-height: 80vh;
        }
        
        .form-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            max-height: 80vh;
            overflow-y: auto;
        }
        
        @media (max-width: 1200px) {
            .layout-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900 font-sans antialiased">
    <div class="min-h-screen p-4">
        <div class="max-w-7xl mx-auto">
            <!-- Header -->
            <div class="mb-6 text-center">
                <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-2">
                    Designate Signatures for {{ $document->file_name ?? 'Document' }}
                </h1>
                <p class="text-gray-600 dark:text-gray-400">
                    Click and drag on the document preview to position signature fields
                </p>
            </div>

            @if ($errors->any())
                <div class="mb-4 p-4 bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300 rounded-lg">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Success Message -->
            @if (session('success'))
                <div class="mb-4 p-4 bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            <div class="layout-container">
                <!-- Preview Section -->
                <div class="preview-section">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">
                        Document Preview ({{ $pdfPages }} pages)
                    </h2>
                    
                    @if($pdfPages > 1)
                    <!-- Compact Page Navigation -->
                    <div class="mb-4 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Navigate Pages:</span>
                            <div class="flex items-center space-x-2">
                                <button 
                                    onclick="switchPage(1)" 
                                    class="px-2 py-1 text-xs bg-blue-100 hover:bg-blue-200 dark:bg-blue-900 dark:hover:bg-blue-800 text-blue-800 dark:text-blue-200 rounded"
                                >
                                    First
                                </button>
                                <button 
                                    onclick="switchPage({{ $pdfPages }})" 
                                    class="px-2 py-1 text-xs bg-blue-100 hover:bg-blue-200 dark:bg-blue-900 dark:hover:bg-blue-800 text-blue-800 dark:text-blue-200 rounded"
                                >
                                    Last
                                </button>
                            </div>
                        </div>
                        
                        <!-- Current Page Display -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <button 
                                    id="prev-page-btn"
                                    onclick="switchToPreviousPage()" 
                                    class="px-3 py-1 text-sm bg-gray-200 hover:bg-gray-300 dark:bg-gray-600 dark:hover:bg-gray-500 text-gray-700 dark:text-gray-300 rounded disabled:opacity-50"
                                >
                                    ← Previous
                                </button>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Page <span id="current-page-number">1</span> of {{ $pdfPages }}
                                </span>
                                <button 
                                    id="next-page-btn"
                                    onclick="switchToNextPage()" 
                                    class="px-3 py-1 text-sm bg-gray-200 hover:bg-gray-300 dark:bg-gray-600 dark:hover:bg-gray-500 text-gray-700 dark:text-gray-300 rounded disabled:opacity-50"
                                >
                                    Next →
                                </button>
                            </div>
                            
                            <!-- Jump to Page -->
                            <div class="flex items-center space-x-2">
                                <label class="text-xs text-gray-600 dark:text-gray-400">Go to:</label>
                                <input 
                                    type="number" 
                                    id="page-jump-input"
                                    min="1" 
                                    max="{{ $pdfPages }}" 
                                    value="1"
                                    class="w-16 px-2 py-1 text-xs border border-gray-300 dark:border-gray-600 rounded text-center"
                                    onchange="jumpToPage(this.value)"
                                >
                            </div>
                        </div>
                        
                        <!-- Page Grid (Compact) -->
                        <details class="mt-2">
                            <summary class="text-xs text-blue-600 dark:text-blue-400 cursor-pointer hover:underline">
                                Show all pages (click to expand)
                            </summary>
                            <div class="mt-2 grid grid-cols-10 gap-1">
                                @for ($i = 1; $i <= $pdfPages; $i++)
                                    <button 
                                        onclick="switchPage({{ $i }})" 
                                        class="page-tab-mini px-1 py-1 text-xs bg-gray-100 hover:bg-gray-200 dark:bg-gray-600 dark:hover:bg-gray-500 text-gray-700 dark:text-gray-300 rounded border {{ $i === 1 ? 'ring-2 ring-blue-500 bg-blue-100 dark:bg-blue-800' : '' }}"
                                        data-page="{{ $i }}"
                                        title="Go to Page {{ $i }}"
                                    >
                                        {{ $i }}
                                    </button>
                                @endfor
                            </div>
                        </details>
                    </div>
                    @endif
                    
                    <div class="preview-container" id="preview-container">
                        <img
                            id="preview-image"
                            src="{{ url('debug-pdf-page/' . $document->id . '/1') }}"
                            alt="Document Preview"
                            style="max-width: 100%; height: auto; display: block;"
                        >
                        <div id="signature-fields-preview"></div>
                    </div>
                    
                    <div class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                        <p><strong>Instructions:</strong></p>
                        <ul class="list-disc list-inside mt-2 space-y-1">
                            <li>Click and drag on the document to position signature fields</li>
                            <li>Use the form on the right to adjust exact coordinates</li>
                            @if($pdfPages > 1)
                            <li>Navigate between pages using the controls above</li>
                            <li>Each page can have multiple signature fields</li>
                            @endif
                            <li>Preview shows exactly where signatures will appear</li>
                        </ul>
                    </div>
                </div>

                <!-- Form Section -->
                <div class="form-section">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">
                        Signature Fields
                    </h2>
                    
                    <form method="POST" action="{{ route('documents.update', $document->id) }}" id="signature-form">
                        @csrf
                        @method('PATCH')
                        <div id="signatures-container">
                            <!-- Signature fields will be added here dynamically -->
                        </div>

                        <!-- Add Signature Button -->
                        <div class="mb-6">
                            <button
                                type="button"
                                id="add-signature"
                                class="w-full px-4 py-2 bg-blue-600 text-white font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition"
                            >
                                + Add Signature Field
                            </button>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex justify-end">
                            <button
                                type="submit"
                                class="px-6 py-2 bg-green-600 text-white font-medium rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition"
                            >
                                Save Signature Locations
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Back Link -->
            <div class="mt-6 text-center">
                <a
                    href="{{ route('signatures.show', $document->id) }}"
                    class="text-blue-600 dark:text-blue-400 hover:underline"
                >
                    ← Back to Document
                </a>
            </div>
        </div>
    </div>

    <script>
        let currentPage = 1;
        let signatureFields = [];
        let selectedFieldIndex = -1;
        let isDragging = false;
        let dragStartX, dragStartY;
        let currentPdfPages = {{ $pdfPages }};

        // PDF page size in mm (from backend)
        const pdfPageWidthMM = {{ $pdfWidthMM }};
        const pdfPageHeightMM = {{ $pdfHeightMM }};
        
        // Page-specific dimensions
        const pagesDimensions = @json($pagesDimensions ?? []);

        // Get dimensions for a specific page
        function getPageDimensions(pageNumber) {
            return pagesDimensions[pageNumber] || {
                width: pdfPageWidthMM,
                height: pdfPageHeightMM,
                orientation: 'P'
            };
        }

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM Content Loaded');
            const img = document.getElementById('preview-image');
            console.log('Preview image element:', img);
            console.log('Image src:', img ? img.src : 'Image not found');
            
            loadSignatureFields();
            updatePreview();
            updateNavigationState(); // Initialize navigation state
            
            // Add click event to preview container for creating new fields
            document.getElementById('preview-container').addEventListener('click', function(e) {
                if (e.target.id === 'preview-image') {
                    createSignatureFieldFromClick(e);
                }
            });
            
            // Add resize listener for dynamic positioning
            window.addEventListener('resize', updatePreview);
            // Add image onload for dynamic preview
            if (img) {
                img.onload = function() {
                    console.log('Image loaded successfully');
                    updatePreview();
                };
                img.onerror = function() {
                    console.error('Image failed to load:', img.src);
                };
                
                // If image is already loaded (cached), trigger update
                if (img.complete) {
                    console.log('Image already loaded (cached)');
                    updatePreview();
                }
            } else {
                console.error('Preview image element not found!');
            }
        });

        function switchPage(pageNumber) {
            currentPage = pageNumber;
            
            // Update navigation buttons
            updateNavigationState();
            
            // Update preview image with debug route (temporary fix)
            const documentId = {{ $document->id }};
            const img = document.getElementById('preview-image');
            img.src = `{{ url('debug-pdf-page') }}/${documentId}/${pageNumber}`;
            
            // Wait for image load to update preview (ensures correct dimensions)
            img.onload = function() {
                updatePreview();
            };
            // If already loaded (cached), update immediately
            if (img.complete) {
                updatePreview();
            }
        }
        
        function switchToPreviousPage() {
            if (currentPage > 1) {
                switchPage(currentPage - 1);
            }
        }
        
        function switchToNextPage() {
            if (currentPage < currentPdfPages) {
                switchPage(currentPage + 1);
            }
        }
        
        function jumpToPage(pageNumber) {
            const page = parseInt(pageNumber);
            if (page >= 1 && page <= currentPdfPages) {
                switchPage(page);
            }
        }
        
        function updateNavigationState() {
            // Update current page number
            const currentPageSpan = document.getElementById('current-page-number');
            if (currentPageSpan) {
                currentPageSpan.textContent = currentPage;
            }
            
            // Update jump input
            const jumpInput = document.getElementById('page-jump-input');
            if (jumpInput) {
                jumpInput.value = currentPage;
            }
            
            // Update prev/next button states
            const prevBtn = document.getElementById('prev-page-btn');
            const nextBtn = document.getElementById('next-page-btn');
            
            if (prevBtn) {
                prevBtn.disabled = currentPage <= 1;
                prevBtn.classList.toggle('opacity-50', currentPage <= 1);
            }
            
            if (nextBtn) {
                nextBtn.disabled = currentPage >= currentPdfPages;
                nextBtn.classList.toggle('opacity-50', currentPage >= currentPdfPages);
            }
            
            // Update mini page tabs
            document.querySelectorAll('.page-tab-mini').forEach(tab => {
                const pageNum = parseInt(tab.getAttribute('data-page'));
                if (pageNum === currentPage) {
                    tab.classList.add('ring-2', 'ring-blue-500', 'bg-blue-100', 'dark:bg-blue-800');
                } else {
                    tab.classList.remove('ring-2', 'ring-blue-500', 'bg-blue-100', 'dark:bg-blue-800');
                }
            });
        }

        function createSignatureFieldFromClick(e) {
            const rect = e.target.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            addSignatureField(currentPage, x, y);
        }

        function getDisplayedDimensions() {
            const img = document.getElementById('preview-image');
            return {
                width: img ? img.clientWidth : 0,
                height: img ? img.clientHeight : 0
            };
        }

        function addSignatureField(page = 1, x = 0, y = 0) {
            const dims = getDisplayedDimensions();
            const width = 150;
            const height = 75;
            // Calculate percentages based on displayed size
            const x_percent = dims.width ? x / dims.width : 0;
            const y_percent = dims.height ? y / dims.height : 0;
            const w_percent = dims.width ? width / dims.width : 0;
            const h_percent = dims.height ? height / dims.height : 0;
            const field = {
                id: Date.now(),
                page_number: page,
                x_percent: x_percent,
                y_percent: y_percent,
                w_percent: w_percent,
                h_percent: h_percent
            };
            signatureFields.push(field);
            updateForm();
            updatePreview();
            selectField(signatureFields.length - 1);
        }

        function updateForm() {
            const container = document.getElementById('signatures-container');
            container.innerHTML = '';
            signatureFields.forEach((field, index) => {
                const fieldHtml = `
                    <div class="signature-field mb-6 p-4 border border-gray-200 dark:border-gray-600 rounded-lg ${selectedFieldIndex === index ? 'ring-2 ring-blue-500' : ''}">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Signature ${index + 1}</h3>
                            <div class="field-controls">
                                <button type="button" class="preview-btn" onclick="selectField(${index})">Preview</button>
                                <button type="button" class="edit-btn" onclick="editField(${index})">Edit</button>
                                <button type="button" class="delete-btn" onclick="deleteField(${index})">Delete</button>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Page
                                    <input
                                        type="number"
                                        name="signatures[${index}][page_number]"
                                        value="${field.page_number}"
                                        min="1"
                                        max="${currentPdfPages}"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"
                                        onchange="updateField(${index}, 'page_number', this.value)"
                                        required
                                    >
                                </label>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    X %
                                    <input
                                        type="number"
                                        name="signatures[${index}][x_percent]"
                                        value="${(field.x_percent * 100).toFixed(2)}"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"
                                        onchange="updateField(${index}, 'x_percent', this.value / 100)"
                                        required
                                    >
                                </label>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Y %
                                    <input
                                        type="number"
                                        name="signatures[${index}][y_percent]"
                                        value="${(field.y_percent * 100).toFixed(2)}"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"
                                        onchange="updateField(${index}, 'y_percent', this.value / 100)"
                                        required
                                    >
                                </label>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Width %
                                    <input
                                        type="number"
                                        name="signatures[${index}][w_percent]"
                                        value="${(field.w_percent * 100).toFixed(2)}"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"
                                        onchange="updateField(${index}, 'w_percent', this.value / 100)"
                                        required
                                    >
                                </label>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Height %
                                    <input
                                        type="number"
                                        name="signatures[${index}][h_percent]"
                                        value="${(field.h_percent * 100).toFixed(2)}"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"
                                        onchange="updateField(${index}, 'h_percent', this.value / 100)"
                                        required
                                    >
                                </label>
                            </div>
                        </div>
                    </div>
                `;
                container.insertAdjacentHTML('beforeend', fieldHtml);
            });
        }

        function updateField(index, property, value) {
            signatureFields[index][property] = parseFloat(value);
            updatePreview();
        }

        function selectField(index) {
            selectedFieldIndex = index;
            const field = signatureFields[index];
            
            // Switch to the correct page if needed
            if (field.page_number !== currentPage) {
                switchPage(field.page_number);
            }
            
            updateForm();
            updatePreview();
        }

        function editField(index) {
            selectField(index);
        }

        function deleteField(index) {
            if (confirm('Are you sure you want to delete this signature field?')) {
                signatureFields.splice(index, 1);
                selectedFieldIndex = -1;
                updateForm();
                updatePreview();
            }
        }

        function updatePreview() {
            const container = document.getElementById('signature-fields-preview');
            container.innerHTML = '';
            const dims = getDisplayedDimensions();
            signatureFields.forEach((field, index) => {
                if (field.page_number === currentPage) {
                    const fieldElement = document.createElement('div');
                    fieldElement.className = `signature-field-preview ${selectedFieldIndex === index ? 'active' : ''}`;
                    const left = field.x_percent * dims.width;
                    const top = field.y_percent * dims.height;
                    const width = field.w_percent * dims.width;
                    const height = field.h_percent * dims.height;
                    fieldElement.style.cssText = `
                        left: ${left}px;
                        top: ${top}px;
                        width: ${width}px;
                        height: ${height}px;
                    `;
                    fieldElement.innerHTML = `
                        <div class="field-label" style="pointer-events: none;">Signature ${index + 1}</div>
                    `;
                    // Add drag functionality - use currentTarget so rect is always the field (not child)
                    fieldElement.addEventListener('mousedown', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        startDrag(e, index);
                    });
                    fieldElement.addEventListener('touchstart', function(e) {
                        if (e.touches.length === 1) {
                            e.preventDefault();
                            e.stopPropagation();
                            const touch = e.touches[0];
                            const synth = { clientX: touch.clientX, clientY: touch.clientY, currentTarget: fieldElement, target: fieldElement, preventDefault: function(){} };
                            startDrag(synth, index);
                        }
                    }, { passive: false });
                    fieldElement.addEventListener('click', function(e) {
                        if (!isDragging) {
                            e.stopPropagation();
                            selectField(index);
                        }
                    });
                    container.appendChild(fieldElement);
                }
            });
        }

        function startDrag(e, fieldIndex) {
            e.preventDefault();
            isDragging = true;
            selectedFieldIndex = fieldIndex;
            document.body.classList.add('user-select-none');
            document.querySelectorAll('.signature-field-preview').forEach(el => el.classList.add('dragging'));
            
            const dragEl = e.currentTarget || e.target;
            const rect = dragEl.getBoundingClientRect ? dragEl.getBoundingClientRect() : e.target.getBoundingClientRect();
            dragStartX = e.clientX - rect.left;
            dragStartY = e.clientY - rect.top;
            
            document.addEventListener('mousemove', onDrag);
            document.addEventListener('mouseup', stopDrag);
            document.addEventListener('touchmove', onDrag, { passive: false });
            document.addEventListener('touchend', stopDrag);
            document.addEventListener('touchcancel', stopDrag);
        }

        function onDrag(e) {
            if (!isDragging) return;
            e.preventDefault();
            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            const clientY = e.touches ? e.touches[0].clientY : e.clientY;
            const container = document.getElementById('preview-container');
            const dims = getDisplayedDimensions();
            const rect = container.getBoundingClientRect();
            const x = (clientX - rect.left - dragStartX) / dims.width;
            const y = (clientY - rect.top - dragStartY) / dims.height;
            // Clamp to 0-1
            signatureFields[selectedFieldIndex].x_percent = Math.max(0, Math.min(1, x));
            signatureFields[selectedFieldIndex].y_percent = Math.max(0, Math.min(1, y));
            updatePreview();
            updateForm();
        }

        function stopDrag() {
            isDragging = false;
            document.body.classList.remove('user-select-none');
            document.querySelectorAll('.signature-field-preview').forEach(el => el.classList.remove('dragging'));
            document.removeEventListener('mousemove', onDrag);
            document.removeEventListener('mouseup', stopDrag);
            document.removeEventListener('touchmove', onDrag);
            document.removeEventListener('touchend', stopDrag);
            document.removeEventListener('touchcancel', stopDrag);
        }

        function loadSignatureFields() {
            // Load existing signature fields if any
            @if($document->signatureFields->count() > 0)
                @foreach($document->signatureFields as $field)
                    signatureFields.push({
                        id: {{ $field->id }},
                        page_number: {{ $field->page_number }},
                        x_percent: {{ $field->x_percent ?? 0 }},
                        y_percent: {{ $field->y_percent ?? 0 }},
                        w_percent: {{ $field->width_percent ?? 0 }},
                        h_percent: {{ $field->height_percent ?? 0 }}
                    });
                @endforeach
            @endif
        }

        // Add signature button
        document.getElementById('add-signature').addEventListener('click', function() {
            const dims = getDisplayedDimensions();
            addSignatureField(currentPage, dims.width / 2, dims.height / 2);
        });

        let isSubmitting = false;
        const signatureForm = document.getElementById('signature-form');
        if (signatureForm) {
            signatureForm.addEventListener('submit', function(e) {
                if (!isSubmitting) {
                    e.preventDefault(); // Prevent default submission on first click
                    
                    // Ensure the latest fields are rendered
                    updateForm();
                    
                    // Validate that we have at least one signature field
                    if (signatureFields.length === 0) {
                        alert('Please add at least one signature field before saving.');
                        isSubmitting = false; // Reset flag
                        return;
                    }
                    
                    // Set flag and submit the form programmatically after DOM updates
                    isSubmitting = true;
                    
                    // Use setTimeout to ensure DOM updates are complete
                    // Store form reference to avoid context loss
                    const form = signatureForm;
                    setTimeout(() => {
                        form.submit();
                    }, 100);
                }
            });
        }
    </script>
</body>
</html>
