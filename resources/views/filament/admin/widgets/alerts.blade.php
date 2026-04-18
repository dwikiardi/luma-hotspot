<x-filament-widgets::widget>
    <div class="space-y-3">
        @if(count($alerts) === 0)
            <div class="flex items-center gap-3 rounded-xl border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-900/20">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-green-100 dark:bg-green-800">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-green-800 dark:text-green-300">Semua sistem normal</p>
                    <p class="text-xs text-green-600 dark:text-green-400">Tidak ada alert saat ini</p>
                </div>
            </div>
        @else
            @foreach($alerts as $alert)
                <div class="flex items-center gap-3 p-3 rounded-xl
                    @if($alert['severity'] === 'danger') border border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-800
                    @elseif($alert['severity'] === 'warning') border border-yellow-200 bg-yellow-50 dark:bg-yellow-900/20 dark:border-yellow-800
                    @else border border-blue-200 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-800
                    @endif
                ">
                    <span class="text-lg">{{ $alert['icon'] }}</span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                            {{ $alert['message'] }}
                        </p>
                    </div>
                    @if(!empty($alert['action_url']))
                        <a href="{{ $alert['action_url'] }}"
                           class="shrink-0 inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold bg-indigo-600 text-white hover:bg-indigo-700 transition-colors">
                            {{ $alert['action_label'] }}
                        </a>
                    @endif
                </div>
            @endforeach
        @endif
    </div>
</x-filament-widgets::widget>