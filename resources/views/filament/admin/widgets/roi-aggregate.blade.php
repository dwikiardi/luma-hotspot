<x-filament-widgets::widget>
    <div class="rounded-xl bg-gradient-to-br from-violet-600 via-indigo-600 to-blue-700 p-6 text-white shadow-lg">
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="text-sm font-medium text-white/80">ROI Platform — 30 Hari</p>
                <h3 class="text-3xl font-bold tracking-tight mt-1">
                    Rp {{ number_format($totalRoi, 0, ',', '.') }}
                </h3>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-white/20">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.403 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.403-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
        </div>

        <div class="mb-4">
            <div class="flex justify-between text-xs text-white/70 mb-1.5">
                <span>Progress vs Target</span>
                <span class="font-semibold">{{ $progressPercent }}%</span>
            </div>
            <div class="w-full bg-white/20 rounded-full h-2.5 overflow-hidden">
                <div class="bg-white rounded-full h-2.5 transition-all duration-700 ease-out"
                     style="width: {{ max($progressPercent, 1) }}%"></div>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-3">
            <div class="rounded-lg bg-white/10 p-3 text-center">
                <p class="text-xs text-white/70">Data Tamu</p>
                <p class="text-sm font-bold mt-0.5">Rp {{ number_format($dataTamu, 0, ',', '.') }}</p>
            </div>
            <div class="rounded-lg bg-white/10 p-3 text-center">
                <p class="text-xs text-white/70">Komplain</p>
                <p class="text-sm font-bold mt-0.5">Rp {{ number_format($komplain, 0, ',', '.') }}</p>
            </div>
            <div class="rounded-lg bg-white/10 p-3 text-center">
                <p class="text-xs text-white/70">Repeat Visit</p>
                <p class="text-sm font-bold mt-0.5">Rp {{ number_format($repeatVisit, 0, ',', '.') }}</p>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>