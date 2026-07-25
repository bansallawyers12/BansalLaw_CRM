<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Certificate of Completion — {{ $document->display_title ?? $document->file_name }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; margin: 40px; }
        h1 { font-size: 22px; margin: 0 0 6px; }
        h2 { font-size: 14px; margin: 24px 0 8px; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
        .muted { color: #666; font-size: 11px; }
        .box { border: 1px solid #ddd; padding: 12px; margin-top: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { text-align: left; padding: 6px 4px; border-bottom: 1px solid #eee; vertical-align: top; }
        th { width: 32%; color: #555; font-weight: normal; }
        .hash { font-family: DejaVu Sans Mono, monospace; font-size: 9px; word-break: break-all; }
        .timeline td { font-size: 11px; }
        .footer { margin-top: 28px; font-size: 10px; color: #777; }
    </style>
</head>
<body>
    <h1>Certificate of Completion</h1>
    <p class="muted">Electronic signature evidence package — generated {{ $generatedAt->format('M d, Y g:i A T') }}</p>

    <h2>Document</h2>
    <table>
        <tr><th>Title</th><td>{{ $document->display_title ?? $document->file_name }}</td></tr>
        <tr><th>Document ID</th><td>{{ $document->id }}</td></tr>
        <tr><th>Status</th><td>{{ strtoupper($document->status ?? 'signed') }}</td></tr>
    </table>

    <h2>Signer</h2>
    <table>
        <tr><th>Name</th><td>{{ $signer->name }}</td></tr>
        <tr><th>Email</th><td>{{ $signer->email }}</td></tr>
        <tr><th>Opened at</th><td>{{ $evidence['opened_at'] ?? '—' }}</td></tr>
        <tr><th>Signed at</th><td>{{ $evidence['signed_at'] ?? '—' }}</td></tr>
        <tr><th>IP address</th><td>{{ $evidence['ip_address'] ?? '—' }}</td></tr>
        <tr><th>User agent</th><td style="font-size: 10px;">{{ $evidence['user_agent'] ?? '—' }}</td></tr>
    </table>

    <h2>Document Integrity (SHA-256)</h2>
    <table>
        <tr>
            <th>Original PDF</th>
            <td class="hash">{{ $evidence['original_sha256'] ?? 'Not available' }}</td>
        </tr>
        <tr>
            <th>Signed PDF</th>
            <td class="hash">{{ $evidence['signed_sha256'] ?? 'Not available' }}</td>
        </tr>
        @foreach(($evidence['signature_image_hashes'] ?? []) as $sigHash)
        <tr>
            <th>Signature image #{{ ($sigHash['index'] ?? 0) + 1 }}</th>
            <td class="hash">{{ $sigHash['sha256'] }}</td>
        </tr>
        @endforeach
    </table>

    <h2>Audit Timeline</h2>
    @if($timeline->isEmpty())
        <p class="muted">No prior audit events recorded.</p>
    @else
        <table class="timeline">
            <thead>
                <tr>
                    <th style="width: 22%;">When</th>
                    <th style="width: 18%;">Event</th>
                    <th>Detail</th>
                </tr>
            </thead>
            <tbody>
                @foreach($timeline as $event)
                <tr>
                    <td>{{ optional($event->created_at)->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $event->action_text }}</td>
                    <td>
                        {{ $event->note }}
                        @if($event->ip_address)
                            <br><span class="muted">IP: {{ $event->ip_address }}</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        This certificate records that an electronic signature image was applied to the document via the CRM signature workflow.
        It is an audit evidence package (document hashes + event timeline), not a cryptographic digital certificate (PKCS#7 / X.509).
    </div>
</body>
</html>
