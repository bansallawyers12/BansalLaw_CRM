<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Export - {{ $email->subject ?: 'No Subject' }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        
        .header {
            border-bottom: 2px solid #007bff;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .subject {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 10px;
        }
        
        .meta-info {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        
        .meta-row {
            display: table-row;
        }
        
        .meta-label {
            display: table-cell;
            font-weight: bold;
            width: 100px;
            padding: 5px 0;
        }
        
        .meta-value {
            display: table-cell;
            padding: 5px 0;
        }
        
        .content {
            margin-bottom: 30px;
        }
        
        .content-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #333;
        }
        
        .email-body {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            white-space: pre-wrap;
        }
        
        .attachments {
            margin-top: 30px;
        }
        
        .attachments-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #333;
        }
        
        .attachment-item {
            background: #f5f5f5;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 3px;
            border-left: 4px solid #007bff;
        }
        
        .attachment-name {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .attachment-details {
            font-size: 12px;
            color: #1a1a1a;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            color: #1a1a1a;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="subject">{{ $email->subject ?: 'No Subject' }}</div>
        <div class="meta-info">
            <div class="meta-row">
                <div class="meta-label">From:</div>
                <div class="meta-value">{{ $email->sender_name ?: 'Unknown' }} &lt;{{ $email->sender_email ?: 'unknown@email.com' }}&gt;</div>
            </div>
            <div class="meta-row">
                <div class="meta-label">To:</div>
                <div class="meta-value">{{ $email->recipients ? implode(', ', $email->recipients) : 'No recipients' }}</div>
            </div>
            <div class="meta-row">
                <div class="meta-label">Date:</div>
                <div class="meta-value">{{ $email->sent_date ? $email->sent_date->format('F j, Y \a\t g:i A') : 'Unknown date' }}</div>
            </div>
            @if($email->message_id)
            <div class="meta-row">
                <div class="meta-label">Message ID:</div>
                <div class="meta-value">{{ $email->message_id }}</div>
            </div>
            @endif
        </div>
    </div>

    <div class="content">
        <div class="content-title">Email Content</div>
        <div class="email-body">
            @if($email->html_content)
                {!! $email->html_content !!}
            @elseif($email->text_content)
                {{ $email->text_content }}
            @else
                No content available
            @endif
        </div>
    </div>

    @if($email->attachments && count($email->attachments) > 0)
    <div class="attachments">
        <div class="attachments-title">Attachments ({{ count($email->attachments) }})</div>
        @foreach($email->attachments as $attachment)
        <div class="attachment-item">
            <div class="attachment-name">{{ $attachment->filename }}</div>
            <div class="attachment-details">
                Size: {{ number_format($attachment->file_size / 1024, 2) }} KB
                @if($attachment->content_type)
                | Type: {{ $attachment->content_type }}
                @endif
            </div>
        </div>
        @endforeach
    </div>
    @endif

    <div class="footer">
        <p>Exported on {{ now()->format('F j, Y \a\t g:i A') }}</p>
        <p>Email ID: {{ $email->id }} | File: {{ $email->file_name }}</p>
    </div>
</body>
</html>
