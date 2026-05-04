<div class="filament-widget">
    <div class="p-4 bg-white rounded-lg shadow dark:bg-gray-800">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                🖥️ Hotspot MikroTik
            </h2>
            <span class="px-3 py-1 text-sm font-medium rounded-full {{ $totalActive > 0 ? 'bg-success-100 text-success-700 dark:bg-success-900 dark:text-success-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}">
                {{ $totalActive }} online
            </span>
        </div>

        @if(count($routers) === 0)
            <p class="text-sm text-gray-500 dark:text-gray-400">Tidak ada router terkonfigurasi.</p>
        @else
            @foreach($routers as $router)
                <div class="mb-4 last:mb-0 p-3 border rounded-lg {{ $router['online'] >= 0 ? 'border-gray-200 dark:border-gray-700' : 'border-danger-300 dark:border-danger-700' }}">
                    <div class="flex items-center justify-between mb-2">
                        <div>
                            <span class="font-medium text-sm text-gray-900 dark:text-white">{{ $router['name'] }}</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400 ml-2">({{ $router['nas_id'] }})</span>
                        </div>
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $router['ip'] }}</span>
                    </div>

                    @if($router['error'])
                        <div class="p-2 bg-danger-50 dark:bg-danger-900/20 rounded text-xs text-danger-700 dark:text-danger-300">
                            ⚠️ {{ $router['error'] }}
                        </div>
                    @elseif(count($router['users']) > 0)
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead>
                                    <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                                        <th class="py-1 pr-2">User</th>
                                        <th class="py-1 pr-2">IP</th>
                                        <th class="py-1">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($router['users'] as $user)
                                        <tr class="border-b border-gray-100 dark:border-gray-800 last:border-0">
                                            <td class="py-1 pr-2 font-medium text-gray-900 dark:text-white">{{ $user['user'] }}</td>
                                            <td class="py-1 pr-2 text-gray-600 dark:text-gray-400">{{ $user['address'] ?? '-' }}</td>
                                            <td class="py-1">
                                                <button onclick="disconnectMikroTik('{{ $router['nas_id'] }}', '{{ $user['user'] }}')"
                                                        class="px-2 py-0.5 text-xs bg-danger-100 text-danger-700 dark:bg-danger-900/30 dark:text-danger-300 rounded hover:bg-danger-200 dark:hover:bg-danger-900/50 transition">
                                                    Putuskan
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-xs text-gray-500 dark:text-gray-400">Tidak ada user aktif di router ini.</p>
                    @endif
                </div>
            @endforeach
        @endif
    </div>

    <script>
        function disconnectMikroTik(nasId, username) {
            if (!confirm('Putuskan user ' + username + ' dari hotspot?')) return;
            fetch('/tenant/mikrotik/disconnect', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ nas_id: nasId, username: username })
            })
            .then(r => r.json())
            .then(d => { if(d.success) location.reload(); else alert(d.message); })
            .catch(() => alert('Gagal'));
        }
    </script>
</div>