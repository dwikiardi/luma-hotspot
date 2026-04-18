<x-filament-widgets::widget>
    <x-filament::section>
        <div class="rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 p-6 text-white">
            <h3 class="text-lg font-semibold mb-4">💰 ROI Platform — 30 Hari</h3>

            <div class="text-4xl font-bold mb-3">
                Rp {{ number_format($totalRoi, 0, ',', '.') }}
            </div>

            <div class="mb-4">
                <div class="flex justify-between text-sm mb-1">
                    <span>Progress vs Target</span>
                    <span>{{ $progressPercent }}%</span>
                </div>
                <div class="w-full bg-white/20 rounded-full h-3">
                    <div class="bg-white rounded-full h-3 transition-all duration-500"
                         style="width: {{ $progressPercent }}%"></div>
                </div>
            </div>

            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span>📊 Data tamu</span>
                    <span class="font-semibold">Rp {{ number_format($dataTamu, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between">
                    <span>😊 Komplain</span>
                    <span class="font-semibold">Rp {{ number_format($komplain, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between">
                    <span>🔄 Repeat visit</span>
                    <span class="font-semibold">Rp {{ number_format($repeatVisit, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
