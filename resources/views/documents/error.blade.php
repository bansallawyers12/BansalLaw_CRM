<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Error - Document Signing' }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            width: 100%;
            padding: 40px 30px;
            text-align: center;
        }

        .error-icon {
            width: 70px;
            height: 70px;
            margin: 0 auto 20px;
            background: #dc3545;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            color: white;
        }

        h1 {
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 15px;
        }

        .message {
            font-size: 16px;
            color: #6c757d;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .info-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 25px;
            text-align: left;
            border-left: 4px solid #dc3545;
        }

        .info-box p {
            font-size: 14px;
            color: #495057;
            margin-bottom: 8px;
        }

        .info-box p:last-child {
            margin-bottom: 0;
        }

        .help-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 25px;
            text-align: left;
        }

        .help-box p {
            font-size: 14px;
            color: #856404;
            margin-bottom: 8px;
        }

        .help-box p:last-child {
            margin-bottom: 0;
        }

        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
            font-size: 13px;
            color: #6c757d;
        }

        .footer p {
            margin-bottom: 8px;
        }

        @media (max-width: 640px) {
            .container {
                padding: 30px 20px;
            }

            h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Error Icon -->
        <div class="error-icon">
            ✕
        </div>

        <!-- Main Message -->
        <h1>{{ $title ?? 'Error' }}</h1>
        <p class="message">{{ $message ?? 'An error occurred while processing your request.' }}</p>

        <!-- Document Info -->
        @if(isset($document) && $document)
        <div class="info-box">
            <p><strong>Document ID:</strong> #{{ $document->id }}</p>
            @if($document->display_title)
            <p><strong>Document:</strong> {{ $document->display_title }}</p>
            @endif
        </div>
        @endif

        <!-- Help Information -->
        <div class="help-box">
            <p><strong>⚠️ What should I do?</strong></p>
            <p>Please contact the document sender for assistance. They can help you resolve this issue or provide you with a new signing link if needed.</p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong>Need Help?</strong></p>
            <p>Email: <a href="mailto:info@bansalimmigration.com.au" style="color: #dc3545; text-decoration: none;">info@bansalimmigration.com.au</a></p>
            <p>Phone: <a href="tel:+61292673945" style="color: #dc3545; text-decoration: none;">+61 2 9267 3945</a></p>
            <p style="margin-top: 15px; font-size: 12px;">
                © {{ date('Y') }} Bansal Migration Management. All rights reserved.
            </p>
        </div>
    </div>
</body>
</html>

