<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preparing your download...</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8f9fa; margin: 0; padding: 0; }
        .single_package { display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .inner_single_package { background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); padding: 40px 24px; max-width: 480px; width: 100%; }
        h2 { color: #2c3e50; margin-bottom: 16px; }
        p { color: #444; margin-bottom: 12px; }
        a { color: #007bff; text-decoration: underline; }
    </style>
</head>
<body>
<div class="single_package">
    <div class="inner_single_package">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12 text-center">
                    <h2>Preparing your download...</h2>
                    <p>Your signed document will download automatically. You will be redirected to the thank you page shortly.</p>
                    <p>If your download does not start, <a href="{{ $downloadUrl }}" id="manualDownload">click here</a>.</p>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    // Trigger download
    window.onload = function() {
        var link = document.createElement('a');
        link.href = "{{ $downloadUrl }}";
        link.download = '';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        // Redirect after 3 seconds
        setTimeout(function() {
            window.location.href = "{{ $thankyouUrl }}";
        }, 3000);
    };
</script>
</body>
</html> 