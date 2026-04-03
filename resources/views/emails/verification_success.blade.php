<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verified Successfully</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #f5f5f5;
            padding: 20px;
            margin: 0;
        }
        .success-container {
            background: #ffffff;
            padding: 50px 40px;
            border: 1px solid #dddddd;
            text-align: center;
            max-width: 550px;
            width: 100%;
        }
        .success-icon {
            width: 80px;
            height: 80px;
            background-color: #10b981;
            border: 3px solid #059669;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
        }
        .success-icon::before {
            content: '✓';
            font-size: 50px;
            color: #ffffff;
            font-weight: bold;
        }
        h1 {
            color: #1a1a1a;
            margin-bottom: 20px;
            font-size: 28px;
            font-weight: 700;
        }
        p {
            color: #1a1a1a;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 15px;
        }
        .email-display {
            background-color: #ecfdf5;
            border: 2px solid #6ee7b7;
            padding: 15px 20px;
            font-weight: 700;
            color: #1a1a1a;
            margin: 30px 0;
            font-size: 16px;
            word-break: break-all;
        }
        .success-message {
            background-color: #f0fdf4;
            border: 1px solid #10b981;
            border-left: 4px solid #10b981;
            padding: 20px;
            margin: 25px 0;
            text-align: left;
        }
        .success-message p {
            margin: 0;
            color: #1a1a1a;
            font-size: 15px;
        }
        .close-button {
            margin-top: 35px;
            padding: 15px 40px;
            background-color: #10b981;
            color: #ffffff;
            border: 2px solid #059669;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .company-name {
            margin-top: 30px;
            color: #666666;
            font-size: 14px;
        }
        .company-name strong {
            color: #1a1a1a;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon"></div>
        <h1>Email Verified Successfully!</h1>
        <p>Your email address has been verified:</p>
        <div class="email-display">{{ $clientEmail->email }}</div>
        
        <div class="success-message">
            <p><strong>Verification Complete</strong></p>
            <p style="margin-top: 10px;">You can now receive important updates and communications from Bansal Immigration.</p>
        </div>
        
        <p>Thank you for confirming your email address. You can now close this window.</p>
        <button class="close-button" onclick="window.close()" style="background-color: #10b981; color: #ffffff; padding: 15px 40px; border: 2px solid #059669; font-size: 16px; font-weight: 700; cursor: pointer;">Close Window</button>
        
        <div class="company-name">
            Verified by <strong>Bansal Immigration</strong>
        </div>
    </div>
</body>
</html>
