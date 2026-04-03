<!DOCTYPE html>
<html>
<head>
    <title>Email Details</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h2>Email Details</h2>
        @if (isset($emailData['error']))
            <div class="alert alert-danger">{{ $emailData['error'] }}</div>
        @else
            <ul class="list-group">
                <li class="list-group-item"><strong>From:</strong> {{ $emailData['from'] }}</li>
                <li class="list-group-item"><strong>To:</strong> {{ $emailData['to'] }}</li>
                <li class="list-group-item"><strong>Subject:</strong> {{ $emailData['subject'] }}</li>
                <li class="list-group-item"><strong>Body:</strong> {{ $emailData['body'] }}</li>
            </ul>
        @endif
    </div>
</body>
</html>

