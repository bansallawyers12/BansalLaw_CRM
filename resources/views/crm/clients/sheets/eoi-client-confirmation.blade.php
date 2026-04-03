<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EOI Confirmation - {{ $action === 'confirm' ? 'Confirm Details' : 'Request Amendment' }}</title>
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
        .confirmation-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 700px;
            width: 100%;
            margin: 20px;
        }
        .confirmation-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px 15px 0 0;
            text-align: center;
        }
        .confirmation-header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }
        .confirmation-header .icon {
            font-size: 60px;
            margin-bottom: 15px;
        }
        .confirmation-body {
            padding: 40px;
        }
        .client-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .client-info h3 {
            color: #667eea;
            font-size: 20px;
            margin-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 10px;
        }
        .detail-row {
            display: flex;
            padding: 12px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: 600;
            width: 180px;
            color: #495057;
        }
        .detail-value {
            flex: 1;
            color: #212529;
        }
        .form-group label {
            font-weight: 600;
            color: #495057;
        }
        .btn-custom {
            padding: 12px 40px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .btn-confirm {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
        }
        .btn-confirm:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
            color: white;
        }
        .btn-secondary-custom {
            background: white;
            border: 2px solid #667eea;
            color: #667eea;
        }
        .btn-secondary-custom:hover {
            background: #667eea;
            color: white;
        }
        .alert-info-custom {
            background: #e7f3ff;
            border-left: 4px solid #2196f3;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 25px;
        }
        .alert-warning-custom {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 25px;
        }
    </style>
</head>
<body>
    <div class="confirmation-container">
        <div class="confirmation-header">
            <div class="icon">
                @if($action === 'confirm')
                    <i class="fas fa-check-circle"></i>
                @else
                    <i class="fas fa-edit"></i>
                @endif
            </div>
            <h1>
                @if($action === 'confirm')
                    Confirm Your EOI Details
                @else
                    Request Amendment
                @endif
            </h1>
        </div>

        <div class="confirmation-body">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle"></i> {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
                </div>
            @endif

            @if($eoi->client_confirmation_status === 'confirmed')
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <strong>Already Confirmed!</strong> You have already confirmed these details on {{ $eoi->client_last_confirmation->format('d/m/Y H:i') }}.
                </div>
            @elseif($eoi->client_confirmation_status === 'amendment_requested')
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> <strong>Amendment Requested!</strong> You have already requested an amendment on {{ $eoi->client_last_confirmation->format('d/m/Y H:i') }}.
                </div>
            @endif

            <div class="client-info">
                <h3>Client Information</h3>
                <div class="detail-row">
                    <div class="detail-label">Name:</div>
                    <div class="detail-value">{{ $eoi->client->first_name }} {{ $eoi->client->last_name }}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Client ID:</div>
                    <div class="detail-value">{{ $eoi->client->client_id }}</div>
                </div>
            </div>

            <div class="client-info">
                <h3>EOI Details</h3>
                <div class="detail-row">
                    <div class="detail-label">EOI Number:</div>
                    <div class="detail-value">{{ $eoi->EOI_number ?? 'N/A' }}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Subclass(es):</div>
                    <div class="detail-value">{{ $eoi->formatted_subclasses }}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">State(s):</div>
                    <div class="detail-value">{{ $eoi->formatted_states }}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Occupation:</div>
                    <div class="detail-value">{{ $eoi->EOI_occupation ?? 'N/A' }}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Points:</div>
                    <div class="detail-value">{{ $eoi->EOI_point ?? 'N/A' }}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Submission Date:</div>
                    <div class="detail-value">
                        @if($eoi->EOI_submission_date)
                            {{ $eoi->EOI_submission_date->format('d/m/Y') }}
                        @else
                            N/A
                        @endif
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Status:</div>
                    <div class="detail-value">
                        <span class="badge badge-{{ $eoi->eoi_status == 'invited' ? 'success' : ($eoi->eoi_status == 'submitted' ? 'primary' : 'secondary') }}">
                            {{ ucfirst($eoi->eoi_status ?? 'Draft') }}
                        </span>
                    </div>
                </div>
            </div>

            @if($action === 'confirm')
                <div class="alert-info-custom">
                    <i class="fas fa-info-circle"></i> <strong>Important:</strong> By clicking "Confirm", you are verifying that all the information above is correct.
                </div>

                <form action="{{ route('client.eoi.process', ['token' => $token]) }}" method="POST">
                    @csrf
                    <input type="hidden" name="action" value="confirm">
                    
                    <div class="text-center">
                        <button type="submit" class="btn btn-confirm btn-custom" 
                                @if($eoi->client_confirmation_status === 'confirmed') disabled @endif>
                            <i class="fas fa-check-circle"></i> Confirm Details
                        </button>
                        <a href="{{ route('client.eoi.amend', ['token' => $token]) }}" class="btn btn-secondary-custom btn-custom">
                            <i class="fas fa-edit"></i> Request Amendment
                        </a>
                    </div>
                </form>
            @else
                <div class="alert-warning-custom">
                    <i class="fas fa-exclamation-triangle"></i> <strong>Request Amendment:</strong> Please provide details about what changes you would like to make.
                </div>

                <form action="{{ route('client.eoi.process', ['token' => $token]) }}" method="POST">
                    @csrf
                    <input type="hidden" name="action" value="amend">
                    
                    <div class="form-group">
                        <label for="notes">Amendment Notes <span class="text-danger">*</span></label>
                        <textarea name="notes" id="notes" class="form-control @error('notes') is-invalid @enderror" 
                                  rows="6" placeholder="Please describe the changes you would like to make..."
                                  required>{{ old('notes', $eoi->client_confirmation_notes) }}</textarea>
                        @error('notes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="form-text text-muted">Maximum 1000 characters</small>
                    </div>

                    <div class="text-center">
                        <button type="submit" class="btn btn-confirm btn-custom"
                                @if($eoi->client_confirmation_status === 'amendment_requested') disabled @endif>
                            <i class="fas fa-paper-plane"></i> Submit Amendment Request
                        </button>
                        <a href="{{ route('client.eoi.confirm', ['token' => $token]) }}" class="btn btn-secondary-custom btn-custom">
                            <i class="fas fa-arrow-left"></i> Back to Confirmation
                        </a>
                    </div>
                </form>
            @endif
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
