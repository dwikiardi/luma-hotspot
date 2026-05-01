<div class="bg-white rounded-xl shadow border border-gray-200 p-4">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
            <div class="w-2 h-2 rounded-full bg-blue-500"></div>
            <h3 class="font-semibold text-gray-900">RADIUS Auth Events</h3>
        </div>
        <div class="flex items-center gap-3 text-sm">
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">{{ $stats->accepts }} Accept</span>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">{{ $stats->rejects }} Reject</span>
            <span class="text-gray-500 text-xs">{{ $stats->rate }}% rate &middot; {{ $stats->unique_users }} unique users</span>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50">
                    <th class="text-left py-2 px-3 font-medium text-gray-600 text-xs uppercase">User</th>
                    <th class="text-left py-2 px-3 font-medium text-gray-600 text-xs uppercase">Reply</th>
                    <th class="text-left py-2 px-3 font-medium text-gray-600 text-xs uppercase">Time</th>
                </tr>
            </thead>
            <tbody>
                @forelse($auths as $auth)
                <tr class="border-b border-gray-50 hover:bg-gray-50">
                    <td class="py-2 px-3 font-medium text-gray-900">{{ $auth->username ?: '(empty)' }}</td>
                    <td class="py-2 px-3">
                        @if($auth->is_accept)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Accept</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Reject</span>
                        @endif
                    </td>
                    <td class="py-2 px-3 text-gray-500 text-xs">{{ $auth->authdate->format('d M H:i:s') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="3" class="py-8 text-center text-gray-400">No auth events</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>