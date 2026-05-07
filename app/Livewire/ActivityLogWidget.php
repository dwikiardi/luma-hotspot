<?php

namespace App\Livewire;

use App\Models\ActivityLog;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ActivityLogWidget extends Component
{
    public int $limit = 30;

    public ?int $tenantId = null;

    public function mount(): void
    {
        $this->tenantId = filament()?->getTenant()?->id;
    }

    public function render(): View
    {
        $logs = ActivityLog::when($this->tenantId, function ($q) {
                $q->where(function ($q) {
                    $q->where('tenant_id', $this->tenantId)
                      ->orWhereNull('tenant_id');
                });
            })
            ->orderByDesc('created_at')
            ->limit($this->limit)
            ->get();

        return view('livewire.activity-log-widget', [
            'logs' => $logs,
        ]);
    }

    public function getListeners(): array
    {
        return [
            'echo:activity-log,.activity.updated' => '$refresh',
        ];
    }

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="p-4 text-center text-gray-500">
            <div class="animate-spin inline-block w-4 h-4 border-2 border-gray-300 border-t-indigo-500 rounded-full mr-2"></div>
            Loading activity log...
        </div>
        HTML;
    }
}
