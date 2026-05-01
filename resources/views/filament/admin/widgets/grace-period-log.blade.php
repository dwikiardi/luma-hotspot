<div class="bg-white rounded-xl shadow border border-gray-200 p-4">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
            <div class="w-2 h-2 rounded-full bg-amber-500"></div>
            <h3 class="font-semibold text-gray-900">Session & Grace Period Monitor</h3>
        </div>
        <div class="flex items-center gap-4 text-sm">
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">{{ $stats->active }} Online</span>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">{{ $stats->in_grace }} Grace</span>
            <span class="text-gray-500 text-xs">FP: {{ $stats->with_fingerprint }}</span>
            <span class="text-gray-500 text-xs">MAC: {{ $stats->with_mac }}</span>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50">
                    <th class="text-left py-2 px-3 font-medium text-gray-600 text-xs uppercase">User</th>
                    <th class="text-left py-2 px-3 font-medium text-gray-600 text-xs uppercase">Method</th>
                    <th class="text-left py-2 px-3 font-medium text-gray-600 text-xs uppercase">Status</th>
                    <th class="text-left py-2 px-3 font-medium text-gray-600 text-xs uppercase">MAC</th>
                    <th class="text-left py-2 px-3 font-medium text-gray-600 text-xs uppercase">IP</th>
                    <th class="text-left py-2 px-3 font-medium text-gray-600 text-xs uppercase">Fingerprint</th>
                    <th class="text-left py-2 px-3 font-medium text-gray-600 text-xs uppercase">Router</th>
                    <th class="text-left py-2 px-3 font-medium text-gray-600 text-xs uppercase">Login</th>
                    <th class="text-left py-2 px-3 font-medium text-gray-600 text-xs uppercase">Remaining</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sessions as $s)
                <tr class="border-b border-gray-50 hover:bg-gray-50">
                    <td class="py-2 px-3">
                        <div class="font-medium text-gray-900">{{ $s->user_name ?? $s->identity_value ?? '-' }}</div>
                        @if($s->identity_value)
                        <div class="text-xs text-gray-400">{{ $s->identity_value }}</div>
                        @endif
                    </td>
                    <td class="py-2 px-3">
                        @php $methodColors = ['room' => 'blue', 'google' => 'red', 'wa' => 'green', 'facebook' => 'indigo']; @endphp
                        @php $methodLabels = ['room' => 'Kamar', 'google' => 'Google', 'wa' => 'WhatsApp', 'facebook' => 'FB']; @endphp
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $methodColors[$s->login_method] ?? 'gray' }}-100 text-{{ $methodColors[$s->login_method] ?? 'gray' }}-800">
                            {{ $methodLabels[$s->login_method] ?? ucfirst($s->login_method) }}
                        </span>
                    </td>
                    <td class="py-2 px-3">
                        @if($s->status === 'active')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Online</span>
                        @elseif($s->has_grace)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Grace</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">{{ $s->status }}</span>
                        @endif
                    </td>
                    <td class="py-2 px-3 text-gray-500 font-mono text-xs">{{ $s->mac_address ?: '-' }}</td>
                    <td class="py-2 px-3 text-gray-500 font-mono text-xs">{{ $s->ip_address ?: '-' }}</td>
                    <td class="py-2 px-3 text-gray-400 font-mono text-xs" title="{{ $s->fingerprint_hash }}">{{ $s->hash_short }}</td>
                    <td class="py-2 px-3 text-gray-500 text-xs">{{ $s->router_name ?: '-' }}</td>
                    <td class="py-2 px-3 text-gray-500 text-xs">{{ \Carbon\Carbon::parse($s->login_at)->format('d M H:i') }}</td>
                    <td class="py-2 px-3 text-xs">
                        @if($s->status === 'active')
                            <span class="text-green-600">{{ $s->remaining_text }}</span>
                        @elseif($s->has_grace)
                            <span class="text-amber-600">{{ $s->remaining_text }}</span>
                        @else
                            <span class="text-gray-400">Expired</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="py-8 text-center text-gray-400">No active or grace sessions</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>