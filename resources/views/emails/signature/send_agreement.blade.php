<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agreement Signature Request</title>
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
            margin: 20px auto;
            background: #ffffff;
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
        .custom-message {
            background-color: #f0fdf4;
            border: 1px solid #10b981;
            border-left: 4px solid #10b981;
            padding: 15px;
            margin: 20px 0;
        }
        .document-info {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-left: 4px solid #059669;
            padding: 20px;
            margin: 25px 0;
        }
        .document-info h3 {
            margin: 0 0 15px 0;
            color: #1a1a1a;
            font-size: 16px;
            font-weight: 700;
        }
        .document-info p {
            margin: 8px 0;
            color: #1a1a1a;
            font-size: 14px;
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
            text-align: center;
        }
        .button-container {
            text-align: center;
            margin: 30px 0;
        }
        .note {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            font-size: 14px;
            color: #1a1a1a;
        }
        .attachments {
            background-color: #e7f3ff;
            border: 1px solid #3b82f6;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            margin: 20px 0;
        }
        .attachments h4 {
            margin: 0 0 10px 0;
            color: #1a1a1a;
            font-size: 14px;
            font-weight: 700;
        }
        .email-footer {
            background-color: #f8f9fa;
            padding: 25px 20px;
            text-align: center;
            border-top: 1px solid #dee2e6;
        }
        .email-footer p {
            margin: 5px 0;
            color: #1a1a1a;
            font-size: 13px;
        }
        .email-footer a {
            color: #059669;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="email-header">
            <img src="{{URL::to('/public/img/logo.png')}}" alt="Bansal Migration" style="max-width: 200px; height: auto; margin-bottom: 15px;" />
            <h1>Agreement Signature Request</h1>
        </div>

        <!-- Body -->
        <div class="email-body">
            <p class="greeting">Dear {{ $signerName }},</p>
            
            <!-- Custom Message -->
            @if(isset($emailMessage) && !empty($emailMessage))
            <div class="custom-message">
                {!! nl2br(e($emailMessage)) !!}
            </div>
            @else
            <p class="message">
                We have prepared an important agreement document that requires your review and signature.
                Please take a moment to carefully review the terms and conditions outlined in the document.
            </p>
            @endif

            <!-- Document Info -->
            <div class="document-info">
                <h3>Agreement Details</h3>
                <p><strong>Document:</strong> {{ $documentTitle }}</p>
                <p><strong>Type:</strong> Cost Agreement</p>
                @if($dueDate)
                    <p><strong>Due Date:</strong> {{ $dueDate }}</p>
                @endif
            </div>

            <!-- Attachments Notice (if any) -->
            <div class="attachments">
                <h4>Additional Information</h4>
                <p style="margin: 0; color: #1a1a1a; font-size: 14px;">This email may include additional documents and information related to your matter. 
                Please review all attached materials carefully before signing.</p>
            </div>

            <!-- Call to Action -->
            <div class="button-container">
                <a href="{{ $signingUrl }}" target="_blank" rel="noopener noreferrer" class="cta-button" style="display: inline-block; background-color: #059669; color: #ffffff; text-decoration: none; padding: 15px 40px; border: 2px solid #047857; font-weight: 700; font-size: 16px;">
                    Review & Sign Agreement
                </a>
            </div>

            <!-- Legal Note -->
            <div class="note">
                <strong>Important Legal Notice:</strong> By signing this agreement, you acknowledge that you have 
                read, understood, and agree to the terms and conditions outlined in the document. This is a legally 
                binding agreement. Please contact us if you have any questions before signing.
            </div>

            <p class="message">
                If you have any questions about this agreement or need clarification on any terms, 
                please don't hesitate to contact our office before proceeding with the signature.
            </p>

            <p class="message" style="margin-top: 30px;">
                <strong>Warm regards,</strong><br>
                Bansal Migration Team<br>
                <em>Your trusted immigration partner</em>
            </p>

            @if(isset($emailSignature) && !empty($emailSignature))
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6;">
                    {!! $emailSignature !!}
                </div>
            @endif
        </div>

        <!-- Footer -->
        <div class="email-footer">
            <p><strong>Bansal Migration</strong></p>
            <p>Immigration & Visa Services</p>
            <p>Email: <a href="mailto:info@bansalimmigration.com.au">info@bansalimmigration.com.au</a></p>
            <p>Phone: <a href="tel:+61292673945">+61 2 9267 3945</a></p>
            <p>Website: <a href="https://www.bansalimmigration.com.au">www.bansalimmigration.com.au</a></p>
            
            <p style="margin-top: 15px; font-size: 11px; color: #666666;">
                This is an automated message. Please do not reply to this email.
            </p>
            <p style="margin-top: 15px; font-size: 13px;">
                Consumer guide: <a href="https://www.mara.gov.au/get-help-visa-subsite/FIles/consumer_guide_english.pdf" style="color: #059669; text-decoration: none; font-weight: 600;">https://www.mara.gov.au/get-help-visa-subsite/FIles/consumer_guide_english.pdf</a>
            </p>
        </div>
    </div>
</body>
</html>
