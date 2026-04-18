<div class="space-y-4">
    <div class="flex items-center justify-between">
        <p class="text-sm text-gray-600">Klik tombol Copy, lalu paste ke Terminal MikroTik</p>
        <button type="button" onclick="copyMikrotikScript()"
            class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>
            </svg>
            Copy Script
        </button>
    </div>

    <pre id="mikrotik-script-display" class="font-mono text-sm bg-slate-900 text-green-400 p-4 rounded-lg whitespace-pre overflow-x-auto">{{ $script_content }}</pre>

    <script>
        function copyMikrotikScript() {
            const script = document.getElementById('mikrotik-script-display').innerText;
            navigator.clipboard.writeText(script).then(() => {
                const btn = event.target.closest('button');
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Copied!';
                btn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                btn.classList.add('bg-green-600');
                setTimeout(() => {
                    btn.innerHTML = originalHTML;
                    btn.classList.remove('bg-green-600');
                    btn.classList.add('bg-blue-600', 'hover:bg-blue-700');
                }, 2000);
            });
        }
    </script>
</div>
