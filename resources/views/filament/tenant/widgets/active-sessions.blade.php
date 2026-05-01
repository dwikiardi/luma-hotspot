<div class="bg-white rounded-xl shadow border border-gray-200 p-4">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
            <div class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></div>
            <h3 class="font-semibold text-gray-900">Pengunjung Online</h3>
        </div>
        <span class="text-sm text-gray-500">
            {{ $total }} user online
        </span>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50">
                    <th class="text-left py-2 px-3 font-medium text-gray-600 text-xs uppercase">Nama</th>
                    <th class="text-left py-2 px-3 font-medium text-gray-600 text-xs uppercase">User</th>
                    <th class="text-left py-2 px-3 font-medium text-gray-600 text-xs uppercase">Metode</th>
                    <th class="text-left py-2 px-3 font-medium text-gray-600 text-xs uppercase">MAC</th>
                    <th class="text-left py-2 px-3 font-medium text-gray-600 text-xs uppercase">IP</th>
                    <th class="text-left py-2 px-3 font-medium text-gray-600 text-xs uppercase">Router</th>
                    <th class="text-left py-2 px-3 font-medium text-gray-600 text-xs uppercase">Login</th>
                    <th class="text-left py-2 px-3 font-medium text-gray-600 text-xs uppercase">Durasi</th>
                    <th class="text-left py-2 px-3 font-medium text-gray-600 text-xs uppercase">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sessions as $session)
                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                        <td class="py-2 px-3">{{ $session['name'] }}</td>
                        <td class="py-2 px-3 font-mono text-xs">{{ $session['identity'] }}</td>
                        <td class="py-2 px-3">
                            @switch($session['method'])
                                @case('google')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Google</span>
                                    @break
                                @case('wa')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">WhatsApp</span>
                                    @break
                                @case('room')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">Kamar</span>
                                    @break
                                @default
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">{{ $session['method'] }}</span>
                            @endswitch
                        </td>
                        <td class="py-2 px-3 font-mono text-xs text-gray-500">{{ $session['mac'] }}</td>
                        <td class="py-2 px-3 font-mono text-xs text-gray-500">{{ $session['ip'] }}</td>
                        <td class="py-2 px-3 text-xs">{{ $session['router'] }}</td>
                        <td class="py-2 px-3 text-xs">{{ $session['login_at'] }}</td>
                        <td class="py-2 px-3 text-xs">{{ $session['duration'] }}</td>
                        <td class="py-2 px-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Online</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="py-4 text-center text-gray-400">Belum ada pengunjung online</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>