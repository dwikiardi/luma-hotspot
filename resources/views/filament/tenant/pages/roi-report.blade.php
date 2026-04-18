<div class="space-y-6">
    <div class="bg-gradient-to-r from-violet-600 to-indigo-600 rounded-2xl p-8 text-white">
        <p class="text-sm opacity-80">Total nilai yang dihasilkan Luma Network</p>
        <h1 class="text-5xl font-bold mt-1">{{ $roi['summary']['total_roi_display'] ?? 'Rp 0' }}</h1>
        <p class="text-sm opacity-80 mt-2">dalam 30 hari terakhir</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        @foreach($roi['breakdown'] ?? [] as $item)
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-gray-500 text-sm font-medium">{{ $item['label'] ?? '' }}</h3>
            <p class="text-2xl font-bold text-gray-900 mt-2">{{ $item['display'] ?? '' }}</p>
            <p class="text-gray-500 text-xs mt-2">{{ $item['detail'] ?? '' }}</p>
        </div>
        @endforeach
    </div>

    <div class="border-2 border-dashed border-red-200 rounded-xl p-6 bg-red-50">
        <h3 class="text-lg font-semibold text-red-800 mb-4">Jika tidak pakai Luma Network:</h3>
        <ul class="space-y-2 text-red-700">
            <li>Data tamu terkumpul: 0</li>
            <li>Komplain ditangani manual: Rp {{ number_format($roi['vs_competitor']['total_yang_hilang'] ?? 0, 0, ',', '.') }}</li>
            <li>Repeat visitor tertrack: 0</li>
            <li class="font-bold">Total yang hilang: Rp {{ number_format($roi['vs_competitor']['total_yang_hilang'] ?? 0, 0, ',', '.') }}</li>
        </ul>
    </div>
</div>
