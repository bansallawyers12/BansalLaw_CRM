@php
    $body = $content ?? ($array['content'] ?? '');
@endphp
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="x-apple-disable-message-reformatting">
  <title></title>
</head>
<body style="margin:0;padding:0;width:100%;background:#ffffff;">
  <div style="width:100%;max-width:100%;">
    {!! $body !!}
  </div>
</body>
</html>
