<div class="bg-white rounded-xl shadow border border-gray-200 p-4">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
            <div class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></div>
            <h3 class="font-semibold text-gray-900">Sesi Aktif</h3>
        </div>
        <span class="text-sm text-gray-500">
            {{ number_format($total) }} user online
        </span>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50">
                    <th class="text-left py-2 px-3 font-medium text-gray-600 text-xs uppercase">User</th>
                    <th class="text-left py-2 px-3 font-medium text-gray-600 text-xs uppercase">Router</th>
                    <th class="text-left py-2 px-3 font-medium text-gray-600 text-xs uppercase">MAC</th>
                    <th class="text-left py-2 px-3 font-medium text-gray-600 text-xs uppercase">IP</th>
                    <th class="text-left py-2 px-3 font-medium text-gray-600 text-xs uppercase">Login</th>
                    <th class="text-left py-2 px-3 font-medium text-gray-600 text-xs uppercase">Durasi</th>
                    <th class="text-right py-2 px-3 font-medium text-gray-600 text-xs uppercase">Traffic</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sessions as $session)
                <tr class="border-b border-gray-50 hover:bg-gray-50">
                    <td class="py-2 px-3 font-medium text-gray-900">{{ $session->username }}</td>
                    <td class="py-2 px-3 text-gray-600">{{ $session->nasipaddress }}</td>
                    <td class="py-2 px-3 text-gray-500 font-mono text-xs">{{ $session->mac }}</td>
                    <td class="py-2 px-3 text-gray-600 font-mono text-xs">{{ $session->client_ip }}</td>
                    <td class="py-2 px-3 text-gray-500">{{ \Carbon\Carbon::parse($session->login_at)->format('H:i') }}</td>
                    <td class="py-2 px-3 text-gray-500">{{ $session->duration }}</td>
                    <td class="py-2 px-3 text-right text-gray-600">{{ $session->traffic }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="py-8 text-center text-gray-400">Tidak ada sesi aktif</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>