<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
            color: #1a1a1a;
        }
        .email-container {
            max-width: 600px;
            margin: 40px auto;
            background: #ffffff;
            border: 1px solid #dddddd;
        }
        .email-header {
            background-color: #4f46e5;
            padding: 30px 20px;
            text-align: center;
            color: #ffffff;
        }
        .email-header h1 {
            margin: 10px 0 0 0;
            font-size: 24px;
            font-weight: 700;
            color: #ffffff;
        }
        .email-body {
            padding: 30px 25px;
        }
        .email-body p {
            margin-bottom: 20px;
            font-size: 15px;
            color: #1a1a1a;
            line-height: 1.6;
        }
        .email-highlight {
            color: #4f46e5;
            font-weight: 700;
        }
        .verify-button {
            display: inline-block;
            padding: 15px 40px;
            background-color: #4f46e5;
            color: #ffffff;
            text-decoration: none;
            border: 2px solid #4338ca;
            font-weight: 700;
            font-size: 16px;
            text-align: center;
        }
        .button-container {
            text-align: center;
            margin: 30px 0;
        }
        .alternative-link {
            margin-top: 30px;
            padding: 20px;
            background-color: #f8f9fa;
            border: 2px dashed #dee2e6;
            font-size: 13px;
            word-break: break-all;
        }
        .alternative-link p {
            margin: 0 0 10px 0;
            color: #1a1a1a;
            font-weight: 600;
        }
        .alternative-link a {
            color: #4f46e5;
            text-decoration: none;
            font-weight: 600;
        }
        .email-footer {
            padding: 25px 20px;
            background-color: #f8f9fa;
            text-align: center;
            font-size: 14px;
            color: #1a1a1a;
            border-top: 1px solid #dee2e6;
        }
        .warning-box {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-top: 30px;
        }
        .warning-box p {
            margin: 0;
            color: #1a1a1a;
            font-size: 14px;
        }
        .info-box {
            background-color: #e7f3ff;
            border: 1px solid #3b82f6;
            border-left: 4px solid #3b82f6;
            padding: 20px;
            margin: 25px 0;
        }
        .info-box p {
            margin: 0 0 12px 0;
            color: #1a1a1a;
            font-weight: 700;
            font-size: 15px;
        }
        .info-box ul {
            margin: 0;
            padding-left: 22px;
            color: #1a1a1a;
        }
        .info-box li {
            margin-bottom: 8px;
            font-size: 14px;
        }
        .info-box li strong {
            color: #1a1a1a;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <img src="{{ asset('img/logo.png') }}" alt="BANSAL Immigration" style="max-width: 200px; height: auto; margin-bottom: 15px;" />
            <p style="margin: 0; font-size: 14px; color: #ffffff; opacity: 0.9;">BANSAL Immigration</p>
            <h1>Verify Your Email Address</h1>
        </div>
        
        <div class="email-body">
            <p>Hello,</p>
            
            <p>Thank you for providing your email address <strong class="email-highlight">{{ $clientEmail->email }}</strong> to <strong>BANSAL Immigration</strong>.</p>
            
            <p>To complete your email verification and ensure we can communicate with you effectively, please click the button below:</p>
            
            <div class="button-container">
                <a href="{{ $verificationUrl }}" class="verify-button" style="display: inline-block; background-color: #4f46e5; color: #ffffff; text-decoration: none; padding: 15px 40px; border: 2px solid #4338ca; font-weight: 700; font-size: 16px;">
                    Verify My Email Address
                </a>
            </div>
            
            <div class="info-box">
                <p style="margin: 0;"><strong>Important:</strong></p>
                <ul style="margin: 10px 0 0 0; padding-left: 20px;">
                    <li>This link expires in <strong>{{ $expiresAt->diffForHumans() }}</strong> ({{ $expiresAt->format('M j, Y g:i A') }})</li>
                    <li>This link can only be used once</li>
                    <li>If you didn't request this verification, please ignore this email</li>
                </ul>
            </div>
            
            <div class="alternative-link">
                <p><strong>If the button doesn't work, copy and paste this link into your browser:</strong></p>
                <p><a href="{{ $verificationUrl }}">{{ $verificationUrl }}</a></p>
            </div>
            
            <div class="warning-box">
                <p>
                    <strong>Warning:</strong> Never share this link with anyone. Our staff will never ask you for this link.
                </p>
            </div>
        </div>
        
        <div class="email-footer">
            <p>This is an automated email from <strong>BANSAL Immigration</strong>.</p>
            <p>If you have any questions, please contact our office.</p>
            <p style="margin-top: 15px; font-size: 13px;">
                Consumer guide: <a href="https://www.mara.gov.au/get-help-visa-subsite/FIles/consumer_guide_english.pdf" style="color: #4f46e5; text-decoration: none; font-weight: 600;">https://www.mara.gov.au/get-help-visa-subsite/FIles/consumer_guide_english.pdf</a>
            </p>
            <p style="margin-top: 15px; font-size: 12px; color: #666666;">
                &copy; {{ date('Y') }} BANSAL Immigration. All rights reserved.
            </p>
        </div>
    </div>
</body>
</html>
