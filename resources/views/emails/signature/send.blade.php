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
            margin: 0;
            font-size: 24px;
            font-weight: 700;
            color: #ffffff;
        }
        
        .header-subtitle {
            margin-top: 8px;
            font-size: 14px;
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
        
        .document-info {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-left: 4px solid #2563eb;
            padding: 20px;
            margin: 25px 0;
        }
        
        .document-info h3 {
            margin: 0 0 15px 0;
            color: #1a1a1a;
            font-size: 16px;
            font-weight: 700;
        }
        
        .document-info-item {
            margin: 10px 0;
            color: #1a1a1a;
            font-size: 14px;
        }
        
        .document-info-item strong {
            color: #1a1a1a;
            font-weight: 700;
        }
        
        .button-container {
            text-align: center;
            margin: 30px 0;
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
        
        .note {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 25px 0;
            font-size: 14px;
            color: #1a1a1a;
        }
        
        .note strong {
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
        
        .footer-brand-name {
            font-size: 16px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 5px;
        }
        
        .footer-brand-tagline {
            font-size: 13px;
            color: #1a1a1a;
            margin-bottom: 15px;
        }
        
        .footer-contact-item {
            margin: 8px 0;
            color: #1a1a1a;
            font-size: 13px;
        }
        
        .footer-contact-item a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
        }
        
        .footer-disclaimer {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #dee2e6;
            font-size: 11px;
            color: #666666;
        }
        
        .email-signature-block {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-container">
            <!-- Header -->
            <div class="email-header">
                <img src="{{URL::to('/public/img/logo.png')}}" alt="Bansal Migration" style="max-width: 200px; height: auto; margin-bottom: 15px;" />
                <h1>Document Signature Request</h1>
                <div class="header-subtitle">Bansal Migration Immigration & Visa Services</div>
            </div>

            <!-- Body -->
            <div class="email-body">
                <p class="greeting">Dear {{ $signerName }},</p>
                
                <p class="message">
                    {{ $emailMessage ?? 'Please review and sign the attached document.' }}
                </p>

                <!-- Document Info Card -->
                <div class="document-info">
                    <h3>Document Details</h3>
                    <div class="document-info-item">
                        <strong>Document:</strong> {{ $documentTitle }}
                    </div>
                    <div class="document-info-item">
                        <strong>Type:</strong> {{ ucfirst($documentType ?? 'General') }}
                    </div>
                    @if(isset($dueDate) && $dueDate)
                    <div class="document-info-item">
                        <strong>Due Date:</strong> {{ $dueDate }}
                    </div>
                    @endif
                </div>

                <!-- Call to Action Button -->
                <div class="button-container">
                    <a href="{{ $signingUrl }}" target="_blank" rel="noopener noreferrer" class="cta-button" style="display: inline-block; background-color: #2563eb; color: #ffffff; text-decoration: none; padding: 15px 40px; border: 2px solid #1e40af; font-weight: 700; font-size: 16px;">
                        Review & Sign Document
                    </a>
                </div>

                <!-- Important Note -->
                <div class="note">
                    <strong>Important:</strong> This link is unique to you and should not be shared. 
                    It will expire once the document is signed or after the due date.
                </div>

                <p class="message">
                    If you have any questions or need assistance, please don't hesitate to contact us.
                </p>

                <p class="message">
                    Thank you for your prompt attention to this matter.
                </p>

                <!-- Signature -->
                <div class="signature-section">
                    <p class="signature-text"><strong>Regards,</strong></p>
                    <p class="signature-name">Bansal Migration Team</p>
                </div>

                <!-- Email Signature Block -->
                @if(isset($emailSignature) && !empty($emailSignature))
                <div class="email-signature-block">
                    {!! $emailSignature !!}
                </div>
                @endif
            </div>

            <!-- Footer -->
            <div class="email-footer">
                <div class="footer-brand-name">Bansal Migration</div>
                <div class="footer-brand-tagline">Immigration & Visa Services</div>
                
                <div class="footer-contact-item">
                    Email: <a href="mailto:info@bansalimmigration.com.au">info@bansalimmigration.com.au</a>
                </div>
                <div class="footer-contact-item">
                    Website: <a href="https://www.bansalimmigration.com.au" target="_blank">www.bansalimmigration.com.au</a>
                </div>
                
                <div class="footer-disclaimer">
                    This is an automated message. Please do not reply to this email.
                </div>
                <p style="margin-top: 15px; font-size: 13px;">
                    Consumer guide: <a href="https://www.mara.gov.au/get-help-visa-subsite/FIles/consumer_guide_english.pdf" style="color: #2563eb; text-decoration: none; font-weight: 600;">https://www.mara.gov.au/get-help-visa-subsite/FIles/consumer_guide_english.pdf</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
