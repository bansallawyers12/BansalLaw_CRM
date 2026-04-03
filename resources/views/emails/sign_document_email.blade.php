<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Signature Request</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            background-color: #f5f5f5;
            color: #1a1a1a;
        }
        
        .email-wrapper {
            width: 100%;
            background-color: #f5f5f5;
            padding: 20px 0;
        }
        
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border: 1px solid #dddddd;
        }
        
        .email-header {
            background-color: #2563eb;
            color: #ffffff;
            padding: 30px 20px;
            text-align: center;
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
        
        .greeting {
            font-size: 16px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 20px;
        }
        
        .message {
            color: #1a1a1a;
            margin-bottom: 20px;
            line-height: 1.6;
            font-size: 15px;
        }
        
        .signing-link-box {
            background-color: #f8f9fa;
            border: 2px dashed #dee2e6;
            padding: 20px;
            margin: 30px 0;
            text-align: center;
        }
        
        .signing-link {
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            word-break: break-all;
            display: inline-block;
            padding: 10px 15px;
            background-color: #ffffff;
            border: 1px solid #dee2e6;
        }
        
        .cta-button {
            display: inline-block;
            background-color: #2563eb;
            color: #ffffff;
            text-decoration: none;
            padding: 15px 40px;
            border: 2px solid #1e40af;
            font-weight: 700;
            font-size: 16px;
        }
        
        .signature-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
        }
        
        .signature-text {
            color: #1a1a1a;
            font-size: 15px;
            margin-bottom: 5px;
        }
        
        .signature-name {
            color: #1a1a1a;
            font-weight: 600;
            font-size: 15px;
        }
        
        .email-footer {
            background-color: #f8f9fa;
            padding: 25px 20px;
            text-align: center;
            border-top: 1px solid #dee2e6;
        }
        
        .footer-brand {
            font-size: 16px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 15px;
        }
        
        .footer-contact {
            margin: 8px 0;
            color: #1a1a1a;
            font-size: 13px;
        }
        
        .footer-contact a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-container">
            <div class="email-header">
                <img src="{{URL::to('/public/img/logo.png')}}" alt="Bansal Migration" style="max-width: 200px; height: auto; margin-bottom: 15px;" />
                <h1>Document Signature Request</h1>
            </div>
            
            <div class="email-body">
                <p class="greeting">Hi {{ $firstName }},</p>
                
                <p class="message">
                    We have forwarded a document for signature. Please click the button below or use the link provided to access and sign the document.
                </p>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="{{ $signingUrl }}" target="_blank" rel="noopener noreferrer" class="cta-button" style="display: inline-block; background-color: #2563eb; color: #ffffff; text-decoration: none; padding: 15px 40px; border: 2px solid #1e40af; font-weight: 700; font-size: 16px;">
                        Sign Document Now
                    </a>
                </div>
                
                <div class="signing-link-box">
                    <div style="font-size: 13px; color: #1a1a1a; margin-bottom: 10px;">Or copy this link:</div>
                    <a href="{{ $signingUrl }}" target="_blank" rel="noopener noreferrer" class="signing-link" style="color:#2563eb;text-decoration:underline;word-break:break-all;">{{ $signingUrl }}</a>
                </div>
                
                <div class="signature-section">
                    <p class="signature-text"><strong>Regards,</strong></p>
                    <p class="signature-name">Bansal Migration Team</p>
                </div>
            </div>
            
            <div class="email-footer">
                <div class="footer-brand">Bansal Migration</div>
                <div class="footer-contact">Immigration & Visa Services</div>
                <div class="footer-contact">
                    Email: <a href="mailto:info@bansalimmigration.com.au">info@bansalimmigration.com.au</a>
                </div>
                <div class="footer-contact">
                    Website: <a href="https://www.bansalimmigration.com.au" target="_blank">www.bansalimmigration.com.au</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
