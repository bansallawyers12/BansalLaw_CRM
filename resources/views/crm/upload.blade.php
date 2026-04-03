<!DOCTYPE html>
<html>
<head>
    <title>DOC/DOCX to PDF Converter - Admin</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 2px dashed #ddd;
            border-radius: 4px;
            background-color: #fafafa;
        }
        button {
            background-color: #0078d4;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }
        button:hover {
            background-color: #106ebe;
        }
        .alert {
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .test-links {
            margin-top: 30px;
            text-align: center;
        }
        .test-links a {
            color: #0078d4;
            text-decoration: none;
            margin: 0 10px;
        }
        .test-links a:hover {
            text-decoration: underline;
        }
        .python-info {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
        }
        .python-info h3 {
            color: #0078d4;
            margin-top: 0;
        }
        .admin-header {
            background-color: #343a40;
            color: white;
            padding: 15px;
            margin: -30px -30px 30px -30px;
            border-radius: 8px 8px 0 0;
        }
        .admin-header h2 {
            margin: 0;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="admin-header">
            <h2>Admin Panel - DOC/DOCX to PDF Converter</h2>
        </div>
        
        <!-- Security Status Warning -->
        @if(isset($isSecure) && !$isSecure)
            <div class="alert alert-warning" style="margin-bottom: 20px;">
                <strong>⚠️ Security Warning:</strong> You are accessing this application over an insecure connection (HTTP). 
                While this is normal for local development, form submissions may not be fully secure. 
                For production use, ensure HTTPS is enabled.
            </div>
        @elseif(isset($isSecure) && $isSecure)
            <div class="alert alert-success" style="margin-bottom: 20px;">
                <strong>✅ Secure Connection:</strong> You are accessing this application over a secure connection (HTTPS).
            </div>
        @endif
        
        <div class="python-info">
            <h3>Python Conversion Service</h3>
            <p>This application uses a Python-based conversion service to convert your documents to PDF with high quality and accuracy.</p>
        </div>
        
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        
        @if(session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
        @endif
        
        @if(session('warning'))
            <div class="alert alert-warning">{{ session('warning') }}</div>
        @endif

        <form action="{{ route('doc-to-pdf.convert') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label for="document">Select DOC or DOCX File:</label>
                <input type="file" id="document" name="document" accept=".doc,.docx" required>
                <small style="color: #666; display: block; margin-top: 5px;">
                    Maximum file size: 50MB. Supported formats: .doc, .docx
                </small>
            </div>
            
            <button type="submit">Convert to PDF</button>
            <small style="color: #666; display: block; margin-top: 10px; text-align: center;">
                This uses local Python processing to convert your document to PDF.
            </small>
        </form>
        
        <div class="test-links">
            <a href="{{ route('doc-to-pdf.test') }}" target="_blank">Test Service Status</a>
            <a href="{{ route('doc-to-pdf.test-python') }}" target="_blank">Test Python Conversion</a>
            <a href="{{ route('doc-to-pdf.debug') }}" target="_blank">Debug Configuration</a>
        </div>
    </div>
</body>
</html>