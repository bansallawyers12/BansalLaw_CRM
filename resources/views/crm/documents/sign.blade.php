<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign Document - E-Signature App</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .signature-pad {
            touch-action: none;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }
        .signature-pad canvas {
            border: 1px solid #ccc;
            border-radius: 4px;
            background-color: transparent;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(2px);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease-out;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #333;
        }
        
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }
        
        .close:hover {
            color: #000;
        }
        
        .signature-canvas-container {
            text-align: center;
            margin: 20px 0;
        }
        
        .signature-controls {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 15px;
        }
        
        .signature-controls button {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.2s;
        }
        
        .clear-btn {
            background-color: #f44336;
            color: white;
        }
        
        .clear-btn:hover {
            background-color: #d32f2f;
        }
        
        .save-btn {
            background-color: #4CAF50;
            color: white;
        }
        
        .save-btn:hover {
            background-color: #388e3c;
        }
        
        .cancel-btn {
            background-color: #9e9e9e;
            color: white;
        }
        
        .cancel-btn:hover {
            background-color: #757575;
        }
        
        /* Tabs Styles */
        .signature-tabs {
            display: flex;
            border-bottom: 2px solid #eee;
            margin-bottom: 20px;
        }
        
        .signature-tab {
            flex: 1;
            padding: 12px 20px;
            text-align: center;
            cursor: pointer;
            background-color: #f5f5f5;
            border: none;
            border-bottom: 3px solid transparent;
            font-size: 14px;
            font-weight: 500;
            color: #666;
            transition: all 0.3s;
        }
        
        .signature-tab:hover {
            background-color: #e8e8e8;
            color: #333;
        }
        
        .signature-tab.active {
            background-color: white;
            color: #4CAF50;
            border-bottom-color: #4CAF50;
        }
        
        .signature-tab-content {
            display: none;
        }
        
        .signature-tab-content.active {
            display: block;
        }
        
        /* Type Signature Styles */
        .fallback-signature {
            width: 100%;
            min-height: 150px;
            padding: 15px;
            border: 2px solid #ccc;
            border-radius: 4px;
            font-size: 24px;
            font-family: 'Brush Script MT', 'Lucida Handwriting', cursive, sans-serif;
            resize: vertical;
            text-align: center;
        }
        
        .fallback-signature:focus {
            outline: none;
            border-color: #4CAF50;
        }
        
        /* Upload Signature Styles */
        .upload-signature-container {
            text-align: center;
            padding: 20px;
        }
        
        .upload-signature-input {
            display: none;
        }
        
        .upload-signature-label {
            display: inline-block;
            padding: 12px 24px;
            background-color: #2196F3;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.2s;
        }
        
        .upload-signature-label:hover {
            background-color: #1976D2;
        }
        
        .upload-signature-preview {
            margin-top: 20px;
            max-width: 100%;
            max-height: 200px;
            border: 2px solid #ccc;
            border-radius: 4px;
            display: none;
        }
        
        .upload-signature-preview.active {
            display: block;
            margin: 20px auto;
        }
        
        .signature-instructions {
            text-align: center;
            color: #666;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .fallback-signature {
            width: 100%;
            height: 200px;
            border: 1px solid #ccc;
            border-radius: 4px;
            background-color: white;
            font-family: cursive;
            font-size: 18px;
            padding: 10px;
            resize: none;
        }

        /* Professional Signature Field Styling */
        .signature-field {
            transition: all 0.3s ease;
            border-width: 2px;
        }
        .signature-field:hover {
            box-shadow: 0 0 0 4px rgba(255, 204, 0, 0.3);
            transform: scale(1.02);
            border-color: #fbbf24 !important;
        }
        .signature-field.signed {
            background-color: rgba(16, 185, 129, 0.15) !important;
            border-color: #10b981 !important;
        }
        .signature-field.active-highlight {
            animation: pulseGlow 1.5s ease-in-out 2;
        }
        @keyframes pulseGlow {
            0%, 100% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0); }
            50% { box-shadow: 0 0 0 8px rgba(59, 130, 246, 0.4); }
        }

        /* DocuSign-style yellow tag with arrow */
        .ds-tag {
            position: absolute;
            top: -18px;
            left: 0;
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            color: #1f2937;
            font-weight: 700;
            font-size: 12px;
            padding: 4px 10px;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.15);
            white-space: nowrap;
            pointer-events: none;
            z-index: 100;
        }
        .ds-tag::after {
            content: '';
            position: absolute;
            left: 12px;
            bottom: -6px;
            width: 0;
            height: 0;
            border-left: 6px solid transparent;
            border-right: 6px solid transparent;
            border-top: 6px solid #f59e0b;
        }
        .signature-field.signed .ds-tag {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        .signature-field.signed .ds-tag::after {
            border-top-color: #059669;
        }

        /* Sticky Signing Header */
        .signing-header {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            color: white;
            padding: 16px 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        .signing-info {
            display: flex;
            align-items: center;
            gap: 24px;
        }
        .signing-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 18px;
            font-weight: 600;
        }
        .title-icon {
            font-size: 24px;
        }
        .progress-badge {
            background: rgba(255,255,255,0.2);
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        .signing-actions {
            display: flex;
            gap: 10px;
        }
        .sign-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .sign-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.2);
        }
        .sign-btn-primary {
            background: #10b981;
            color: white;
        }
        .sign-btn-primary:hover {
            background: #059669;
        }
        .sign-btn-secondary {
            background: #6b7280;
            color: white;
        }
        .sign-btn-secondary:hover {
            background: #4b5563;
        }
        .sign-btn-success {
            background: #f59e0b;
            color: #1f2937;
        }
        .sign-btn-success:hover {
            background: #d97706;
        }
    </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900 font-sans antialiased">
    
    <!-- NEW: Signing Progress Header -->
    <div class="signing-header">
        <div class="signing-info">
            <div class="signing-title">
                <span class="title-icon">📝</span>
                <span class="title-text">{{ $document->file_name ?? 'Document' }}</span>
            </div>
            <div class="signing-progress">
                <span class="progress-badge">
                    <span id="signed-count">0</span>/<span id="total-count">0</span> Signed
                </span>
            </div>
        </div>
        <div class="signing-actions">
            <button id="start-signing" class="sign-btn sign-btn-primary">
                <span>▶</span> Start Signing
            </button>
            <button id="next-field" class="sign-btn sign-btn-secondary" style="display:none;">
                Next Field →
            </button>
            <button id="finish-signing" class="sign-btn sign-btn-success" style="display:none;">
                ✓ Submit All Signatures
            </button>
        </div>
    </div>
    
    <div class="min-h-screen flex items-center justify-center px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl w-full bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 sm:p-8">
            <!-- Header -->
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6 text-center">
                Sign Document: {{ $document->file_name ?? 'Document' }}
            </h1>

            <!-- Error Message -->
            @if (session('error'))
                <div class="mb-4 p-4 bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300 rounded-lg">
                    {{ session('error') }}
                </div>
            @endif

            <!-- Debug Info -->
            <div id="debug-info" class="mb-4 p-4 bg-yellow-100 dark:bg-yellow-900 text-yellow-700 dark:text-yellow-300 rounded-lg" style="display: none;">
                <strong>Debug Info:</strong>
                <div id="debug-content"></div>
            </div>

            @if ($pdfPages > 1)
            <!-- Page Navigation -->
            <div class="mb-6 p-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Document Pages ({{ $pdfPages }} total)
                    </h3>
                    <div class="flex items-center space-x-2">
                        <button 
                            onclick="scrollToPage(1)" 
                            class="px-3 py-1 text-sm bg-blue-100 hover:bg-blue-200 dark:bg-blue-900 dark:hover:bg-blue-800 text-blue-800 dark:text-blue-200 rounded"
                        >
                            Go to Top
                        </button>
                        <button 
                            onclick="scrollToPage({{ $pdfPages }})" 
                            class="px-3 py-1 text-sm bg-blue-100 hover:bg-blue-200 dark:bg-blue-900 dark:hover:bg-blue-800 text-blue-800 dark:text-blue-200 rounded"
                        >
                            Go to Last Page
                        </button>
                    </div>
                </div>
                
                <!-- Page Quick Navigation -->
                <div class="mt-3 flex flex-wrap gap-2">
                    @for ($i = 1; $i <= $pdfPages; $i++)
                        <button 
                            onclick="scrollToPage({{ $i }})" 
                            class="px-2 py-1 text-xs bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded border"
                            title="Go to Page {{ $i }}"
                        >
                            {{ $i }}
                        </button>
                    @endfor
                </div>
            </div>
            @endif

            <!-- Signature Pages -->
            <div class="space-y-6">
                @for ($i = 1; $i <= $pdfPages; $i++)
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                            Page {{ $i }}
                        </h2>
                        <div class="relative">
                            <img
                                id="pdf-image-{{ $i }}"
                                src="{{ route('public.documents.page', ['id' => $document->id, 'page' => $i]) }}"
                                alt="Page {{ $i }}"
                                class="w-full h-auto rounded-md shadow-sm"
                                style="max-width: 100%; z-index: 1; pointer-events: none;"
                            >
                            @foreach ($signatureFields as $field)
                                @if ($field->page_number == $i)
                                    <div
                                        id="signature-field-{{ $field->id }}"
                                        class="signature-field absolute border-2 border-dashed cursor-pointer"
                                        data-x-percent="{{ $field->x_percent ?? 0 }}"
                                        data-y-percent="{{ $field->y_percent ?? 0 }}"
                                        data-w-percent="{{ $field->width_percent ?? 0 }}"
                                        data-h-percent="{{ $field->height_percent ?? 0 }}"
                                        data-page="{{ $i }}"
                                        style="border-color: #ffcc00; background-color: rgba(255, 204, 0, 0.15); z-index: 50; pointer-events: auto;"
                                        onclick="activateSignatureField({{ $field->id }}, {{ $i }})"
                                    >
                                        <div class="ds-tag">✍️ Sign Here</div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endfor
            </div>

            <!-- Signature Form -->
            <form method="POST" action="{{ route('public.documents.submitSignatures', $document->id) }}">
                @csrf
                <input type="hidden" name="signer_id" value="{{ $signer->id }}">
                @for ($i = 1; $i <= $pdfPages; $i++)
                    <input
                        type="hidden"
                        name="signatures[{{ $i }}]"
                        id="signature-input-{{ $i }}"
                    >
                    <input
                        type="hidden"
                        name="signature_positions[{{ $i }}]"
                        id="signature-position-{{ $i }}"
                    >
                @endfor
                <div class="mt-6 flex justify-end">
                    <button
                        type="submit"
                        class="w-full sm:w-auto px-6 py-2 bg-blue-600 text-white font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition"
                    >
                        Submit Signatures
                    </button>
                </div>
            </form>

            <!-- Back Link -->
            <div class="mt-6 text-center">
                <a
                    href="{{ route('signatures.show', $document->id) }}"
                    class="text-blue-600 dark:text-blue-400 hover:underline"
                >
                    Back to Document
                </a>
            </div>
        </div>
    </div>

    <!-- Signature Modal -->
    <div id="signature-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Sign Here</h3>
                <span class="close" onclick="closeSignatureModal()">&times;</span>
            </div>
            
            <!-- Tabs -->
            <div class="signature-tabs">
                <button class="signature-tab active" onclick="switchSignatureTab('draw', this)">Draw</button>
                <button class="signature-tab" onclick="switchSignatureTab('upload', this)">Upload</button>
            </div>
            
            <!-- Draw Tab Content -->
            <div id="draw-tab" class="signature-tab-content active">
                <div class="signature-instructions">
                    Use your mouse, touch, or stylus to draw your signature below
                </div>
                
                <div class="signature-canvas-container">
                    <canvas
                        id="signature-pad"
                        class="signature-pad"
                        width="400"
                        height="200"
                    ></canvas>
                </div>
            </div>
            
            <!-- Upload Tab Content -->
            <div id="upload-tab" class="signature-tab-content">
                <div class="signature-instructions">
                    Upload an image of your signature (PNG, JPG, or SVG)
                </div>
                
                <div class="upload-signature-container">
                    <input type="file" id="upload-signature-input" class="upload-signature-input" accept="image/png,image/jpeg,image/jpg,image/svg+xml" onchange="handleSignatureUpload(event)">
                    <label for="upload-signature-input" class="upload-signature-label">Choose Image</label>
                    <img id="upload-signature-preview" class="upload-signature-preview" alt="Signature preview">
                </div>
            </div>
            
            <div class="signature-controls">
                <button type="button" class="clear-btn" onclick="clearSignature()">Clear</button>
                <button type="button" class="cancel-btn" onclick="closeSignatureModal()">Cancel</button>
                <button type="button" class="save-btn" onclick="saveSignature()">Save Signature</button>
                <button type="button" class="save-btn" id="paste-signature-btn" style="display:none; margin-left:8px;" onclick="pasteSignature()">Paste Signature</button>
            </div>
        </div>
    </div>

    <!-- Simple SignaturePad Implementation -->
    <script>
        // Simple SignaturePad implementation
        class SimpleSignaturePad {
            constructor(canvas, options = {}) {
                this.canvas = canvas;
                this.ctx = canvas.getContext('2d');
                this.ctx.globalCompositeOperation = 'source-over'; // Ensure drawing on transparent
                this.isDrawing = false;
                this.points = [];
                
                this.options = {
                    // backgroundColor: options.backgroundColor || 'rgb(255, 255, 255)', // Remove background color for transparency
                    penColor: options.penColor || 'rgb(0, 0, 0)',
                    minWidth: options.minWidth || 0.5,
                    maxWidth: options.maxWidth || 2.5,
                    ...options
                };
                
                this.clear();
                this.setupEventListeners();
            }
            
            clear() {
                // Make canvas transparent
                this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
                this.points = [];
            }
            
            isEmpty() {
                return this.points.length === 0;
            }
            
            toDataURL(type = 'image/png') {
                return this.canvas.toDataURL(type); // PNG supports transparency
            }
            
            setupEventListeners() {
                const events = ['mousedown', 'mousemove', 'mouseup', 'touchstart', 'touchmove', 'touchend'];
                
                events.forEach(event => {
                    this.canvas.addEventListener(event, (e) => {
                        e.preventDefault();
                        this.handleEvent(event, e);
                    });
                });
            }
            
            handleEvent(type, event) {
                const rect = this.canvas.getBoundingClientRect();
                let clientX, clientY;
                
                if (type.includes('touch')) {
                    if (event.touches && event.touches[0]) {
                        clientX = event.touches[0].clientX;
                        clientY = event.touches[0].clientY;
                    }
                } else {
                    clientX = event.clientX;
                    clientY = event.clientY;
                }
                
                const x = clientX - rect.left;
                const y = clientY - rect.top;
                
                switch (type) {
                    case 'mousedown':
                    case 'touchstart':
                        this.isDrawing = true;
                        this.points = [{x, y}];
                        this.ctx.beginPath();
                        this.ctx.moveTo(x, y);
                        break;
                        
                    case 'mousemove':
                    case 'touchmove':
                        if (this.isDrawing) {
                            this.points.push({x, y});
                            this.ctx.lineTo(x, y);
                            this.ctx.strokeStyle = this.options.penColor;
                            this.ctx.lineWidth = this.options.maxWidth;
                            this.ctx.lineCap = 'round';
                            this.ctx.stroke();
                        }
                        break;
                        
                    case 'mouseup':
                    case 'touchend':
                        this.isDrawing = false;
                        break;
                }
            }
        }

        let currentActiveField = null;
        window.currentActiveField = null; // Make it globally accessible
        let currentActivePage = null;
        let signaturePad = null;
        let savedSignatures = {};
        let useFallback = false;
        let userSavedSignatureData = null; // Store user's signature for reuse
        let currentSignatureMode = 'draw'; // 'draw' or 'upload'
        let uploadedSignatureData = null; // Store uploaded signature image data

        // Debug function
        function toggleDebug() {
            const debugInfo = document.getElementById('debug-info');
            const debugContent = document.getElementById('debug-content');
            
            if (debugInfo.style.display === 'none') {
                debugInfo.style.display = 'block';
                updateDebugInfo();
            } else {
                debugInfo.style.display = 'none';
            }
        }

        function updateDebugInfo() {
            const debugContent = document.getElementById('debug-content');
            
            let debugText = `
                <div>Active Page: ${currentActivePage}</div>
                <div>Active Field: ${currentActiveField}</div>
                <div>SignaturePad: ${signaturePad ? 'Initialized' : 'Not Initialized'}</div>
                <div>Using Fallback: ${useFallback ? 'Yes' : 'No'}</div>
                <div>Pad Empty: ${signaturePad ? signaturePad.isEmpty() : 'N/A'}</div>
                <div>Canvas Size: ${signaturePad ? signaturePad.canvas.width + 'x' + signaturePad.canvas.height : 'N/A'}</div>
                <div>Touch Events: ${('ontouchstart' in window) ? 'Supported' : 'Not Supported'}</div>
                <div>Mouse Events: ${('onmousedown' in window) ? 'Supported' : 'Not Supported'}</div>
            `;
            
            debugContent.innerHTML = debugText;
        }

        // Tab switching function
        function switchSignatureTab(mode, buttonElement) {
            currentSignatureMode = mode;
            
            // Update tab buttons
            document.querySelectorAll('.signature-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            if (buttonElement) {
                buttonElement.classList.add('active');
            }
            
            // Update tab content
            document.querySelectorAll('.signature-tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            if (mode === 'draw') {
                document.getElementById('draw-tab').classList.add('active');
                // Initialize signature pad if not already done
                if (!signaturePad) {
                    signaturePad = initializeSignaturePad();
                }
            } else if (mode === 'upload') {
                document.getElementById('upload-tab').classList.add('active');
            }
        }
        
        // Make functions globally accessible
        window.switchSignatureTab = switchSignatureTab;
        window.handleSignatureUpload = handleSignatureUpload;

        // Handle signature file upload
        function handleSignatureUpload(event) {
            const file = event.target.files[0];
            if (!file) return;
            
            // Validate file type
            const validTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/svg+xml'];
            if (!validTypes.includes(file.type)) {
                alert('Please upload a valid image file (PNG, JPG, or SVG)');
                event.target.value = '';
                return;
            }
            
            // Validate file size (max 5MB)
            if (file.size > 5 * 1024 * 1024) {
                alert('File size must be less than 5MB');
                event.target.value = '';
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                uploadedSignatureData = e.target.result;
                const preview = document.getElementById('upload-signature-preview');
                preview.src = uploadedSignatureData;
                preview.classList.add('active');
            };
            reader.onerror = function() {
                alert('Error reading file. Please try again.');
                event.target.value = '';
            };
            reader.readAsDataURL(file);
        }

        function initializeSignaturePad() {
            const canvas = document.getElementById('signature-pad');
            
            if (!canvas) {
                console.error('Canvas not found');
                return null;
            }

            // Set canvas size
            canvas.width = 400;
            canvas.height = 200;

            try {
                // Try to create the signature pad
                const pad = new SimpleSignaturePad(canvas, {
                    // backgroundColor: 'rgb(255, 255, 255)', // Remove background color for transparency
                    penColor: 'rgb(0, 0, 0)',
                    minWidth: 0.5,
                    maxWidth: 2.5
                });

                return pad;
            } catch (error) {
                console.error('Error initializing signature pad:', error);
                return null;
            }
        }


        function activateSignatureField(fieldId, page) {
            currentActiveField = fieldId;
            window.currentActiveField = fieldId; // Keep both in sync
            currentActivePage = page;

            // Reset to draw mode
            currentSignatureMode = 'draw';
            
            // Reset tabs
            document.querySelectorAll('.signature-tab').forEach((tab, index) => {
                if (index === 0) {
                    tab.classList.add('active');
                } else {
                    tab.classList.remove('active');
                }
            });
            
            // Reset tab content
            document.querySelectorAll('.signature-tab-content').forEach((content, index) => {
                if (index === 0) {
                    content.classList.add('active');
                } else {
                    content.classList.remove('active');
                }
            });

            // Show the modal
            const modal = document.getElementById('signature-modal');
            modal.style.display = 'block';

            // Show/hide Paste Signature button
            const pasteBtn = document.getElementById('paste-signature-btn');
            if (userSavedSignatureData) {
                pasteBtn.style.display = 'inline-block';
            } else {
                pasteBtn.style.display = 'none';
            }

            // Initialize signature pad if not already done
            if (!signaturePad) {
                try {
                    signaturePad = initializeSignaturePad();
                } catch (error) {
                    console.error('Error initializing signature pad:', error);
                }
            } else {
                // Clear the existing signature
                signaturePad.clear();
            }
            
            // Clear type signature
            document.getElementById('fallback-signature').value = '';
            
            // Clear upload signature
            uploadedSignatureData = null;
            document.getElementById('upload-signature-input').value = '';
            const preview = document.getElementById('upload-signature-preview');
            preview.src = '';
            preview.classList.remove('active');

            // Update debug info if visible
            if (document.getElementById('debug-info') && document.getElementById('debug-info').style.display !== 'none') {
                updateDebugInfo();
            }
        }

        function closeSignatureModal() {
            const modal = document.getElementById('signature-modal');
            modal.style.display = 'none';
            
            // Clear based on current mode
            if (currentSignatureMode === 'draw' && signaturePad) {
                signaturePad.clear();
            } else if (currentSignatureMode === 'upload') {
                uploadedSignatureData = null;
                document.getElementById('upload-signature-input').value = '';
                const preview = document.getElementById('upload-signature-preview');
                preview.src = '';
                preview.classList.remove('active');
            }
        }

        function clearSignature() {
            if (currentSignatureMode === 'draw' && signaturePad) {
                signaturePad.clear();
            } else if (currentSignatureMode === 'upload') {
                uploadedSignatureData = null;
                document.getElementById('upload-signature-input').value = '';
                const preview = document.getElementById('upload-signature-preview');
                preview.src = '';
                preview.classList.remove('active');
            }
        }

        function saveSignature() {
            let signatureData = '';
            
            if (currentSignatureMode === 'draw') {
                if (signaturePad && !signaturePad.isEmpty()) {
                    signatureData = signaturePad.toDataURL('image/png');
                } else {
                    alert('Please draw a signature first.');
                    return;
                }
            } else if (currentSignatureMode === 'upload') {
                if (uploadedSignatureData) {
                    signatureData = uploadedSignatureData;
                } else {
                    alert('Please upload a signature image first.');
                    return;
                }
            }
            
            if (!signatureData) {
                alert('Please provide a signature first.');
                return;
            }
            // Save the user's signature for reuse
            userSavedSignatureData = signatureData;
            const fieldElement = document.getElementById('signature-field-' + currentActiveField);
            const pdfImage = document.getElementById('pdf-image-' + currentActivePage);
            const fieldRect = fieldElement.getBoundingClientRect();
            const imageRect = pdfImage.getBoundingClientRect();
            // Key Fix: Use natural dimensions for accurate percents
            const naturalWidth = pdfImage.naturalWidth;
            const naturalHeight = pdfImage.naturalHeight;
            const displayWidth = pdfImage.clientWidth;
            const displayHeight = pdfImage.clientHeight;
            const scaleX = displayWidth / naturalWidth || 1;
            const scaleY = displayHeight / naturalHeight || 1;
            // Unscale to natural
            const relativeX = (fieldRect.left - imageRect.left) / scaleX;
            const relativeY = (fieldRect.top - imageRect.top) / scaleY;
            const fieldWidthNatural = fieldElement.offsetWidth / scaleX;
            const fieldHeightNatural = fieldElement.offsetHeight / scaleY;
            // Percents relative to full PDF
            const xPercent = relativeX / naturalWidth;
            const yPercent = relativeY / naturalHeight;
            const wPercent = fieldWidthNatural / naturalWidth;
            const hPercent = fieldHeightNatural / naturalHeight;
            // Log all relevant info for debugging
            console.log('[Signature Debug] Canvas size:', {width: signaturePad ? signaturePad.canvas.width : 'N/A', height: signaturePad ? signaturePad.canvas.height : 'N/A'});
            console.log('[Signature Debug] Field position (px):', {left: fieldRect.left, top: fieldRect.top, width: fieldElement.offsetWidth, height: fieldElement.offsetHeight});
            console.log('[Signature Debug] PDF image natural size:', {width: naturalWidth, height: naturalHeight});
            console.log('[Signature Debug] PDF image display size:', {width: displayWidth, height: displayHeight});
            console.log('[Signature Debug] Scale factors:', {scaleX, scaleY});
            console.log('[Signature Debug] Relative position (natural px):', {x: relativeX, y: relativeY, width: fieldWidthNatural, height: fieldHeightNatural});
            console.log('[Signature Debug] Percentages:', {xPercent, yPercent, wPercent, hPercent});
            console.log('[Signature Debug] Page:', currentActivePage, 'Field ID:', currentActiveField);
            // Store signature data and position as percentages
            savedSignatures[currentActiveField] = {
                data: signatureData,
                x_percent: xPercent,
                y_percent: yPercent,
                w_percent: wPercent,
                h_percent: hPercent,
                page: currentActivePage
            };
            fieldElement.classList.add('signed');
            const tag = fieldElement.querySelector('.ds-tag');
            if (tag) tag.textContent = '✓ Signed';
            displaySignatureOnDocument(currentActiveField, signatureData, fieldElement);
            closeSignatureModal();
        }

        // Paste signature to current field
        function pasteSignature() {
            if (!userSavedSignatureData) return;
            const fieldElement = document.getElementById('signature-field-' + currentActiveField);
            const pdfImage = document.getElementById('pdf-image-' + currentActivePage);
            const fieldRect = fieldElement.getBoundingClientRect();
            const imageRect = pdfImage.getBoundingClientRect();
            // Key Fix: Use natural dimensions for accurate percents
            const naturalWidth = pdfImage.naturalWidth;
            const naturalHeight = pdfImage.naturalHeight;
            const displayWidth = pdfImage.clientWidth;
            const displayHeight = pdfImage.clientHeight;
            const scaleX = displayWidth / naturalWidth || 1;
            const scaleY = displayHeight / naturalHeight || 1;
            // Unscale to natural
            const relativeX = (fieldRect.left - imageRect.left) / scaleX;
            const relativeY = (fieldRect.top - imageRect.top) / scaleY;
            const fieldWidthNatural = fieldElement.offsetWidth / scaleX;
            const fieldHeightNatural = fieldElement.offsetHeight / scaleY;
            // Percents relative to full PDF
            const xPercent = relativeX / naturalWidth;
            const yPercent = relativeY / naturalHeight;
            const wPercent = fieldWidthNatural / naturalWidth;
            const hPercent = fieldHeightNatural / naturalHeight;
            savedSignatures[currentActiveField] = {
                data: userSavedSignatureData,
                x_percent: xPercent,
                y_percent: yPercent,
                w_percent: wPercent,
                h_percent: hPercent,
                page: currentActivePage
            };
            fieldElement.classList.add('signed');
            const tag = fieldElement.querySelector('.ds-tag');
            if (tag) tag.textContent = '✓ Signed';
            displaySignatureOnDocument(currentActiveField, userSavedSignatureData, fieldElement);
            closeSignatureModal();
        }

        function displaySignatureOnDocument(fieldId, signatureData, fieldElement) {
            // Create signature image element
            const signatureImg = document.createElement('img');
            signatureImg.src = signatureData;
            signatureImg.style.cssText = `
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                object-fit: contain;
                pointer-events: none;
                z-index: 10;
            `;
            
            // Add the signature image to the field
            fieldElement.appendChild(signatureImg);
            
            // Also create a signature overlay on the PDF image for better visibility
            const pdfImage = document.getElementById('pdf-image-' + currentActivePage);
            const pdfContainer = pdfImage.parentElement;
            
            // Check if signature overlay already exists for this field
            const existingOverlay = document.getElementById('signature-overlay-' + fieldId);
            if (existingOverlay) {
                existingOverlay.remove();
            }
            
            const signatureOverlay = document.createElement('img');
            signatureOverlay.id = 'signature-overlay-' + fieldId;
            signatureOverlay.src = signatureData;
            signatureOverlay.style.cssText = `
                position: absolute;
                left: ${fieldElement.style.left};
                top: ${fieldElement.style.top};
                width: ${fieldElement.style.width};
                height: ${fieldElement.style.height};
                object-fit: contain;
                pointer-events: none;
                z-index: 5;
                opacity: 0.9;
            `;
            
            pdfContainer.appendChild(signatureOverlay);
        }

        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modal = document.getElementById('signature-modal');
            if (event.target === modal) {
                closeSignatureModal();
            }
        }

        // Handle form submission
        document.querySelector('form').addEventListener('submit', function (e) {
            for (let i = 1; i <= {{ $pdfPages }}; i++) {
                var pageSignatures = {};
                var pagePositions = {};
                Object.keys(savedSignatures).forEach(function(fieldId) {
                    var signature = savedSignatures[fieldId];
                    if (signature.page === i) {
                        pageSignatures[fieldId] = signature.data;
                        pagePositions[fieldId] = {
                            x_percent: signature.x_percent,
                            y_percent: signature.y_percent,
                            w_percent: signature.w_percent,
                            h_percent: signature.h_percent
                        };
                    }
                });
                document.getElementById('signature-input-' + i).value = JSON.stringify(pageSignatures);
                document.getElementById('signature-position-' + i).value = JSON.stringify(pagePositions);
            }
        });

        // Expose activateSignatureField to global scope for inline onclick
        window.activateSignatureField = activateSignatureField;

        // Responsive positioning for signature fields
        function positionSignatureFields(page) {
            const img = document.getElementById('pdf-image-' + page);
            if (!img) return;

            const naturalWidth = img.naturalWidth;
            const naturalHeight = img.naturalHeight;
            const displayWidth = img.clientWidth;
            const displayHeight = img.clientHeight;

            // Consistency check log
            console.log(`Page ${page} Field Positions:`, {
                natural: {w: naturalWidth, h: naturalHeight},
                display: {w: displayWidth, h: displayHeight},
                aspect_ratio_match: (displayWidth / displayHeight).toFixed(4) === (naturalWidth / naturalHeight).toFixed(4)
            });

            document.querySelectorAll('.signature-field[data-page="' + page + '"]').forEach(field => {
                const xPercent = parseFloat(field.getAttribute('data-x-percent')) || 0;
                const yPercent = parseFloat(field.getAttribute('data-y-percent')) || 0;
                const wPercent = parseFloat(field.getAttribute('data-w-percent')) || 0;
                const hPercent = parseFloat(field.getAttribute('data-h-percent')) || 0;

                // Scale positions to display
                field.style.left = (xPercent * displayWidth) + 'px';
                field.style.top = (yPercent * displayHeight) + 'px';
                field.style.width = (wPercent * displayWidth) + 'px';
                field.style.height = (hPercent * displayHeight) + 'px';
            });
        }


        // Page navigation functions
        function scrollToPage(pageNumber) {
            const pageElement = document.querySelector(`[data-page="${pageNumber}"]`).closest('.bg-gray-50');
            if (pageElement) {
                pageElement.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'start' 
                });
                
                // Highlight the page temporarily
                pageElement.style.boxShadow = '0 0 20px rgba(59, 130, 246, 0.5)';
                setTimeout(() => {
                    pageElement.style.boxShadow = '';
                }, 2000);
            }
        }

        // Check if all required signature fields are signed
        function validateAllSignatures() {
            const totalFields = document.querySelectorAll('.signature-field').length;
            const signedFields = Object.keys(savedSignatures).length;
            
            if (signedFields < totalFields) {
                alert(`Please sign all signature fields. ${signedFields} of ${totalFields} fields are signed.`);
                return false;
            }
            return true;
        }

        // Enhanced form submission with multipage validation
        document.querySelector('form').addEventListener('submit', function (e) {
            if (!validateAllSignatures()) {
                e.preventDefault();
                return false;
            }
            
            // Continue with existing form submission logic...
        });

        // Guided Signing Workflow
        let allSignatureFields = [];
        let signedFields = new Set();

        document.addEventListener('DOMContentLoaded', function () {
            // Initialize signature pad
            signaturePad = initializeSignaturePad();
            
            if (signaturePad) {
                // Signature pad initialized successfully
            } else if (useFallback) {
                // Using fallback signature method
            } else {
                console.error('Failed to initialize signature pad');
            }

            // Initialize signature field positioning
            for (let i = 1; i <= {{ $pdfPages }}; i++) {
                const img = document.getElementById('pdf-image-' + i);
                if (img) {
                    img.onload = () => positionSignatureFields(i);
                    // If image is already loaded (from cache)
                    if (img.complete) positionSignatureFields(i);
                }
            }
            window.addEventListener('resize', function () {
                for (let i = 1; i <= {{ $pdfPages }}; i++) {
                    positionSignatureFields(i);
                }
            });

            // Initialize guided signing workflow
            allSignatureFields = Array.from(document.querySelectorAll('.signature-field')).map(el => ({
                element: el,
                id: el.id.replace('signature-field-', ''),
                page: parseInt(el.getAttribute('data-page'))
            }));
            
            document.getElementById('total-count').textContent = allSignatureFields.length;
            
            // Button handlers
            document.getElementById('start-signing').addEventListener('click', startGuidedSigning);
            document.getElementById('next-field').addEventListener('click', goToNextUnsigned);
            document.getElementById('finish-signing').addEventListener('click', submitAllSignatures);
        });

        function startGuidedSigning() {
            document.getElementById('start-signing').style.display = 'none';
            document.getElementById('next-field').style.display = 'flex';
            goToNextUnsigned();
        }

        function goToNextUnsigned() {
            const nextField = allSignatureFields.find(f => !signedFields.has(f.id));
            if (!nextField) {
                alert('All fields signed! Ready to submit.');
                return;
            }
            
            // Scroll to page
            scrollToPage(nextField.page);
            
            // Highlight and open
            setTimeout(() => {
                nextField.element.classList.add('active-highlight');
                setTimeout(() => nextField.element.classList.remove('active-highlight'), 3000);
                nextField.element.click();
            }, 300);
        }

        function markFieldAsSigned(fieldId) {
            signedFields.add(fieldId);
            updateProgress();
            
            // Update visual
            const field = document.getElementById('signature-field-' + fieldId);
            if (field) {
                field.classList.add('signed');
                const tag = field.querySelector('.ds-tag');
                if (tag) tag.textContent = '✓ Signed';
            }
        }

        function updateProgress() {
            document.getElementById('signed-count').textContent = signedFields.size;
            
            // Show finish button when all signed
            if (signedFields.size >= allSignatureFields.length) {
                document.getElementById('next-field').style.display = 'none';
                document.getElementById('finish-signing').style.display = 'flex';
            }
        }

        function submitAllSignatures() {
            if (signedFields.size < allSignatureFields.length) {
                alert(`Please sign all fields.\n${signedFields.size} of ${allSignatureFields.length} signed.`);
                return;
            }
            
            // Trigger form submission
            populateHiddenFields();
            document.querySelector('form[action*="submitSignatures"]').submit();
        }

        // Hook into existing saveSignature
        const originalSaveSignature = window.saveSignature;
        window.saveSignature = function() {
            originalSaveSignature.apply(this, arguments);
            
            if (window.currentActiveField) {
                markFieldAsSigned(window.currentActiveField);
                
                // Auto-advance after 800ms
                setTimeout(goToNextUnsigned, 800);
            }
        };
    </script>
</body>
</html>