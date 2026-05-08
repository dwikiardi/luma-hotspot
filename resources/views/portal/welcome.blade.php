<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>{{ $venueName }} - WiFi</title>
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
        .container {
            max-width: 420px;
            width: 100%;
            text-align: center;
        }
        .card {
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 28px;
            padding: 40px 28px 32px;
            box-shadow: 0 30px 60px rgba(0,0,0,0.2);
        }
        .logo-wrap {
            width: 80px;
            height: 80px;
            margin: 0 auto 16px;
            border-radius: 22px;
            background: rgba(255,255,255,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(10px);
            overflow: hidden;
        }
        .logo-wrap img {
            width: 52px;
            height: 52px;
            object-fit: contain;
        }
        .logo-wrap svg {
            width: 42px;
            height: 42px;
            fill: white;
        }
        .venue-name {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -0.3px;
            margin-bottom: 4px;
            opacity: 0.95;
        }
        .welcome-text {
            font-size: 32px;
            font-weight: 800;
            letter-spacing: -0.5px;
            margin: 20px 0 6px;
            line-height: 1.2;
        }
        .sub-text {
            font-size: 15px;
            opacity: 0.75;
            margin-bottom: 28px;
            line-height: 1.5;
        }
        .info-row {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255,255,255,0.1);
            border-radius: 14px;
            padding: 12px 16px;
            margin-bottom: 10px;
            font-size: 14px;
            text-align: left;
        }
        .info-row svg { width: 20px; height: 20px; flex-shrink: 0; opacity: 0.7; }
        .consent-box {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 16px;
            padding: 16px;
            margin: 20px 0 12px;
            text-align: left;
        }
        .consent-box label {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 13px;
            line-height: 1.5;
            opacity: 0.85;
            cursor: pointer;
        }
        .consent-box input[type=checkbox] {
            width: 20px;
            height: 20px;
            margin-top: 1px;
            accent-color: white;
            flex-shrink: 0;
        }
        .consent-box a {
            color: white;
            opacity: 0.9;
            text-decoration: underline;
        }
        .terms-box {
            background: rgba(255,255,255,0.06);
            border-radius: 14px;
            padding: 14px;
            margin-bottom: 20px;
            max-height: 140px;
            overflow-y: auto;
            font-size: 11px;
            line-height: 1.6;
            text-align: left;
            opacity: 0.7;
            display: none;
        }
        .terms-box.show { display: block; }
        .terms-box h4 { font-size: 13px; margin-bottom: 8px; opacity: 0.9; }
        .btn {
            display: block;
            width: 100%;
            padding: 16px;
            background: white;
            color: {{ $color }};
            border: none;
            border-radius: 16px;
            font-size: 17px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.15s;
            text-decoration: none;
            text-align: center;
        }
        .btn:active { transform: scale(0.97); }
        .btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        .footer-links {
            margin-top: 16px;
            font-size: 13px;
            opacity: 0.6;
        }
        .footer-links a {
            color: white;
            text-decoration: none;
            margin: 0 8px;
        }
        .highlight { font-weight: 700; opacity: 1; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            {{-- Logo --}}
            <div class="logo-wrap">
                @if($logo)
                    <img src="{{ $logo }}" alt="Logo" onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
                    <svg viewBox="0 0 24 24" style="display:none"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
                @else
                    <svg viewBox="0 0 24 24"><path d="M1 9l2 2c4.97-4.97 13.03-4.97 18 0l2-2C17.93 4.07 6.07 4.07 1 9zm8 8l3 3 3-3c-1.65-1.66-4.34-1.66-6 0zm-6-6l2 2c2.76-2.76 7.24-2.76 10 0l2-2C13.14 7.14 5.86 7.14 3 11z"/></svg>
                @endif
            </div>

            {{-- Venue Name --}}
            <div class="venue-name">{{ $venueName }}</div>

            {{-- Welcome --}}
            <div class="welcome-text">Welcome to<br>WiFi 2.0</div>
            <div class="sub-text">Free · Secure · Seamless</div>

            {{-- Info --}}
            <div class="info-row">
                <svg viewBox="0 0 24 24" fill="white"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                <span>No passwords. Just tap Connect.</span>
            </div>
            <div class="info-row">
                <svg viewBox="0 0 24 24" fill="white"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94L14.4 2.81c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41L9.25 5.35c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.07.62-.07.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>
                <span>One tap auto-login forever</span>
            </div>

            {{-- Room Number --}}
            <div class="room-input" style="margin-bottom: 14px;">
                <input type="text" id="roomInput" placeholder="Enter your room number (e.g. 101)" 
                    style="width:100%;padding:14px 16px;border-radius:14px;border:1px solid rgba(255,255,255,0.3);background:rgba(255,255,255,0.1);color:#fff;font-size:16px;outline:none;text-align:center"
                    oninput="toggleBtn()">
            </div>

            {{-- Cookie & T&C Consent --}}
            <div class="consent-box">
                <label>
                    <input type="checkbox" id="consentCheck" onchange="toggleBtn()">
                    <span>
                        I agree to the <a href="#" onclick="document.getElementById('termsBox').classList.toggle('show');event.preventDefault()">Terms &amp; Conditions</a>
                        and <a href="#" onclick="document.getElementById('termsBox').classList.toggle('show');event.preventDefault()">Privacy Policy</a>.
                        This site uses cookies for auto-login.
                    </span>
                </label>
            </div>

            {{-- Error --}}
            <div id="errorMsg" style="display:none;background:rgba(255,80,80,0.2);border-radius:12px;padding:10px;margin-bottom:12px;font-size:14px;color:#ffb3b3"></div>

            {{-- Loading --}}
            <div id="loadingMsg" style="display:none;margin-bottom:12px;font-size:14px;opacity:0.8">Creating your connection...</div>

            {{-- Connect Button --}}
            <button onclick="connectRoom()" class="btn" id="connectBtn" disabled style="display:block">
                Connect to Internet
            </button>

            {{-- Footer --}}
            <div class="footer-links">
                <a href="#" onclick="document.getElementById('termsBox').classList.toggle('show');event.preventDefault()">Terms</a>·
                <a href="#">Privacy</a>·
                <span>Powered by Luma</span>
            </div>
        </div>
    </div>

    <script>
        function toggleBtn() {
            var consent = document.getElementById('consentCheck').checked;
            var room = document.getElementById('roomInput').value.trim();
            document.getElementById('connectBtn').disabled = !consent || !room;
        }

        function connectRoom() {
            var room = document.getElementById('roomInput').value.trim();
            if (!room) return;

            var btn = document.getElementById('connectBtn');
            var err = document.getElementById('errorMsg');
            var load = document.getElementById('loadingMsg');
            btn.disabled = true;
            btn.textContent = 'Connecting...';
            load.style.display = 'block';
            err.style.display = 'none';

            var fingerprint = '';
            try {
                if (window.fingerprintData) fingerprint = JSON.parse(window.fingerprintData || '{}');
            } catch(e) {}

            fetch('/cna-login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Fingerprint': window.fingerprint || '' },
                body: JSON.stringify({
                    room_number: room,
                    nas_id: '{{ $nasId ?? '' }}',
                    client_mac: '{{ $mac ?? '' }}',
                    link_login: '{{ $linkLogin ?? '' }}',
                    dst: '{{ $dstUrl ?? '' }}',
                    fingerprint_data: window.fingerprintData || '{}'
                })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success && data.connect_url) {
                    window.location.href = data.connect_url;
                } else {
                    err.textContent = data.message || 'Room number not recognized';
                    err.style.display = 'block';
                    btn.disabled = false;
                    btn.textContent = 'Connect to Internet';
                    load.style.display = 'none';
                }
            })
            .catch(function() {
                err.textContent = 'Connection error. Please try again.';
                err.style.display = 'block';
                btn.disabled = false;
                btn.textContent = 'Connect to Internet';
                load.style.display = 'none';
            });
        }
    </script>
</body>
</html>
