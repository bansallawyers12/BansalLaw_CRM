<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Appointment Cancellation</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.6;
            color: #1a1a1a;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .header {
            background-color: #2c3e50;
            color: #ffffff;
            padding: 20px;
            text-align: center;
            border: 1px solid #1a252f;
        }
        .header h1 {
            margin: 10px 0 0 0;
            font-size: 24px;
            color: #ffffff;
        }
        .header p {
            margin: 5px 0 0 0;
            color: #ffffff;
        }
        .content {
            background-color: #ffffff;
            padding: 30px;
            border: 1px solid #dddddd;
        }
        .appointment-details {
            background-color: #f8f9fa;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #dee2e6;
            border-left: 4px solid #dc3545;
        }
        .detail-row {
            margin: 15px 0;
            padding: 10px 0;
            border-bottom: 1px solid #eeeeee;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .label {
            font-weight: 700;
            color: #1a1a1a;
            display: inline-block;
            width: 140px;
        }
        .value {
            color: #1a1a1a;
        }
        .cancellation-notice {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            padding: 20px;
            color: #1a1a1a;
            font-size: 0.9em;
        }
        .contact-info {
            background-color: #e7f3ff;
            border: 1px solid #3b82f6;
            padding: 15px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="{{ URL::to('/public/img/logo.png') }}" alt="Bansal Immigration" style="max-width: 180px; height: auto; margin-bottom: 10px;" />
        <h1>Bansal Immigration</h1>
        <p>Appointment Cancellation</p>
    </div>

    <div class="content">
        <p>Dear {{ $clientName }},</p>

        <p>We are writing to confirm that your appointment with Bansal Immigration has been cancelled.</p>

        <div class="appointment-details">
            <h2 style="margin-top: 0; color: #1a1a1a; font-size: 18px;">Cancelled Appointment Details</h2>

            <div class="detail-row">
                <span class="label">Date:</span>
                <span class="value">{{ $appointmentDate }}</span>
            </div>

            <div class="detail-row">
                <span class="label">Time:</span>
                <span class="value">{{ $appointmentTime }}</span>
            </div>

            <div class="detail-row">
                <span class="label">Location:</span>
                <span class="value">{{ $locationAddress }}</span>
            </div>

            <div class="detail-row">
                <span class="label">Consultant:</span>
                <span class="value">{{ $consultant }}</span>
            </div>

            <div class="detail-row">
                <span class="label">Service:</span>
                <span class="value">{{ $serviceType }}</span>
            </div>

            @if($cancellationReason)
            <div class="detail-row">
                <span class="label">Reason:</span>
                <span class="value">{{ $cancellationReason }}</span>
            </div>
            @endif
        </div>

        <div class="contact-info">
            <strong style="color: #1a1a1a;">Would you like to reschedule?</strong>
            <p style="margin: 10px 0 0 0; color: #1a1a1a;">
                Please contact us to book a new appointment:<br>
                Phone: <a href="tel:{{ str_replace([' ', '-'], '', $locationPhone) }}" style="color: #2563eb; text-decoration: none; font-weight: 600;">{{ $locationPhone }}</a><br>
                Email: <a href="mailto:info@bansalimmigration.com" style="color: #2563eb; text-decoration: none; font-weight: 600;">info@bansalimmigration.com</a><br>
                Website: <a href="https://bansalimmigration.com" style="color: #2563eb; text-decoration: none; font-weight: 600;">bansalimmigration.com</a>
            </p>
        </div>

        <p style="margin-top: 30px;">We hope to assist you with your immigration needs in the future.</p>

        <p>Best regards,<br>
        <strong>Bansal Immigration Team</strong></p>
    </div>

    <div class="footer">
        <p>This is an automated cancellation confirmation email. Please do not reply to this email.</p>
        <p style="margin-top: 15px; font-size: 14px;">
            Consumer guide: <a href="https://www.mara.gov.au/get-help-visa-subsite/FIles/consumer_guide_english.pdf" style="color: #2563eb; text-decoration: none; font-weight: 600;">https://www.mara.gov.au/get-help-visa-subsite/FIles/consumer_guide_english.pdf</a>
        </p>
        <p style="font-size: 0.8em; color: #666666;">
            &copy; {{ date('Y') }} Bansal Immigration. All rights reserved.
        </p>
    </div>
</body>
</html>
