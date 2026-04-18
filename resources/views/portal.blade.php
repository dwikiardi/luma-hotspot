<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $branding['name'] ?? 'Guest WiFi' }} - Luma Network</title>
    <script defer src="/js/alpine.min.js"></script>
    <script defer src="/js/fingerprintjs.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%);
        }
        .portal-container {
            max-width: 480px;
            margin: 0 auto;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .header {
            text-align: center;
            padding: 40px 20px;
        }
        .logo {
            width: 80px;
            height: 80px;
            background: {{ $branding['color'] ?? '#6366f1' }};
            border-radius: 20px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .logo img { width: 50px; height: 50px; object-fit: contain; }
        .logo svg { width: 50px; height: 50px; fill: white; }
        .venue-name {
            font-size: 28px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 8px;
        }
        .room-info {
            display: inline-block;
            background: {{ $branding['color'] ?? '#6366f1' }};
            color: white;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }
        .content {
            flex: 1;
            background: white;
            border-radius: 24px;
            padding: 32px 24px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: #6b7280;
            margin-bottom: 20px;
            text-align: center;
        }
        .btn {
            width: 100%;
            padding: 16px 24px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            transition: all 0.2s;
            text-decoration: none;
            margin-bottom: 12px;
        }
        .btn-google { background: white; border: 2px solid #e5e7eb; color: #374151; }
        .btn-google:hover { background: #f9fafb; border-color: #d1d5db; }
        .btn-wa { background: #25D366; color: white; }
        .btn-wa:hover { background: #20bd5a; }
        .btn-room { background: {{ $branding['color'] ?? '#6366f1' }}; color: white; }
        .btn-room:hover { background: #4f46e5; }
        .btn svg { width: 24px; height: 24px; }
        .form-section { margin-top: 24px; padding-top: 24px; border-top: 1px solid #e5e7eb; display: none; }
        .form-section.active { display: block; }
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 6px; }
        .form-input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 16px;
        }
        .form-input:focus { outline: none; border-color: {{ $branding['color'] ?? '#6366f1' }}; }
        .form-submit {
            width: 100%;
            padding: 14px 24px;
            background: {{ $branding['color'] ?? '#6366f1' }};
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }
        .otp-inputs { display: flex; gap: 8px; justify-content: center; margin-bottom: 16px; }
        .otp-input { width: 48px; height: 56px; text-align: center; font-size: 24px; font-weight: 600; border: 2px solid #e5e7eb; border-radius: 10px; }
        .otp-input:focus { outline: none; border-color: {{ $branding['color'] ?? '#6366f1' }}; }
        .back-btn { display: inline-flex; align-items: center; gap: 6px; color: #6b7280; font-size: 14px; cursor: pointer; margin-bottom: 16px; }
        .error-msg { background: #fef2f2; color: #dc2626; padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 16px; display: none; }
        .error-msg.show { display: block; }
        .success-msg { background: #f0fdf4; color: #16a34a; padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 16px; display: none; }
        .success-msg.show { display: block; }
        .footer { text-align: center; padding: 24px; color: #9ca3af; font-size: 12px; }
        .footer a { color: {{ $branding['color'] ?? '#6366f1' }}; text-decoration: none; }
        .spinner { width: 20px; height: 20px; border: 2px solid white; border-top-color: transparent; border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .no-methods {
            text-align: center;
            padding: 40px 20px;
            color: #6b7280;
        }
        .no-methods-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 16px;
            background: #f3f4f6;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .no-methods-icon svg {
            width: 32px;
            height: 32px;
            color: #9ca3af;
        }
        .error-box {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            margin: 20px 0;
        }
        .error-box h3 {
            color: #dc2626;
            margin-bottom: 8px;
            font-size: 18px;
        }
        .error-box p {
            color: #991b1b;
            font-size: 14px;
        }
    </style>
</head>
<body>
    @if($isCNA && $isIOS)
        @include('portal.cna_bridge')
    @else
    <div x-data="portalApp()" x-init="init()" class="portal-container">
        <header class="header">
            <div class="logo">
                @if(!empty($branding['logo']))
                    <img src="{{ $branding['logo'] }}" alt="Logo">
                @else
                    <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
                @endif
            </div>
            <h1 class="venue-name">{{ $branding['name'] ?? 'Guest WiFi' }}</h1>
            @if(!empty($relayInfo['room_number']))
                <span class="room-info">Kamar {{ $relayInfo['room_number'] }}</span>
            @endif
        </header>

        <div class="content">
            @if(!$nasId)
                <div class="error-box">
                    <h3>Akses Tidak Valid</h3>
                    <p>Silakan hubungi staff hotel atau hubungi support untuk bantuan.</p>
                </div>
            @elseif(empty($methods) || (!($methods['google'] ?? false) && !($methods['wa'] ?? false) && !($methods['room'] ?? false)))
                <div class="no-methods">
                    <div class="no-methods-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    </div>
                    <p style="font-size: 16px; font-weight: 600; color: #374151; margin-bottom: 8px;">Metode login belum dikonfigurasi</p>
                    <p style="font-size: 14px; color: #6b7280;">Silakan hubungi administrator untuk mengaktifkan metode login.</p>
                </div>
            @else
                <p class="section-title">Masuk dengan metode pilihan Anda</p>

                @if($isCNA && $isAndroid)
                    <div style="text-align: center; padding: 20px;">
                        <p style="margin-bottom: 16px; color: #374151;">Buka portal di browser untuk login.</p>
                        <button onclick="window.open('{{ url('/portal?nas_id=' . urlencode($nasId) . '&client_mac=' . urlencode($mac ?? '')) }}', '_blank')" class="btn btn-wa">Buka di Browser</button>
                    </div>
                @else
                    <div id="errorMsg" class="error-msg" x-show="error" x-text="error"></div>
                    <div id="successMsg" class="success-msg" x-show="success" x-text="success"></div>

                    @if(($methods['google'] ?? false))
                        <a href="{{ route('login.google', ['nas_id' => $nasId, 'client_mac' => $mac ?? '']) }}" class="btn btn-google">
                            <svg viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                            Masuk dengan Google
                        </a>
                    @endif

                    @if(($methods['wa'] ?? false))
                        <button @click="showWaForm = true; showRoomForm = false" class="btn btn-wa">
                            <svg viewBox="0 0 24 24" fill="white"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                            Masuk dengan WhatsApp
                        </button>
                    @endif

                    @if(($methods['room'] ?? false) && ($customLoginEnabled ?? false))
                        <button @click="showRoomForm = true; showWaForm = false" class="btn btn-room">
                            <svg viewBox="0 0 24 24" fill="white"><path d="M7 14c1.66 0 3-1.34 3-3S8.66 8 7 8s-3 1.34-3 3 1.34 3 3 3zm0-4c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm12-3h-8v8H3V5H1v15h2v-3h18v3h2v-9c0-2.21-1.79-4-4-4zm2 8h-8V9h6c1.1 0 2 .9 2 2v4z"/></svg>
                            {{ $customLoginLabel ?? 'Nomor Kamar' }}
                        </button>
                    @endif

                    <div class="form-section" :class="{ 'active': showWaForm }">
                        <div class="back-btn" @click="showWaForm = false; showRoomForm = false">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                            Kembali
                        </div>
                        
                        <div x-show="!showWaVerify">
                            <div class="form-group">
                                <label class="form-label">Nomor WhatsApp</label>
                                <input type="tel" class="form-input" placeholder="08xxxxxxxxxx" x-model="waPhone" maxlength="15">
                            </div>
                            <button @click="requestOtp()" class="form-submit" :disabled="loading">
                                <span x-show="!loading">Kirim OTP</span>
                                <span x-show="loading" class="spinner"></span>
                            </button>
                        </div>

                        <div x-show="showWaVerify">
                            <div class="form-group">
                                <label class="form-label">Masukkan kode OTP</label>
                                <div class="otp-inputs">
                                    <template x-for="(digit, index) in otpDigits" :key="index">
                                        <input type="text" maxlength="1" class="otp-input" x-model="otpDigits[index]" @input="handleOtpInput($event, index)" @keydown="handleOtpKeydown($event, index)">
                                    </template>
                                </div>
                            </div>
                            <button @click="verifyOtp()" class="form-submit" :disabled="loading">
                                <span x-show="!loading">Verifikasi</span>
                                <span x-show="loading" class="spinner"></span>
                            </button>
                        </div>
                    </div>

                    <div class="form-section" :class="{ 'active': showRoomForm }">
                        <div class="back-btn" @click="showRoomForm = false; showWaForm = false">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                            Kembali
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">{{ $customLoginLabel ?? 'Nomor Kamar' }}</label>
                            <input type="text" class="form-input" placeholder="{{ $customLoginPlaceholder ?? 'Contoh: 101' }}" x-model="roomNumber">
                        </div>
                        <button @click="loginRoom()" class="form-submit" :disabled="loading">
                            <span x-show="!loading">Masuk</span>
                            <span x-show="loading" class="spinner"></span>
                        </button>
                    </div>
                @endif
            @endif
        </div>

        <footer class="footer">
            <p>Powered by <a href="#">Luma Network</a></p>
        </footer>
    </div>

    <script>
        function portalApp() {
            return {
                showWaForm: false,
                showRoomForm: false,
                showWaVerify: false,
                waPhone: '',
                roomNumber: '',
                otpDigits: ['', '', '', '', '', ''],
                loading: false,
                error: '',
                success: '',
                fingerprint: '',
                trustScore: 50,
                fingerprintData: {},
                linkLogin: '{{ $linkLogin ?? "" }}',
                dstUrl: '{{ $dstUrl ?? "https://www.google.com" }}',

                async init() {
                    await this.collectFingerprint();
                },

                async collectFingerprint() {
                    try {
                        if (typeof FingerprintJS !== 'undefined') {
                            const fp = await FingerprintJS.load();
                            const result = await fp.get();
                            this.fingerprintData = {
                                visitor_id: result.visitorId || '',
                                screen_resolution: screen.width + 'x' + screen.height,
                                color_depth: screen.colorDepth,
                                device_memory: navigator.deviceMemory || null,
                                hardware_concurrency: navigator.hardwareConcurrency || null,
                                timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                                languages: navigator.languages ? navigator.languages.join(',') : navigator.language,
                                touch_support: 'ontouchstart' in window || navigator.maxTouchPoints > 0,
                                platform: navigator.platform || '',
                                os_name: this.getOSName(),
                                os_version: this.getOSVersion(),
                                browser_name: this.getBrowserName(),
                                browser_version: this.getBrowserVersion(),
                                user_agent: navigator.userAgent,
                                nas_id: '{{ $nasId }}',
                                mac: '{{ $mac ?? "" }}',
                            };
                            
                            const canvas = document.createElement('canvas');
                            const ctx = canvas.getContext('2d');
                            ctx.textBaseline = 'top';
                            ctx.font = '14px Arial';
                            ctx.fillText('fingerprint', 2, 2);
                            this.fingerprintData.canvas_hash = this.hashString(canvas.toDataURL());
                            
                            const glCanvas = document.createElement('canvas');
                            const gl = glCanvas.getContext('webgl') || glCanvas.getContext('experimental-webgl');
                            if (gl) {
                                const debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
                                if (debugInfo) {
                                    this.fingerprintData.webgl_vendor = gl.getParameter(debugInfo.UNMASKED_VENDOR_WEBGL) || '';
                                    this.fingerprintData.webgl_renderer = gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL) || '';
                                }
                                this.fingerprintData.webgl_hash = this.hashString(this.fingerprintData.webgl_vendor + this.fingerprintData.webgl_renderer);
                            }
                            
                            try {
                                const response = await fetch('/api/fingerprint/analyze', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify(this.fingerprintData)
                                });
                                const data = await response.json();
                                this.fingerprint = data.fingerprint_hash || result.visitorId;
                                this.trustScore = data.trust_score || 50;
                            } catch (e) {
                                this.fingerprint = result.visitorId || this.hashString(navigator.userAgent + screen.width);
                            }
                        } else {
                            this.fingerprint = this.hashString(navigator.userAgent + screen.width + screen.height);
                        }
                    } catch (e) {
                        this.fingerprint = this.hashString(navigator.userAgent + Date.now());
                    }
                },

                getOSName() {
                    const ua = navigator.userAgent;
                    if (ua.includes('Windows')) return 'Windows';
                    if (ua.includes('Mac')) return 'macOS';
                    if (ua.includes('Linux')) return 'Linux';
                    if (ua.includes('Android')) return 'Android';
                    if (ua.includes('iOS') || ua.includes('iPhone') || ua.includes('iPad')) return 'iOS';
                    return 'Unknown';
                },

                getOSVersion() {
                    const ua = navigator.userAgent;
                    const match = ua.match(/(Windows NT|Mac OS X|Android|iOS)[\s]*([\d._]+)/);
                    return match ? match[2].replace(/_/g, '.') : '';
                },

                getBrowserName() {
                    const ua = navigator.userAgent;
                    if (ua.includes('Chrome') && !ua.includes('Edg')) return 'Chrome';
                    if (ua.includes('Firefox')) return 'Firefox';
                    if (ua.includes('Safari') && !ua.includes('Chrome')) return 'Safari';
                    if (ua.includes('Edg')) return 'Edge';
                    if (ua.includes('Opera') || ua.includes('OPR')) return 'Opera';
                    return 'Unknown';
                },

                getBrowserVersion() {
                    const ua = navigator.userAgent;
                    const match = ua.match(/(Chrome|Firefox|Safari|Edge|Edg|Opera|OPR)[\/](\d+)/);
                    return match ? match[2] : '';
                },

                hashString(str) {
                    let hash = 0;
                    for (let i = 0; i < str.length; i++) {
                        const char = str.charCodeAt(i);
                        hash = ((hash << 5) - hash) + char;
                        hash = hash & hash;
                    }
                    return Math.abs(hash).toString(16);
                },

                async requestOtp() {
                    if (!this.waPhone || this.waPhone.length < 10) {
                        this.error = 'Masukkan nomor WhatsApp yang valid';
                        return;
                    }
                    this.loading = true;
                    this.error = '';
                    try {
                        const response = await fetch('/auth/wa/request', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-Fingerprint': this.fingerprint, 'X-Trust-Score': this.trustScore },
                            body: JSON.stringify({ phone: this.waPhone, nas_id: '{{ $nasId }}', client_mac: '{{ $mac ?? "" }}', link_login: this.linkLogin, dst: this.dstUrl }),
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.showWaVerify = true;
                            this.success = 'OTP berhasil dikirim';
                            setTimeout(() => this.success = '', 3000);
                        } else {
                            this.error = data.message || 'Gagal mengirim OTP';
                        }
                    } catch (e) { this.error = 'Terjadi kesalahan. Coba lagi.'; }
                    finally { this.loading = false; }
                },

                handleOtpInput(event, index) {
                    const value = event.target.value;
                    if (value && index < 5) {
                        const inputs = event.target.parentElement.children;
                        inputs[index + 1].focus();
                    }
                },

                handleOtpKeydown(event, index) {
                    if (event.key === 'Backspace' && !event.target.value && index > 0) {
                        const inputs = event.target.parentElement.children;
                        inputs[index - 1].focus();
                    }
                },

                async verifyOtp() {
                    const otp = this.otpDigits.join('');
                    if (otp.length !== 6) { this.error = 'Masukkan 6 digit OTP'; return; }
                    this.loading = true;
                    this.error = '';
                    try {
                        const response = await fetch('/auth/wa/verify', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-Fingerprint': this.fingerprint, 'X-Trust-Score': this.trustScore },
                            body: JSON.stringify({ otp: otp, nas_id: '{{ $nasId }}', client_mac: '{{ $mac ?? "" }}', link_login: this.linkLogin, dst: this.dstUrl }),
                        });
                        const data = await response.json();
                        if (data.redirect) { window.location.href = data.redirect; }
                        else if (data.success) { window.location.href = 'https://www.google.com'; }
                        else { this.error = data.message || 'OTP tidak valid'; }
                    } catch (e) { this.error = 'Terjadi kesalahan. Coba lagi.'; }
                    finally { this.loading = false; }
                },

                async loginRoom() {
                    if (!this.roomNumber) { this.error = 'Masukkan nomor kamar'; return; }
                    this.loading = true;
                    this.error = '';
                    try {
                        const response = await fetch('/auth/room', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-Fingerprint': this.fingerprint, 'X-Trust-Score': this.trustScore },
                            body: JSON.stringify({ room_number: this.roomNumber, nas_id: '{{ $nasId }}', client_mac: '{{ $mac ?? "" }}', link_login: this.linkLogin, dst: this.dstUrl }),
                        });
                        const data = await response.json();
                        if (data.redirect) { window.location.href = data.redirect; }
                        else if (data.success) { window.location.href = 'https://www.google.com'; }
                        else { this.error = data.message || 'Login gagal'; }
                    } catch (e) { this.error = 'Terjadi kesalahan. Coba lagi.'; }
                    finally { this.loading = false; }
                },
            }
        }
    </script>
    @endif
</body>
</html>