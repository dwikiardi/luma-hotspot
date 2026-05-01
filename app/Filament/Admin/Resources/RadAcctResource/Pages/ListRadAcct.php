<?php

namespace App\Filament\Admin\Resources\RadAcctResource\Pages;

use App\Filament\Admin\Resources\RadAcctResource\RadAcctResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;

class ListRadAcct extends ListRecords
{
    protected static string $resource = RadAcctResource::class;

    protected static ?string $title = 'RADIUS Accounting';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_csv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    $sessions = \App\Models\UserSession::with(['user', 'router'])
                        ->orderByDesc('login_at')
                        ->limit(5000)
                        ->get();

                    $csv = "Username,Name,Method,MAC,IP,Router,Status,Login,Duration,Traffic\n";
                    foreach ($sessions as $s) {
                        $input = $s->meta['input_octets'] ?? 0;
                        $output = $s->meta['output_octets'] ?? 0;
                        $csv .= sprintf(
                            "%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
                            $s->user?->identity_value ?? '-',
                            $s->user?->name ?? '-',
                            $s->login_method ?? '-',
                            $s->mac_address ?? '-',
                            $s->ip_address ?? '-',
                            $s->router?->name ?? '-',
                            $s->status,
                            $s->login_at?->format('Y-m-d H:i') ?? '-',
                            $s->login_at ? $s->login_at->diffForHumans(now(), true) : '-',
                            ($input > 0 || $output > 0) ? number_format($input / 1024 / 1024, 1).'↓ '.number_format($output / 1024 / 1024, 1).'↑ MB' : '-'
                        );
                    }

                    return response()->streamDownload(fn () => print($csv), 'radius-accounting-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
                }),
        ];
    }
}