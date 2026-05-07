<x-filament::section>
    <x-slot name="heading">
        <div class="flex items-center gap-2">
            <x-heroicon-o-queue-list class="w-5 h-5 text-gray-500" />
            System Activity Log
        </div>
    </x-slot>
    @livewire('activity-log-widget')
</x-filament::section>
