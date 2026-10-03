<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $title ?? 'Bansal Lawyers Document' }}</title>
    <style>
        @page {
            margin: 15px;
            size: auto;
        }
        html, body {
            margin: 0;
            padding: 0;
            background-color: #ffffff;
            font-family: sans-serif;
            text-align: center;
        }
        .document-wrapper {
            width: 100%;
            text-align: center;
            margin: 0 auto;
        }
        .document-image {
            max-width: 100%;
            height: auto;
            display: block;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <div class="document-wrapper">
        <img class="document-image" src="{{ $imageUrl }}" alt="{{ $title ?? 'Bansal Lawyers Document' }}">
    </div>
</body>
</html>
