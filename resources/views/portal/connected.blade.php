<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>{{ $venueName }} - Connected</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            background: linear-gradient(160deg, {{ $color }} 0%, {{ $colorDark }} 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #fff;
        }
        .container { max-width: 420px; width: 100%; text-align: center; }
        .card {
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 28px;
            padding: 48px 28px 36px;
            box-shadow: 0 30px 60px rgba(0,0,0,0.2);
        }
        .check-icon {
            width: 72px; height: 72px;
            background: rgba(255,255,255,0.18);
            border-radius: 50%;
            margin: 0 auto 24px;
            display: flex; align-items: center; justify-content: center;
        }
        .check-icon svg { width: 36px; height: 36px; fill: white; }
        .logo-wrap {
            width: 60px; height: 60px; margin: 0 auto 12px;
            border-radius: 16px; background: rgba(255,255,255,0.12);
            display: flex; align-items: center; justify-content: center;
            overflow: hidden;
        }
        .logo-wrap img { width: 40px; height: 40px; object-fit: contain; }
        .logo-wrap svg { width: 32px; height: 32px; fill: white; }
        .venue-name { font-size: 18px; font-weight: 700; margin-bottom: 4px; opacity: 0.9; }
        .title { font-size: 28px; font-weight: 800; margin-bottom: 8px; }
        .room-badge {
            display: inline-block;
            background: rgba(255,255,255,0.18);
            border-radius: 12px;
            padding: 8px 20px;
            font-size: 18px;
            font-weight: 700;
            margin: 8px 0 20px;
        }
        .sub-text { font-size: 15px; opacity: 0.75; margin-bottom: 24px; line-height: 1.5; }
        .loader {
            border: 3px solid rgba(255,255,255,0.15);
            border-top-color: white;
            border-radius: 50%;
            width: 36px; height: 36px;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 12px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .hint { font-size: 13px; opacity: 0.5; }
    </style>
    <script>
        setTimeout(function() {
            window.location.href = "{{ $redirectUrl }}";
        }, 2000);
    </script>
</head>
<body>
    <div class="container">
        <div class="card">
            @if($logo)
            <div class="logo-wrap">
                <img src="{{ $logo }}" alt="Logo" onerror="this.remove()">
            </div>
            @endif
            <div class="venue-name">{{ $venueName }}</div>

            <div class="check-icon">
                <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
            </div>

            <div class="title">Connected!</div>

            @if($room)
            <div class="room-badge">Room {{ $room }}</div>
            @endif

            <div class="sub-text">Enjoy your stay!<br>You are now connected to the internet.</div>

            <div class="loader"></div>
            <div class="hint">Redirecting to internet...</div>
        </div>
    </div>
</body>
</html>
