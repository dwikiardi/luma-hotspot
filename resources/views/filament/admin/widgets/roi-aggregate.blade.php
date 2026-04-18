<x-filament-widgets::widget>
    <div style="border-radius: 0.75rem; background: linear-gradient(to bottom right, #7c3aed, #4f46e5, #1d4ed8); padding: 1.5rem; color: white; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); overflow: hidden;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
            <div>
                <p style="font-size: 0.875rem; font-weight: 500; color: #c7d2fe;">ROI Platform — 30 Hari</p>
                <h3 style="font-size: 1.875rem; font-weight: 700; letter-spacing: -0.025em; margin-top: 0.25rem; color: white;">
                    Rp {{ number_format($totalRoi, 0, ',', '.') }}
                </h3>
            </div>
            <div style="display: flex; height: 3rem; width: 3rem; align-items: center; justify-content: center; border-radius: 0.75rem; background-color: rgba(255, 255, 255, 0.2); flex-shrink: 0;">
                <svg xmlns="http://www.w3.org/2000/svg" style="height: 1.5rem; width: 1.5rem; color: white;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.403 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.403-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
        </div>

        <div style="margin-bottom: 1.25rem;">
            <div style="display: flex; justify-content: space-between; font-size: 0.75rem; color: #c7d2fe; margin-bottom: 0.375rem;">
                <span>Progress vs Target</span>
                <span style="font-weight: 600; color: white;">{{ $progressPercent }}%</span>
            </div>
            <div style="width: 100%; background-color: rgba(255, 255, 255, 0.25); border-radius: 9999px; height: 0.625rem; overflow: hidden;">
                <div style="background-color: white; border-radius: 9999px; height: 0.625rem; transition: all 0.7s ease-out; width: {{ max($progressPercent, 2) }}%;"></div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.75rem;">
            <div style="border-radius: 0.5rem; background-color: rgba(255, 255, 255, 0.15); padding: 0.75rem; text-align: center;">
                <p style="font-size: 0.75rem; color: #c7d2fe;">Data Tamu</p>
                <p style="font-size: 0.875rem; font-weight: 700; color: white; margin-top: 0.125rem;">Rp {{ number_format($dataTamu, 0, ',', '.') }}</p>
            </div>
            <div style="border-radius: 0.5rem; background-color: rgba(255, 255, 255, 0.15); padding: 0.75rem; text-align: center;">
                <p style="font-size: 0.75rem; color: #c7d2fe;">Komplain</p>
                <p style="font-size: 0.875rem; font-weight: 700; color: white; margin-top: 0.125rem;">Rp {{ number_format($komplain, 0, ',', '.') }}</p>
            </div>
            <div style="border-radius: 0.5rem; background-color: rgba(255, 255, 255, 0.15); padding: 0.75rem; text-align: center;">
                <p style="font-size: 0.75rem; color: #c7d2fe;">Repeat Visit</p>
                <p style="font-size: 0.875rem; font-weight: 700; color: white; margin-top: 0.125rem;">Rp {{ number_format($repeatVisit, 0, ',', '.') }}</p>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>