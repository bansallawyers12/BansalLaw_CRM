<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Failed</title>
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
        .error-container {
            background: #ffffff;
            padding: 50px 40px;
            border: 1px solid #dddddd;
            text-align: center;
            max-width: 550px;
            width: 100%;
        }
        .error-icon {
            width: 80px;
            height: 80px;
            background-color: #ef4444;
            border: 3px solid #dc2626;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
        }
        .error-icon::before {
            content: '✗';
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
        .error-message {
            background-color: #fff3cd;
            border: 2px solid #ffc107;
            padding: 20px;
            margin: 25px 0;
            text-align: left;
        }
        .error-message p {
            margin: 0;
            color: #1a1a1a;
            font-weight: 600;
            font-size: 15px;
        }
        .reasons-box {
            background-color: #f8f9fa;
            padding: 25px;
            margin: 25px 0;
            text-align: left;
            border: 1px solid #dee2e6;
        }
        .reasons-box p {
            margin-bottom: 15px;
            font-weight: 700;
            color: #1a1a1a;
            font-size: 16px;
        }
        .reasons-box ul {
            margin: 0;
            padding-left: 25px;
            color: #1a1a1a;
        }
        .reasons-box li {
            margin-bottom: 10px;
            font-size: 15px;
            line-height: 1.6;
        }
        .help-box {
            background-color: #e7f3ff;
            border: 1px solid #3b82f6;
            border-left: 4px solid #3b82f6;
            padding: 20px;
            margin-top: 30px;
            text-align: left;
        }
        .help-box p {
            margin: 0;
            color: #1a1a1a;
        }
        .help-box p:first-child {
            font-weight: 700;
            font-size: 16px;
            margin-bottom: 10px;
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
    <div class="error-container">
        <div class="error-icon"></div>
        <h1>Verification Failed</h1>
        
        <div class="error-message">
            <p>{{ $message }}</p>
        </div>
        
        <div class="reasons-box">
            <p>Possible reasons:</p>
            <ul>
                <li>The verification link has expired (links are valid for 24 hours)</li>
                <li>The link has already been used</li>
                <li>The link is invalid or corrupted</li>
            </ul>
        </div>
        
        <div class="help-box">
            <p>Need help?</p>
            <p>Please contact Bansal Immigration office to request a new verification email.</p>
        </div>
        
        <div class="company-name">
            <strong>Bansal Immigration</strong>
        </div>
    </div>
</body>
</html>
