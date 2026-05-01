<div class="bg-white rounded-xl shadow border border-gray-200 p-4">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
            <div class="w-2 h-2 rounded-full bg-purple-500"></div>
            <h3 class="font-semibold text-gray-900">Fingerprint Scoring</h3>
        </div>
        <div class="flex items-center gap-4 text-sm">
            <span class="text-gray-500">{{ $stats->total }} devices</span>
            <span class="text-green-600 font-medium">{{ $stats->high_confidence }} high confidence</span>
            <span class="text-gray-500">avg score: {{ $stats->avg_score }}</span>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50">
                    <th class="text-left py-2 px-3 font-medium text-gray-600 text-xs uppercase">Hash</th>
                    <th class="text-left py-2 px-3 font-medium text-gray-600 text-xs uppercase">Visitor ID</th>
                    <th class="text-left py-2 px-3 font-medium text-gray-600 text-xs uppercase">Platform</th>
                    <th class="text-left py-2 px-3 font-medium text-gray-600 text-xs uppercase">Browser</th>
                    <th class="text-center py-2 px-3 font-medium text-gray-600 text-xs uppercase">Score</th>
                    <th class="text-center py-2 px-3 font-medium text-gray-600 text-xs uppercase">Confidence</th>
                    <th class="text-center py-2 px-3 font-medium text-gray-600 text-xs uppercase">Known</th>
                    <th class="text-center py-2 px-3 font-medium text-gray-600 text-xs uppercase">Matches</th>
                    <th class="text-left py-2 px-3 font-medium text-gray-600 text-xs uppercase">IP</th>
                    <th class="text-left py-2 px-3 font-medium text-gray-600 text-xs uppercase">Last Seen</th>
                </tr>
            </thead>
            <tbody>
                @forelse($prints as $fp)
                <tr class="border-b border-gray-50 hover:bg-gray-50">
                    <td class="py-2 px-3 font-mono text-xs text-gray-500" title="{{ $fp->fingerprint_hash }}">{{ $fp->hash_short }}</td>
                    <td class="py-2 px-3 text-gray-700 text-xs">{{ $fp->visitor_id ?: '-' }}</td>
                    <td class="py-2 px-3 text-gray-500 text-xs">{{ $fp->platform ?: '-' }}</td>
                    <td class="py-2 px-3 text-gray-500 text-xs">{{ $fp->browser_name ?: '-' }}</td>
                    <td class="py-2 px-3 text-center">
                        @php $scoreColor = $fp->trust_score >= 70 ? 'green' : ($fp->trust_score >= 40 ? 'yellow' : 'red'); @endphp
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $scoreColor }}-100 text-{{ $scoreColor }}-800">{{ $fp->trust_score }}</span>
                    </td>
                    <td class="py-2 px-3 text-center">
                        @php $confColor = $fp->confidence === 'high' ? 'green' : ($fp->confidence === 'medium' ? 'yellow' : 'red'); @endphp
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $confColor }}-100 text-{{ $confColor }}-800">{{ $fp->confidence }}</span>
                    </td>
                    <td class="py-2 px-3 text-center">
                        @if($fp->is_known_device)
                            <span class="text-green-600">&#10003;</span>
                        @else
                            <span class="text-gray-300">&#10007;</span>
                        @endif
                    </td>
                    <td class="py-2 px-3 text-center text-gray-600">{{ $fp->match_count }}</td>
                    <td class="py-2 px-3 text-gray-500 font-mono text-xs">{{ $fp->ip_address ?: '-' }}</td>
                    <td class="py-2 px-3 text-gray-500 text-xs">{{ $fp->updated_at ? \Carbon\Carbon::parse($fp->updated_at)->format('d M H:i') : '-' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" class="py-8 text-center text-gray-400">No fingerprint data yet</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>