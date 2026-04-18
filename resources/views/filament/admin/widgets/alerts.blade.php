<x-filament-widgets::widget>
    <x-filament::section>
        @if(count($alerts) === 0)
            <div class="text-center py-8 text-gray-500">
                <div class="text-4xl mb-2">✅</div>
                <p class="text-lg font-medium">Semua sistem normal</p>
                <p class="text-sm">Tidak ada alert saat ini</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach($alerts as $alert)
                    <div class="flex items-start gap-3 p-3 rounded-lg
                        @if($alert['severity'] === 'danger') bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800
                        @elseif($alert['severity'] === 'warning') bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800
                        @else bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800
                        @endif
                    ">
                        <span class="text-xl">{{ $alert['icon'] }}</span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ $alert['message'] }}
                            </p>
                        </div>
                        @if(!empty($alert['action_url']))
                            <a href="{{ $alert['action_url'] }}"
                               class="shrink-0 text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                                {{ $alert['action_label'] }}
                            </a>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
