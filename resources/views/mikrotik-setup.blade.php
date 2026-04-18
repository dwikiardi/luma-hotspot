---
layout: default
title: MikroTik Setup - Luma Network
---

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup MikroTik - Luma Network</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        body { font-family: "Inter", sans-serif; }
        pre { 
            background: #1e293b; 
            color: #e2e8f0; 
            padding: 1rem;
            border-radius: 0.5rem;
            overflow-x: auto;
        }
        .copy-btn:active { transform: scale(0.95); }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-900 to-slate-800 min-h-screen">
    <div class="max-w-4xl mx-auto px-4 py-12">
        <!-- Header -->
        <div class="text-center mb-10">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-500 rounded-2xl mb-4">
                <i class="ri-router-line text-3xl text-white"></i>
            </div>
            <h1 class="text-3xl font-bold text-white mb-2">Setup MikroTik</h1>
            <p class="text-slate-400">Copy paste script di bawah ke Terminal MikroTik Anda</p>
        </div>

        <!-- Form Input -->
        <div class="bg-slate-800 rounded-2xl p-6 mb-8 border border-slate-700">
            <h2 class="text-lg font-semibold text-white mb-4">Konfigurasi</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm text-slate-400 mb-2">NAS Identifier / System Identity</label>
                    <input type="text" id="nasId" value="" placeholder="hotel-lobby-01"
                        class="w-full bg-slate-700 border border-slate-600 rounded-lg px-4 py-3 text-white placeholder-slate-400 focus:outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm text-slate-400 mb-2">IP Server Luma</label>
                    <input type="text" id="serverIp" value="103.137.140.6" placeholder="103.137.140.6"
                        class="w-full bg-slate-700 border border-slate-600 rounded-lg px-4 py-3 text-white placeholder-slate-400 focus:outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm text-slate-400 mb-2">RADIUS Secret</label>
                    <input type="text" id="radiusSecret" value="luma_radius_secret" placeholder="luma_radius_secret"
                        class="w-full bg-slate-700 border border-slate-600 rounded-lg px-4 py-3 text-white placeholder-slate-400 focus:outline-none focus:border-blue-500">
                </div>
            </div>
        </div>

        <!-- Complete Script -->
        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 rounded-2xl p-6 mb-6 border border-blue-500">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-bold text-white">
                    <i class="ri-checkbox-circle-line mr-2"></i>
                    Script Lengkap (Copy All)
                </h2>
                <button onclick="copyAll()" id="copyAllBtn" class="bg-white/20 hover:bg-white/30 text-white font-medium py-2 px-4 rounded-lg transition flex items-center gap-2">
                    <i class="ri-file-copy-line"></i>
                    <span>Copy All</span>
                </button>
            </div>
            <p class="text-blue-100 text-sm mb-4">Jalankan semua command sekaligus. Paste di Terminal MikroTik.</p>
            <pre id="complete-script" class="bg-slate-900/50 text-green-400"># Isi NAS Identifier di form atas untuk generate script</pre>
        </div>

        <!-- Instructions -->
        <div class="bg-slate-800 rounded-2xl p-6 mb-6 border border-slate-700">
            <h2 class="text-lg font-semibold text-white mb-4">
                <i class="ri-information-line text-blue-400 mr-2"></i>
                Cara Penggunaan
            </h2>
            <ol class="space-y-3 text-slate-300">
                <li class="flex items-start gap-3">
                    <span class="flex-shrink-0 w-6 h-6 bg-blue-600 rounded-full flex items-center justify-center text-sm font-bold">1</span>
                    <span>Buka Winbox atau SSH ke MikroTik router Anda</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="flex-shrink-0 w-6 h-6 bg-blue-600 rounded-full flex items-center justify-center text-sm font-bold">2</span>
                    <span>Buka menu <strong>System > Terminal</strong></span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="flex-shrink-0 w-6 h-6 bg-blue-600 rounded-full flex items-center justify-center text-sm font-bold">3</span>
                    <span>Klik tombol <strong>"Copy All"</strong> di atas</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="flex-shrink-0 w-6 h-6 bg-blue-600 rounded-full flex items-center justify-center text-sm font-bold">4</span>
                    <span>Paste di Terminal (Ctrl+V atau klik kanan > Paste)</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="flex-shrink-0 w-6 h-6 bg-blue-600 rounded-full flex items-center justify-center text-sm font-bold">5</span>
                    <span>Tekan <strong>Enter</strong> untuk jalankan semua command</span>
                </li>
            </ol>
        </div>

        <!-- Footer -->
        <div class="text-center mt-10 text-slate-500 text-sm">
            <p>Butuh bantuan? <a href="mailto:support@luma.id" class="text-blue-400 hover:underline">support@luma.id</a></p>
        </div>
    </div>

    <script>
        function generateScript() {
            const nasId = document.getElementById('nasId').value || 'YOUR_NAS_ID';
            const serverIp = document.getElementById('serverIp').value || '103.137.140.6';
            const radiusSecret = document.getElementById('radiusSecret').value || 'luma_radius_secret';

            return `# MikroTik Configuration - Luma Network
/system identity
set name="${nasId}"
/radius
add service=hotspot address=${serverIp} secret=${radiusSecret} authentication-port=1812 accounting-port=1813
/ip hotspot profile
set [find default=yes] use-radius=yes radius-accounting=yes nas-port-type=wireless-802.11 radius-interim-update=5m
/ip hotspot walled-garden ip
add dst-address=${serverIp} action=accept
add dst-host=*.lumanetwork.id action=accept
add dst-host=*.google.com action=accept
add dst-host=*.facebook.com action=accept
add dst-host=*.apple.com action=accept`;
        }

        function updateDisplay() {
            const script = generateScript();
            document.getElementById('complete-script').textContent = script;
        }

        function copyAll() {
            const nasId = document.getElementById('nasId').value;
            if (!nasId) {
                alert('Isi NAS Identifier terlebih dahulu');
                return;
            }
            const script = generateScript();
            
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(script).then(() => {
                    showCopySuccess();
                }).catch(() => {
                    fallbackCopy(script);
                });
            } else {
                fallbackCopy(script);
            }
        }

        function fallbackCopy(text) {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            showCopySuccess();
        }

        function showCopySuccess() {
            const btn = document.getElementById('copyAllBtn');
            btn.innerHTML = '<i class="ri-check-line"></i><span>Copied!</span>';
            btn.classList.add('bg-green-600');
            setTimeout(() => {
                btn.innerHTML = '<i class="ri-file-copy-line"></i><span>Copy All</span>';
                btn.classList.remove('bg-green-600');
            }, 2000);
        }

        // Update script on input change
        document.getElementById('nasId').addEventListener('input', updateDisplay);
        document.getElementById('serverIp').addEventListener('input', updateDisplay);
        document.getElementById('radiusSecret').addEventListener('input', updateDisplay);

        // Initial update
        updateDisplay();
    </script>
</body>
</html>
