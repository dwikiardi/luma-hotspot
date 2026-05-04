<div class="space-y-6">
    <div class="bg-gradient-to-r from-violet-600 to-indigo-600 rounded-2xl p-8 text-white">
        <p class="text-sm opacity-80">Total pengguna WiFi</p>
        <h1 class="text-5xl font-bold mt-1">{{ number_format($totalUsers, 0, '.', '.') }}</h1>
        <p class="text-sm opacity-80 mt-2">{{ number_format($uniqueUsers7d, 0, '.', '.') }} unik dalam 7 hari</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-lg shadow p-6 dark:bg-gray-800">
            <h3 class="text-gray-500 text-sm font-medium dark:text-gray-400">Total Sesi</h3>
            <p class="text-3xl font-bold text-gray-900 dark:text-white mt-2">{{ number_format($totalSessions, 0, '.', '.') }}</p>
            <p class="text-gray-500 text-xs mt-2 dark:text-gray-400">{{ $sessionsToday }} hari ini</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6 dark:bg-gray-800">
            <h3 class="text-gray-500 text-sm font-medium dark:text-gray-400">Online Sekarang</h3>
            <p class="text-3xl font-bold text-green-600 dark:text-green-400 mt-2">{{ $activeNow }}</p>
            <p class="text-gray-500 text-xs mt-2 dark:text-gray-400">{{ $inGrace }} dalam grace period</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6 dark:bg-gray-800">
            <h3 class="text-gray-500 text-sm font-medium dark:text-gray-400">Repeat MAC</h3>
            <p class="text-3xl font-bold text-red-600 dark:text-red-400 mt-2">{{ $repeatMacCount }}</p>
            <p class="text-gray-500 text-xs mt-2 dark:text-gray-400">device yg login &gt; 1 kali</p>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6 dark:bg-gray-800">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">🔁 MAC yang Sama Login Berulang</h3>
        @if($repeatMacCount > 0)
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">{{ $repeatMacCount }} MAC unik login > 1 kali</p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 dark:text-gray-400 border-b">
                            <th class="py-2 pr-4">MAC Address</th>
                            <th class="py-2 pr-4">Login Count</th>
                            <th class="py-2">User Terkait</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($repeatMacs as $item)
                        <tr class="border-b border-gray-100 dark:border-gray-700">
                            <td class="py-2 pr-4 font-mono text-xs text-gray-900 dark:text-white">{{ $item['mac'] }}</td>
                            <td class="py-2 pr-4">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium 
                                    {{ $item['count'] > 5 ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300' : 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300' }}">
                                    {{ $item['count'] }}x
                                </span>
                            </td>
                            <td class="py-2 text-gray-600 dark:text-gray-400">
                                {{ implode(', ', $item['users']) }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-gray-500 dark:text-gray-400 text-sm">Belum ada data MAC berulang.</p>
        @endif
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-white rounded-lg shadow p-6 dark:bg-gray-800">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">👤 Tipe Identitas User</h3>
            <div class="space-y-3">
                @php
                    $types = $identityTypes;
                    $totalType = array_sum($types) ?: 1;
                    $labels = ['room' => 'Kamar', 'google' => 'Google', 'wa' => 'WhatsApp', 'email' => 'Email'];
                    $colors = ['room' => '#6366f1', 'google' => '#4285F4', 'wa' => '#25D366', 'email' => '#f59e0b'];
                @endphp
                @foreach($types as $key => $val)
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-600 dark:text-gray-400">{{ $labels[$key] ?? $key }}</span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ $val }}</span>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                        <div class="h-2 rounded-full" style="width: {{ round($val / $totalType * 100) }}%; background: {{ $colors[$key] ?? '#6b7280' }}"></div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6 dark:bg-gray-800">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">📊 Ringkasan Grace Period</h3>
            <div class="space-y-4">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Dalam Grace Period</p>
                    <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ $inGracePeriod }}</p>
                    <p class="text-xs text-gray-400">User yang bisa auto-reconnect tanpa login ulang</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Repeat MAC</p>
                    <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $repeatMacCount }}</p>
                    <p class="text-xs text-gray-400">Device yg sama login berkali-kali (kemungkinan random MAC)</p>
                </div>
            </div>
        </div>
    </div>
</div>