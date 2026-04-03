<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Signature Reminder</title>
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
            background-color: #dc2626;
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
        .reminder-badge {
            display: inline-block;
            background-color: rgba(255, 255, 255, 0.2);
            padding: 5px 15px;
            border: 1px solid #ffffff;
            font-size: 12px;
            margin-top: 10px;
            font-weight: 600;
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
        .urgent-notice {
            background-color: #fee2e2;
            border: 2px solid #fca5a5;
            padding: 20px;
            margin: 25px 0;
            text-align: center;
        }
        .urgent-notice h3 {
            margin: 10px 0;
            color: #dc2626;
            font-size: 18px;
            font-weight: 700;
        }
        .urgent-notice p {
            margin: 5px 0;
            color: #1a1a1a;
            font-size: 14px;
        }
        .document-info {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-left: 4px solid #dc2626;
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
        .due-date-warning {
            color: #dc2626;
            font-weight: 700;
        }
        .cta-button {
            display: inline-block;
            background-color: #dc2626;
            color: #ffffff;
            text-decoration: none;
            padding: 15px 40px;
            border: 2px solid #b91c1c;
            font-weight: 700;
            font-size: 16px;
        }
        .button-container {
            text-align: center;
            margin: 30px 0;
        }
        .help-section {
            background-color: #e7f3ff;
            border: 1px solid #3b82f6;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            margin: 25px 0;
        }
        .help-section h4 {
            margin: 0 0 10px 0;
            color: #1a1a1a;
            font-size: 14px;
            font-weight: 700;
        }
        .help-section p {
            margin: 5px 0;
            color: #1a1a1a;
            font-size: 13px;
        }
        .help-section a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
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
            color: #dc2626;
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
            <h1>Document Signature Reminder</h1>
            <div class="reminder-badge">Reminder #{{ $reminderNumber }}</div>
        </div>

        <!-- Body -->
        <div class="email-body">
            <p class="greeting">Dear {{ $signerName }},</p>
            
            <!-- Urgent Notice -->
            <div class="urgent-notice">
                <h3>Action Required: Document Awaiting Your Signature</h3>
                <p>This is a friendly reminder that a document is still pending your signature.</p>
            </div>

            <p class="message">
                We previously sent you a document that requires your signature, but we haven't received 
                it yet. Your prompt attention to this matter would be greatly appreciated.
            </p>

            <!-- Document Info -->
            <div class="document-info">
                <h3>Document Details</h3>
                <p><strong>Document:</strong> {{ $documentTitle }}</p>
                <p><strong>Reminder:</strong> #{{ $reminderNumber }} of 3</p>
                @if($dueDate)
                    <p class="due-date-warning"><strong>Due Date:</strong> {{ $dueDate }}</p>
                @endif
            </div>

            <!-- Call to Action -->
            <div class="button-container">
                <a href="{{ $signingUrl }}" target="_blank" rel="noopener noreferrer" class="cta-button" style="display: inline-block; background-color: #dc2626; color: #ffffff; text-decoration: none; padding: 15px 40px; border: 2px solid #b91c1c; font-weight: 700; font-size: 16px;">
                    Sign Now
                </a>
            </div>
            <p class="message" style="margin-top: 10px;">
                Or copy this link:
                <a href="{{ $signingUrl }}" target="_blank" rel="noopener noreferrer" style="color:#2563eb;text-decoration:underline;word-break:break-all;">
                    {{ $signingUrl }}
                </a>
            </p>

            <p class="message">
                <strong>Why is this important?</strong><br>
                Your signature is required to proceed with your matter. Any delays in signing this document 
                may impact the progress of your case or application.
            </p>

            <!-- Help Section -->
            <div class="help-section">
                <h4>Need Help?</h4>
                <p>If you're experiencing any issues with the signing process or have questions about 
                the document, please contact us immediately:</p>
                <p>Email: <a href="mailto:info@bansalimmigration.com.au">info@bansalimmigration.com.au</a></p>
                <p>Phone: <a href="tel:+61292673945">+61 2 9267 3945</a></p>
            </div>

            <p class="message">
                We understand that you may have a busy schedule, but your timely response is crucial 
                for the progress of your immigration matter.
            </p>

            <p class="message" style="margin-top: 30px;">
                <strong>Thank you for your cooperation,</strong><br>
                Bansal Migration Team
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
                This is an automated reminder. Please do not reply to this email.<br>
                You will receive up to 3 reminders.
            </p>
            <p style="margin-top: 15px; font-size: 13px;">
                Consumer guide: <a href="https://www.mara.gov.au/get-help-visa-subsite/FIles/consumer_guide_english.pdf" style="color: #dc2626; text-decoration: none; font-weight: 600;">https://www.mara.gov.au/get-help-visa-subsite/FIles/consumer_guide_english.pdf</a>
            </p>
        </div>
    </div>
</body>
</html>
