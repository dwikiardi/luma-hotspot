<div class="bg-white rounded-xl shadow border border-gray-200 p-4">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
            <div class="w-2 h-2 rounded-full bg-indigo-500"></div>
            <h3 class="font-semibold text-gray-900">RADIUS Accounting</h3>
        </div>
        <div class="flex items-center gap-3 text-sm">
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">{{ $stats->active }} active</span>
            <span class="text-gray-500 text-xs">{{ $stats->today_starts }} starts &middot; {{ $stats->today_stops }} stops</span>
            <span class="text-blue-600 text-xs font-medium">&#8595;{{ $stats->traffic_in }} / &#8593;{{ $stats->traffic_out }}</span>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50">
                    <th class="text-left py-2 px-3 font-medium text-gray-600 text-xs uppercase">User</th>
                    <th class="text-left py-2 px-3 font-medium text-gray-600 text-xs uppercase">MAC</th>
                    <th class="text-left py-2 px-3 font-medium text-gray-600 text-xs uppercase">IP</th>
                    <th class="text-left py-2 px-3 font-medium text-gray-600 text-xs uppercase">Start</th>
                    <th class="text-left py-2 px-3 font-medium text-gray-600 text-xs uppercase">Stop</th>
                    <th class="text-left py-2 px-3 font-medium text-gray-600 text-xs uppercase">Duration</th>
                    <th class="text-right py-2 px-3 font-medium text-gray-600 text-xs uppercase">Traffic</th>
                    <th class="text-center py-2 px-3 font-medium text-gray-600 text-xs uppercase">Cause</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                <tr class="border-b border-gray-50 hover:bg-gray-50">
                    <td class="py-2 px-3 font-medium text-gray-900">{{ $row->username }}</td>
                    <td class="py-2 px-3 text-gray-500 font-mono text-xs">{{ $row->mac }}</td>
                    <td class="py-2 px-3 text-gray-600 font-mono text-xs">{{ $row->client_ip }}</td>
                    <td class="py-2 px-3 text-gray-500 text-xs">{{ \Carbon\Carbon::parse($row->acctstarttime)->format('d M H:i') }}</td>
                    <td class="py-2 px-3 text-xs">
                        @if($row->is_active)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                        @else
                            <span class="text-gray-500">{{ \Carbon\Carbon::parse($row->acctstoptime)->format('d M H:i') }}</span>
                        @endif
                    </td>
                    <td class="py-2 px-3 text-gray-500 text-xs">{{ $row->duration }}</td>
                    <td class="py-2 px-3 text-right text-gray-600 text-xs">{{ $row->traffic }}</td>
                    <td class="py-2 px-3 text-center text-xs text-gray-500">{{ $row->terminate }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="py-8 text-center text-gray-400">No accounting data</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>