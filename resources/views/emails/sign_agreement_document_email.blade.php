<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agreement Document Signature Request</title>
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
            background-color: #059669;
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
        
        .custom-message-box {
            background-color: #f0fdf4;
            border: 1px solid #10b981;
            border-left: 4px solid #10b981;
            padding: 20px;
            margin: 25px 0;
        }
        
        .custom-message-box p {
            margin: 0;
            color: #1a1a1a;
            line-height: 1.6;
            font-size: 15px;
        }
        
        .cta-button {
            display: inline-block;
            background-color: #059669;
            color: #ffffff;
            text-decoration: none;
            padding: 15px 40px;
            border: 2px solid #047857;
            font-weight: 700;
            font-size: 16px;
        }
        
        .attachments-notice {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 15px;
            margin: 25px 0;
            font-size: 14px;
            color: #1a1a1a;
        }
        
        .attachments-notice strong {
            color: #1a1a1a;
            font-weight: 700;
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
            color: #059669;
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
                <h1>Agreement Signature Request</h1>
            </div>
            
            <div class="email-body">
                <p class="greeting">Dear {{ $firstName }},</p>
                
                @if(!empty($emailmessage))
                <div class="custom-message-box">
                    {!! nl2br(e($emailmessage)) !!}
                </div>
                @else
                <p class="message">
                    We have prepared an important agreement document that requires your review and signature.
                    Please take a moment to carefully review the terms and conditions outlined in the document.
                </p>
                @endif
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="{{ $signingUrl }}" target="_blank" rel="noopener noreferrer" class="cta-button" style="display: inline-block; background-color: #059669; color: #ffffff; text-decoration: none; padding: 15px 40px; border: 2px solid #047857; font-weight: 700; font-size: 16px;">
                        Review & Sign Agreement
                    </a>
                </div>
                
                <div class="attachments-notice">
                    <strong>Note:</strong> This email may contain important attachments related to your agreement. 
                    Please review all documents before signing.
                </div>
                
                <div class="signature-section">
                    <p class="signature-text"><strong>Regards,</strong></p>
                    <p class="signature-name">Bansal Migration Team</p>
                </div>
                
                @if(isset($emailSignature) && !empty($emailSignature))
                <div style="margin-top: 30px; padding-top: 25px; border-top: 1px solid #dee2e6;">
                    {!! $emailSignature !!}
                </div>
                @endif
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
