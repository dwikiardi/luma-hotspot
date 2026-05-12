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
            -webkit-font-smoothing: antialiased;
        }
        .container {
            max-width: 400px;
            width: 100%;
            text-align: center;
        }
        .card {
            position: relative;
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 32px;
            padding: 36px 24px 28px;
            box-shadow: 0 32px 64px rgba(0,0,0,0.25);
        }
        .logo-wrap {
            width: 72px;
            height: 72px;
            margin: 0 auto 12px;
            border-radius: 20px;
            background: rgba(255,255,255,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .logo-wrap img { width: 44px; height: 44px; object-fit: contain; }
        .logo-wrap svg { width: 36px; height: 36px; fill: white; }
        .venue-name {
            font-size: 17px;
            font-weight: 600;
            letter-spacing: -0.2px;
            opacity: 0.9;
            margin-bottom: 20px;
        }
        .title {
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.3px;
            margin-bottom: 4px;
            line-height: 1.2;
        }
        .subtitle {
            font-size: 15px;
            opacity: 0.7;
            margin-bottom: 24px;
            line-height: 1.5;
        }
        .room-input {
            margin-bottom: 12px;
        }
        .room-input input {
            width: 100%;
            padding: 16px;
            border-radius: 16px;
            border: 1.5px solid rgba(255,255,255,0.2);
            background: rgba(255,255,255,0.08);
            color: #fff;
            font-size: 20px;
            font-weight: 600;
            text-align: center;
            outline: none;
            transition: border-color 0.2s;
            letter-spacing: 1px;
        }
        .room-input input::placeholder {
            color: rgba(255,255,255,0.4);
            font-weight: 400;
            font-size: 16px;
            letter-spacing: 0;
        }
        .room-input input:focus {
            border-color: rgba(255,255,255,0.5);
        }
        .consent-box {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 14px;
            padding: 14px;
            margin: 16px 0;
            text-align: left;
        }
        .consent-box label {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 13px;
            line-height: 1.5;
            opacity: 0.8;
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
            text-decoration: underline;
            opacity: 0.9;
        }
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
            opacity: 0.35;
            transform: none;
            cursor: not-allowed;
        }
        .btn-loading {
            opacity: 0.7;
            pointer-events: none;
        }
        .error-msg {
            display: none;
            background: rgba(255,80,80,0.2);
            border-radius: 12px;
            padding: 10px 14px;
            margin-bottom: 12px;
            font-size: 14px;
            color: #ffb3b3;
            text-align: center;
        }
        .features {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            justify-content: center;
        }
        .feature {
            flex: 1;
            background: rgba(255,255,255,0.06);
            border-radius: 12px;
            padding: 10px 8px;
            font-size: 12px;
            opacity: 0.75;
            line-height: 1.3;
        }
        .feature svg {
            width: 18px;
            height: 18px;
            fill: white;
            display: block;
            margin: 0 auto 4px;
            opacity: 0.8;
        }
        .powered {
            margin-top: 16px;
            font-size: 12px;
            opacity: 0.4;
            letter-spacing: 0.3px;
        }
    </style>
    <script>
    var fpPromise = null;
    try {
        fpPromise = import('https://openfpcdn.io/fingerprintjs/v5')
            .then(function(FingerprintJS) { return FingerprintJS.load(); });
    } catch(e) { console.log('FPJS import failed:', e); }
    </script>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="logo-wrap">
                @if($logo)
                    <img src="{{ $logo }}" alt="Logo" onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
                    <svg viewBox="0 0 24 24" style="display:none"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
                @else
                    <svg viewBox="0 0 24 24"><path d="M1 9l2 2c4.97-4.97 13.03-4.97 18 0l2-2C17.93 4.07 6.07 4.07 1 9zm8 8l3 3 3-3c-1.65-1.66-4.34-1.66-6 0zm-6-6l2 2c2.76-2.76 7.24-2.76 10 0l2-2C13.14 7.14 5.86 7.14 3 11z"/></svg>
                @endif
            </div>

            <div class="venue-name">{{ $venueName }}</div>

            <div class="title">Selamat Datang</div>
            <div class="subtitle">Masukkan nomor kamar untuk mengakses WiFi</div>

            <div class="features">
                <div class="feature">
                    <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                    Tanpa Password
                </div>
                <div class="feature">
                    <svg viewBox="0 0 24 24"><path d="M12 1C8.14 1 5 4.14 5 8c0 4.13 5 11 7 13s7-8.87 7-13c0-3.86-3.14-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                    Auto-Konek Kembali
                </div>
            </div>

            <div class="room-input">
                <input type="text" id="roomInput" inputmode="numeric" pattern="[0-9]*"
                    placeholder="Nomor Kamar" autocomplete="off"
                    oninput="toggleBtn()">
            </div>

            <div class="consent-box">
                <label>
                    <input type="checkbox" id="consentCheck" onchange="toggleBtn()">
                    <span>
                        Saya setuju dengan <a href="#" onclick="document.getElementById('termsBox').classList.toggle('show');event.preventDefault()">Syarat &amp; Ketentuan</a>
                        dan <a href="#" onclick="document.getElementById('termsBox').classList.toggle('show');event.preventDefault()">Kebijakan Privasi</a>.
                        Kuki digunakan untuk auto-login.
                    </span>
                </label>
            </div>

            <div id="termsBox" class="terms-box">
                <h4>Syarat & Ketentuan</h4>
                <p>Dengan mengakses WiFi ini, Anda menyetujui bahwa penggunaan internet Anda tunduk pada kebijakan hotel. Akses diberikan untuk tamu yang terdaftar. Aktivitas ilegal dilarang.</p>
            </div>

            <div id="errorMsg" class="error-msg"></div>

            <button onclick="connectRoom()" class="btn" id="connectBtn" disabled>
                Hubungkan
            </button>

            <div class="powered">LUMA NETWORK</div>

            <div id="autoCheckOverlay" class="auto-check-overlay">
                <div class="spinner" style="width:28px;height:28px;margin:0 auto 16px"></div>
                <p style="opacity:0.6;font-size:14px">Memeriksa perangkat...</p>
            </div>
        </div>
    </div>

    <style>
        .terms-box {
            display: none;
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 12px;
            font-size: 11px;
            line-height: 1.6;
            text-align: left;
            opacity: 0.65;
            max-height: 100px;
            overflow-y: auto;
        }
        .terms-box.show { display: block; }
        .terms-box h4 { font-size: 12px; margin-bottom: 4px; opacity: 0.8; }
        .spinner {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 2px solid {{ $color }};
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
            vertical-align: middle;
            margin-right: 6px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .auto-check-overlay {
            position: absolute;
            inset: 0;
            border-radius: 32px;
            background: inherit;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 10;
            padding: 24px;
            text-align: center;
            backdrop-filter: blur(4px);
        }
        .auto-check-overlay.hidden { display: none; }
    </style>

    <script>
        // --- Global state ---
        var fpVisitorId = '';
        var fpComponents = '{}';

        // --- Auto-check: kenali returning device via browser fingerprint ---
        (function() {
            var overlay = document.getElementById('autoCheckOverlay');

            function hideOverlay() {
                if (overlay) overlay.classList.add('hidden');
            }

            function runAutoCheck(fingerprint) {
                fpVisitorId = fingerprint;

                fetch('/api/fingerprint/auto-check', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        fingerprint: fingerprint,
                        nas_id: '{{ $nasId ?? '' }}',
                        client_mac: '{{ $mac ?? '' }}',
                        link_login: '{{ $linkLogin ?? '' }}',
                        dst: '{{ $dstUrl ?? '' }}'
                    })
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.match && data.connect_url) {
                        window.location.href = data.connect_url;
                    } else {
                        hideOverlay();
                    }
                })
                .catch(function() { hideOverlay(); });

                // Safety: hide overlay if fetch hangs
                setTimeout(hideOverlay, 5000);
            }

            function fallbackFp() {
                var ua = navigator.userAgent.replace(/[^a-z0-9]/gi,'').substring(0,20);
                var sr = (window.screen.width || '') + 'x' + (window.screen.height || '');
                var tz = Intl.DateTimeFormat ? Intl.DateTimeFormat().resolvedOptions().timeZone || '' : '';
                return 'fp-ua-' + ua + '-' + btoa(sr + tz).replace(/[^a-z0-9]/gi,'').substring(0,10);
            }

            // Master safety: hide overlay after max 6s whatever happens
            var masterTimer = setTimeout(hideOverlay, 6000);

            if (fpPromise) {
                fpPromise.then(function(fp) { return fp.get(); }).then(function(result) {
                    clearTimeout(masterTimer);
                    fpVisitorId = result.visitorId;
                    fpComponents = JSON.stringify(result.components || {});
                    runAutoCheck(fpVisitorId);
                }).catch(function() {
                    clearTimeout(masterTimer);
                    try { runAutoCheck(fallbackFp()); } catch(e) { hideOverlay(); }
                });
            } else {
                clearTimeout(masterTimer);
                try { runAutoCheck(fallbackFp()); } catch(e) { hideOverlay(); }
            }
        })();

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
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span> Menghubungkan...';
            err.style.display = 'none';

            fetch('/cna-login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Fingerprint': fpVisitorId || '' },
                body: JSON.stringify({
                    room_number: room,
                    nas_id: '{{ $nasId ?? '' }}',
                    client_mac: '{{ $mac ?? '' }}',
                    link_login: '{{ $linkLogin ?? '' }}',
                    dst: '{{ $dstUrl ?? '' }}',
                    fingerprint_data: fpComponents || '{}'
                })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success && data.connect_url) {
                    window.location.href = data.connect_url;
                } else {
                    err.textContent = data.message || 'Nomor kamar tidak dikenal';
                    err.style.display = 'block';
                    btn.innerHTML = 'Hubungkan';
                    btn.disabled = false;
                }
            })
            .catch(function() {
                err.textContent = 'Gagal terhubung. Silakan coba lagi.';
                err.style.display = 'block';
                btn.innerHTML = 'Hubungkan';
                btn.disabled = false;
            });
        }
    </script>
</body>
</html>
