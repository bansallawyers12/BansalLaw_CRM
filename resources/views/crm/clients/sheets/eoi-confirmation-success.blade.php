<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Success - EOI Confirmation</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .success-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 600px;
            width: 100%;
            margin: 20px;
            text-align: center;
            padding: 60px 40px;
        }
        .success-icon {
            font-size: 100px;
            color: #28a745;
            margin-bottom: 30px;
            animation: scaleIn 0.5s ease-in-out;
        }
        @keyframes scaleIn {
            0% {
                transform: scale(0);
                opacity: 0;
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }
        .success-container h1 {
            color: #667eea;
            font-size: 32px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .success-container p {
            font-size: 18px;
            color: #6c757d;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        .info-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-top: 30px;
            text-align: left;
        }
        .info-box h4 {
            color: #667eea;
            font-size: 18px;
            margin-bottom: 15px;
        }
        .info-box p {
            font-size: 15px;
            margin-bottom: 10px;
        }
        .info-row {
            display: flex;
            padding: 8px 0;
        }
        .info-label {
            font-weight: 600;
            width: 140px;
            color: #495057;
        }
        .info-value {
            flex: 1;
            color: #212529;
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        
        @if(session('success'))
            <h1>Success!</h1>
            <p>{{ session('success') }}</p>
        @else
            <h1>Thank You!</h1>
            <p>Your response has been recorded successfully.</p>
        @endif

        <div class="info-box">
            <h4><i class="fas fa-info-circle"></i> What Happens Next?</h4>
            
            @if($eoi->client_confirmation_status === 'confirmed')
                <p><i class="fas fa-check text-success"></i> Your migration agent has been notified that you have confirmed your EOI details.</p>
                <p><i class="fas fa-clock text-info"></i> They will proceed with the next steps in your migration process.</p>
            @elseif($eoi->client_confirmation_status === 'amendment_requested')
                <p><i class="fas fa-edit text-warning"></i> Your migration agent has been notified about your amendment request.</p>
                <p><i class="fas fa-phone text-info"></i> They will review your request and contact you shortly to discuss the changes.</p>
            @endif

            <hr>
            
            <div class="info-row">
                <div class="info-label">EOI Number:</div>
                <div class="info-value">{{ $eoi->EOI_number ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Status:</div>
                <div class="info-value">
                    @if($eoi->client_confirmation_status === 'confirmed')
                        <span class="badge badge-success">Confirmed</span>
                    @elseif($eoi->client_confirmation_status === 'amendment_requested')
                        <span class="badge badge-warning">Amendment Requested</span>
                    @else
                        <span class="badge badge-secondary">Pending</span>
                    @endif
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Submitted:</div>
                <div class="info-value">{{ $eoi->client_last_confirmation ? $eoi->client_last_confirmation->format('d/m/Y H:i') : 'N/A' }}</div>
            </div>
        </div>

        <p class="mt-4 mb-0 text-muted">
            <small>If you have any questions, please contact your migration agent directly.</small>
        </p>
    </div>
</body>
</html>
