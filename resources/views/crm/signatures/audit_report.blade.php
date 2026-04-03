<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Signature Audit Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #667eea;
        }
        
        .header h1 {
            color: #667eea;
            margin: 0 0 10px 0;
            font-size: 24px;
        }
        
        .header p {
            margin: 0;
            color: #6c757d;
            font-size: 14px;
        }
        
        .summary {
            background: #f8f9fa;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .summary h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #2c3e50;
        }
        
        .summary-grid {
            display: table;
            width: 100%;
        }
        
        .summary-item {
            display: table-cell;
            padding: 5px;
            text-align: center;
        }
        
        .summary-item strong {
            display: block;
            font-size: 20px;
            color: #667eea;
        }
        
        .summary-item span {
            font-size: 11px;
            color: #6c757d;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        th {
            background: #667eea;
            color: white;
            padding: 8px;
            text-align: left;
            font-size: 11px;
            font-weight: 600;
        }
        
        td {
            padding: 6px 8px;
            border-bottom: 1px solid #e9ecef;
            font-size: 10px;
        }
        
        tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: 600;
        }
        
        .badge-draft {
            background: #6c757d;
            color: white;
        }
        
        .badge-sent {
            background: #ffc107;
            color: #000;
        }
        
        .badge-signed {
            background: #28a745;
            color: white;
        }
        
        .badge-pending {
            background: #ffc107;
            color: #000;
        }
        
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            text-align: center;
            font-size: 10px;
            color: #6c757d;
        }
        
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>📝 Signature Audit Report</h1>
        <p>Generated on {{ now()->format('F j, Y \a\t g:i A') }}</p>
        <p>Bansal Migration Management System</p>
    </div>
    
    <!-- Summary Statistics -->
    <div class="summary">
        <h3>Summary Statistics</h3>
        <div class="summary-grid">
            <div class="summary-item">
                <strong>{{ $documents->count() }}</strong>
                <span>Total Documents</span>
            </div>
            <div class="summary-item">
                <strong>{{ $documents->where('status', 'signed')->count() }}</strong>
                <span>Signed</span>
            </div>
            <div class="summary-item">
                <strong>{{ $documents->where('status', 'sent')->count() }}</strong>
                <span>Pending</span>
            </div>
            <div class="summary-item">
                <strong>{{ $documents->where('status', 'draft')->count() }}</strong>
                <span>Draft</span>
            </div>
        </div>
    </div>
    
    <!-- Documents Table -->
    <table>
        <thead>
            <tr>
                <th style="width: 8%;">Doc ID</th>
                <th style="width: 20%;">Title</th>
                <th style="width: 10%;">Status</th>
                <th style="width: 15%;">Signer</th>
                <th style="width: 12%;">Created At</th>
                <th style="width: 12%;">Signed At</th>
                <th style="width: 8%;">Reminders</th>
                <th style="width: 15%;">Owner</th>
            </tr>
        </thead>
        <tbody>
            @foreach($documents as $doc)
                @foreach($doc->signers as $signer)
                <tr>
                    <td>#{{ $doc->id }}</td>
                    <td>{{ Str::limit($doc->display_title, 30) }}</td>
                    <td>
                        <span class="badge badge-{{ $doc->status }}">
                            {{ ucfirst($doc->status) }}
                        </span>
                    </td>
                    <td>
                        {{ $signer->name }}<br>
                        <small style="color: #6c757d;">{{ $signer->email }}</small>
                    </td>
                    <td>{{ $doc->created_at->format('M d, Y') }}</td>
                    <td>
                        @if($signer->signed_at)
                            {{ $signer->signed_at->format('M d, Y') }}
                        @else
                            <span style="color: #6c757d;">—</span>
                        @endif
                    </td>
                    <td style="text-align: center;">{{ $signer->reminder_count }}</td>
                    <td>
                        @if($doc->creator)
                            {{ $doc->creator->first_name }} {{ $doc->creator->last_name }}
                        @else
                            <span style="color: #6c757d;">Unknown</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
    
    <!-- Document Details (if less than 50 docs) -->
    @if($documents->count() <= 50)
    <div class="page-break"></div>
    
    <h2 style="color: #667eea; margin-top: 30px; margin-bottom: 20px;">Document Details</h2>
    
    @foreach($documents as $doc)
    <div style="margin-bottom: 25px; padding: 15px; border: 1px solid #dee2e6; border-radius: 5px;">
        <h3 style="margin: 0 0 10px 0; color: #2c3e50; font-size: 14px;">
            #{{ $doc->id }} - {{ $doc->display_title }}
        </h3>
        
        <table style="margin: 0;">
            <tr>
                <td style="border: none;"><strong>Status:</strong></td>
                <td style="border: none;">
                    <span class="badge badge-{{ $doc->status }}">{{ ucfirst($doc->status) }}</span>
                </td>
                <td style="border: none;"><strong>Type:</strong></td>
                <td style="border: none;">General</td>
            </tr>
            <tr>
                <td style="border: none;"><strong>Priority:</strong></td>
                <td style="border: none;">Normal</td>
                <td style="border: none;"><strong>Created:</strong></td>
                <td style="border: none;">{{ $doc->created_at->format('M d, Y g:i A') }}</td>
            </tr>
            <tr>
                <td style="border: none;"><strong>Owner:</strong></td>
                <td style="border: none;" colspan="3">
                    @if($doc->creator)
                        {{ $doc->creator->first_name }} {{ $doc->creator->last_name }} ({{ $doc->creator->email }})
                    @else
                        Unknown
                    @endif
                </td>
            </tr>
            @if(($doc->client_id && $doc->client) || ($doc->lead_id && $doc->lead))
            <tr>
                <td style="border: none;"><strong>Associated:</strong></td>
                <td style="border: none;" colspan="3">
                    {{ $doc->lead_id ? 'Lead' : 'Client' }}: 
                    {{ ($doc->client ?? $doc->lead)->first_name ?? '' }} {{ ($doc->client ?? $doc->lead)->last_name ?? '' }}
                </td>
            </tr>
            @endif
        </table>
        
        <h4 style="margin: 15px 0 5px 0; font-size: 12px; color: #6c757d;">Signers:</h4>
        <ul style="margin: 0; padding-left: 20px; font-size: 10px;">
            @foreach($doc->signers as $signer)
            <li>
                <strong>{{ $signer->name }}</strong> ({{ $signer->email }}) - 
                <span class="badge badge-{{ $signer->status }}">{{ ucfirst($signer->status) }}</span>
                @if($signer->signed_at)
                    - Signed: {{ $signer->signed_at->format('M d, Y g:i A') }}
                @endif
                - Reminders: {{ $signer->reminder_count }}
            </li>
            @endforeach
        </ul>
    </div>
    
    @if($loop->iteration % 3 == 0 && !$loop->last)
        <div class="page-break"></div>
    @endif
    @endforeach
    @endif
    
    <!-- Footer -->
    <div class="footer">
        <p>This is a system-generated report from Bansal Migration Management System</p>
        <p>Report contains {{ $documents->count() }} document(s) with {{ $documents->sum(fn($d) => $d->signers->count()) }} total signer(s)</p>
        <p>© {{ now()->year }} Bansal Migration. All rights reserved.</p>
    </div>
</body>
</html>

