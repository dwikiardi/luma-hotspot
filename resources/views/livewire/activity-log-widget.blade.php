<div
    x-data="{}"
    wire:poll.5s
    class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden"
>
    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
            <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            System Activity Log
        </h3>
        <span class="text-xs text-gray-400">{{ $logs->count() }} entries</span>
    </div>

    <div class="overflow-y-auto max-h-96" id="activity-log-scroll">
        @if($logs->isEmpty())
            <div class="p-8 text-center text-gray-400 text-sm">
                No activity yet.
            </div>
        @else
        <div class="flow-root">
            <ul role="list" class="divide-y divide-gray-50">
                @foreach($logs as $log)
                <li class="px-4 py-2.5 hover:bg-gray-50 transition-colors">
                    <div class="flex items-start gap-2.5">
                        {{-- Icon --}}
                        <span class="mt-0.5 flex-shrink-0">
                            @php
                                $iconClass = match($log->level) {
                                    'error' => 'text-red-500 bg-red-50',
                                    'warn' => 'text-amber-500 bg-amber-50',
                                    'success' => 'text-emerald-500 bg-emerald-50',
                                    default => 'text-blue-500 bg-blue-50',
                                };
                                $dotColor = match($log->level) {
                                    'error' => 'bg-red-400',
                                    'warn' => 'bg-amber-400',
                                    'success' => 'bg-emerald-400',
                                    default => 'bg-blue-400',
                                };
                            @endphp
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full {{ $iconClass }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $dotColor }}"></span>
                            </span>
                        </span>

                        {{-- Content --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-xs font-mono px-1.5 py-0.5 rounded bg-gray-100 text-gray-600">
                                    {{ $log->component }}
                                </span>
                                <span class="text-xs font-mono px-1.5 py-0.5 rounded 
                                    @if($log->level === 'error') bg-red-100 text-red-700
                                    @elseif($log->level === 'warn') bg-amber-100 text-amber-700
                                    @elseif($log->level === 'success') bg-emerald-100 text-emerald-700
                                    @else bg-gray-100 text-gray-600 @endif
                                ">
                                    {{ $log->event }}
                                </span>
                                <span class="text-xs text-gray-400 tabular-nums">
                                    {{ $log->created_at->format('H:i:s') }}
                                </span>
                            </div>
                            <p class="text-sm text-gray-700 mt-0.5 leading-relaxed">
                                {{ $log->message }}
                            </p>
                            @if($log->data)
                            <div class="mt-0.5 text-xs text-gray-400 font-mono truncate max-w-md">
                                @foreach($log->data as $k => $v)
                                    @if($k !== 'users' && $v)
                                    <span class="mr-2">{{ $k }}={{ is_array($v) ? json_encode($v) : $v }}</span>
                                    @endif
                                @endforeach
                            </div>
                            @endif
                        </div>
                    </div>
                </li>
                @endforeach
            </ul>
        </div>
        @endif
    </div>
</div>
