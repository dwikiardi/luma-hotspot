<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WiFi - {{ config('app.name', 'Luma Network') }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, {{ $branding['color'] ?? '#6366f1' }}, #3730a3);
            padding: 20px;
        }
        .card {
            background: white;
            border-radius: 24px;
            padding: 40px 32px;
            max-width: 400px;
            width: 100%;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        .icon {
            width: 72px;
            height: 72px;
            background: {{ $branding['color'] ?? '#6366f1' }};
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }
        .icon svg { width: 36px; height: 36px; fill: white; }
        h1 { font-size: 22px; color: #1f2937; margin-bottom: 12px; }
        p { color: #6b7280; font-size: 15px; line-height: 1.6; margin-bottom: 28px; }
        .btn {
            display: inline-block;
            background: {{ $branding['color'] ?? '#6366f1' }};
            color: white;
            text-decoration: none;
            padding: 16px 32px;
            border-radius: 14px;
            font-weight: 600;
            font-size: 17px;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
        }
        .btn:active { transform: scale(0.96); }
        .hint {
            margin-top: 20px;
            font-size: 13px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">
            <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
        </div>
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>
        <a href="{{ $portalUrl }}" class="btn" id="openBtn" target="_blank" rel="noopener">Buka Portal WiFi</a>
        <p class="hint">Ketuk tombol di atas untuk melanjutkan</p>
    </div>
    <script>
        var isAndroid = /Android/i.test(navigator.userAgent);
        
        if (isAndroid) {
            var intentUrl = 'intent://103.137.140.6:8081/portal?browser=1&nas_id={{ urlencode($nasId ?? '') }}&client_mac={{ urlencode($mac ?? '') }}&link_login={{ urlencode($linkLogin ?? '') }}&dst={{ urlencode($dstUrl ?? '') }}#Intent;scheme=http;package=com.android.chrome;end';
            // Auto-redirect Android to Chrome
            window.location.href = intentUrl;
        }
        // iOS: no auto-redirect, user must tap the button (x-safari-https only works in <a href>)
    </script>
</body>
</html>
