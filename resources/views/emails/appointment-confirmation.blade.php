<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Appointment Confirmation</title>
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
            background-color: #ffffff;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #dee2e6;
            border-left: 4px solid #3498db;
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
        .important-note {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            border-left: 4px solid #ffc107;
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
        <img src="{{URL::to('/public/img/logo.png')}}" alt="Bansal Immigration" style="max-width: 180px; height: auto; margin-bottom: 10px;" />
        <h1>Bansal Immigration</h1>
        <p>Appointment Confirmation</p>
    </div>

    <div class="content">
        <p>Dear {{ $clientName }},</p>

        <p>Thank you for booking an appointment with Bansal Immigration. This email confirms your appointment details:</p>

        <div class="appointment-details">
            <h2 style="margin-top: 0; color: #1a1a1a; font-size: 18px;">Appointment Details</h2>
            
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
        </div>

        @if($adminNotes)
        <div class="important-note">
            <strong>Important Notes:</strong>
            <p style="margin: 10px 0 0 0; color: #1a1a1a;">{{ $adminNotes }}</p>
        </div>
        @endif

        <div class="important-note">
            <strong>Please bring:</strong>
            <ul style="margin: 10px 0 0 0; padding-left: 20px; color: #1a1a1a;">
                <li>Valid photo identification (Passport, Driver's License)</li>
                <li>All relevant documents related to your visa inquiry</li>
                <li>Any previous correspondence from immigration authorities</li>
            </ul>
        </div>

        <div class="contact-info">
            <strong style="color: #1a1a1a;">Need to reschedule or have questions?</strong>
            <p style="margin: 10px 0 0 0; color: #1a1a1a;">
                Phone: <a href="tel:{{ str_replace([' ', '-'], '', $locationPhone) }}" style="color: #2563eb; text-decoration: none; font-weight: 600;">{{ $locationPhone }}</a><br>
                Email: <a href="mailto:info@bansalimmigration.com" style="color: #2563eb; text-decoration: none; font-weight: 600;">info@bansalimmigration.com</a><br>
                Website: <a href="https://bansalimmigration.com" style="color: #2563eb; text-decoration: none; font-weight: 600;">bansalimmigration.com</a>
            </p>
        </div>

        <p style="margin-top: 30px;">We look forward to assisting you with your immigration needs.</p>

        <p>Best regards,<br>
        <strong>Bansal Immigration Team</strong></p>
    </div>

    <div class="footer">
        <p>This is an automated confirmation email. Please do not reply to this email.</p>
        <p style="margin-top: 15px; font-size: 14px;">
            Consumer guide: <a href="https://www.mara.gov.au/get-help-visa-subsite/FIles/consumer_guide_english.pdf" style="color: #2563eb; text-decoration: none; font-weight: 600;">https://www.mara.gov.au/get-help-visa-subsite/FIles/consumer_guide_english.pdf</a>
        </p>
        <p style="font-size: 0.8em; color: #666666;">
            &copy; {{ date('Y') }} Bansal Immigration. All rights reserved.
        </p>
    </div>
</body>
</html>
