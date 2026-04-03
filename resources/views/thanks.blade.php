<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank You - Document Signed Successfully</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

        .success-icon {
            width: 70px;
            height: 70px;
            margin: 0 auto 20px;
            background: #28a745;
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
        }

        .info-box p {
            font-size: 14px;
            color: #495057;
            margin-bottom: 8px;
        }

        .info-box p:last-child {
            margin-bottom: 0;
        }

        .download-btn {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 14px 32px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            transition: transform 0.2s, box-shadow 0.2s;
            margin-bottom: 20px;
        }

        .download-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
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

            .download-btn {
                padding: 12px 24px;
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Success Icon -->
        <div class="success-icon">
            ✓
        </div>

        <!-- Main Message -->
        <h1>Thank You!</h1>
        <p class="message">{{ $message ?? 'Your document has been signed successfully.' }}</p>

        <!-- Document Info -->
        @if(isset($document) && $document)
        <div class="info-box">
            <p><strong>Document ID:</strong> #{{ $document->id }}</p>
            <p><strong>Signed At:</strong> {{ now()->format('F j, Y g:i A') }}</p>
        </div>
        @endif

        <!-- Email Confirmation -->
        <div class="info-box">
            <p><strong>📧 Confirmation Email</strong></p>
            <p>A confirmation email with your signed document has been sent to your email address.</p>
        </div>

        <!-- Download Button -->
        @if(isset($downloadUrl) && $downloadUrl)
        <a href="{{ $downloadUrl }}" class="download-btn" download>
            📥 Download Signed Document
        </a>
        @endif

        <!-- Footer -->
        <div class="footer">
            <p><strong>Need Help?</strong></p>
            <p>Contact us at: {{ config('mail.from.address', 'support@bansalmigration.com') }}</p>
            <p style="margin-top: 15px; font-size: 12px;">
                © {{ date('Y') }} Bansal Migration Management. All rights reserved.
            </p>
        </div>
    </div>
</body>
</html>

