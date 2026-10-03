<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    @php
        $brand = config('app.login_brand') ?? config('app.name', 'Bansal Lawyers CRM');
        $pageTitle = trim($__env->yieldContent('title')) ?: 'Something went wrong';
    @endphp
    <title>{{ $brand }} | {{ $pageTitle }}</title>
    <link rel="icon" type="image/png" href="{{ asset('img/favicon.png') }}">
    <style>
        :root {
            --navy: #1e3d60;
            --navy-deep: #152a42;
            --accent: #3a6fa8;
            --accent-light: #5b9bd5;
            --page-bg: #eef4fb;
            --card-bg: #ffffff;
            --text: #1a2c40;
            --muted: #5e7a90;
            --border: #c8dcef;
            --radius: 20px;
        }
        *, *::before, *::after { box-sizing: border-box; }
        html, body {
            margin: 0;
            min-height: 100vh;
            min-height: 100dvh;
            font-family: 'Segoe UI', system-ui, -apple-system, BlinkMacSystemFont, Roboto, sans-serif;
            color: var(--text);
            background: var(--page-bg);
        }
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            position: relative;
            overflow-x: hidden;
        }
        body::before,
        body::after {
            content: '';
            position: fixed;
            border-radius: 50%;
            pointer-events: none;
            z-index: 0;
        }
        body::before {
            width: min(520px, 90vw);
            height: min(520px, 90vw);
            top: -120px;
            right: -80px;
            background: radial-gradient(circle, rgba(58, 111, 168, 0.18) 0%, transparent 70%);
        }
        body::after {
            width: min(400px, 80vw);
            height: min(400px, 80vw);
            bottom: -100px;
            left: -60px;
            background: radial-gradient(circle, rgba(30, 61, 96, 0.12) 0%, transparent 70%);
        }
        .error-page {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 520px;
        }
        .error-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: 0 24px 48px rgba(30, 61, 96, 0.12), 0 0 0 1px rgba(255, 255, 255, 0.6) inset;
            padding: 40px 32px 32px;
            text-align: center;
        }
        .error-brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 28px;
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--navy);
            letter-spacing: 0.02em;
        }
        .error-brand img {
            width: 36px;
            height: 36px;
            object-fit: contain;
        }
        .error-code {
            font-size: clamp(3.5rem, 12vw, 5rem);
            font-weight: 800;
            line-height: 1;
            margin: 0 0 8px;
            background: linear-gradient(135deg, var(--navy) 0%, var(--accent) 55%, var(--accent-light) 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .error-heading {
            margin: 0 0 12px;
            font-size: 1.35rem;
            font-weight: 700;
            color: var(--navy);
        }
        .error-message {
            margin: 0 0 28px;
            font-size: 1rem;
            line-height: 1.55;
            color: var(--muted);
            max-width: 38ch;
            margin-left: auto;
            margin-right: auto;
        }
        .error-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: center;
            margin-bottom: 20px;
        }
        .error-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 22px;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid transparent;
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
        }
        .error-btn:active { transform: scale(0.98); }
        .error-btn--primary {
            background: linear-gradient(135deg, var(--navy) 0%, var(--accent) 100%);
            color: #fff;
            box-shadow: 0 8px 20px rgba(30, 61, 96, 0.25);
        }
        .error-btn--primary:hover {
            box-shadow: 0 10px 24px rgba(30, 61, 96, 0.32);
            color: #fff;
        }
        .error-btn--ghost {
            background: var(--page-bg);
            color: var(--navy);
            border-color: var(--border);
        }
        .error-btn--ghost:hover {
            background: #e4edf8;
            color: var(--navy-deep);
        }
        .error-foot {
            font-size: 0.8rem;
            color: var(--muted);
            margin: 0;
        }
        .error-debug {
            margin-top: 24px;
            padding: 14px;
            text-align: left;
            font-size: 0.75rem;
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: auto;
            max-height: 160px;
            color: #334155;
            word-break: break-word;
        }
        @media (max-width: 480px) {
            .error-card { padding: 32px 20px 24px; }
            .error-actions { flex-direction: column; }
            .error-btn { width: 100%; }
        }
    </style>
    @stack('head')
</head>
<body>
    <div class="error-page">
        <div class="error-card">
            <div class="error-brand">
                <img src="{{ asset('img/favicon.png') }}" alt="" width="36" height="36">
                <span>{{ $brand }}</span>
            </div>
            @yield('content')
            <p class="error-foot">If this keeps happening, contact your system administrator.</p>
        </div>
    </div>
</body>
</html>
