<?php

namespace App\Filament\Admin\Widgets;

use App\Models\AnalyticsEvent;
use App\Models\Tenant;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopVenuesWidget extends BaseWidget
{
    protected static ?string $heading = 'Top Performing Venues';

    protected int|string|array $columnSpan = 4;

    protected static ?int $sort = 5;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Tenant::query()
                    ->withCount(['analyticsEvents as visitor_count' => function ($query) {
                        $query->where('event_type', 'login_success')
                            ->where('occurred_at', '>=', now()->subDays(7));
                    }])
                    ->orderBy('visitor_count', 'desc')
                    ->limit(5)
            )
            ->columns([
                TextColumn::make('rank')
                    ->rowIndex()
                    ->badge()
                    ->color(fn (int $state) => match ($state) {
                        1 => 'warning',
                        2 => 'gray',
                        3 => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (int $state) => '#'.$state),

                TextColumn::make('name')
                    ->searchable()
                    ->description(fn (Tenant $record): string => ucfirst($record->venue_type)),

                TextColumn::make('visitor_count')
                    ->label('Visitor 7 hari')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('seamless_rate')
                    ->label('Seamless Rate')
                    ->state(function (Tenant $record): string {
                        $auto = AnalyticsEvent::where('tenant_id', $record->id)
                            ->where('occurred_at', '>=', now()->subDays(7))
                            ->where('event_type', 'auto_reconnect')->count();
                        $forced = AnalyticsEvent::where('tenant_id', $record->id)
                            ->where('occurred_at', '>=', now()->subDays(7))
                            ->where('event_type', 'forced_relogin')->count();
                        $total = $auto + $forced;
                        if ($total === 0) {
                            return 'N/A';
                        }

                        return round(($auto / $total) * 100, 1).'%';
                    })
                    ->badge()
                    ->color(function (Tenant $record): string {
                        $auto = AnalyticsEvent::where('tenant_id', $record->id)
                            ->where('occurred_at', '>=', now()->subDays(7))
                            ->where('event_type', 'auto_reconnect')->count();
                        $forced = AnalyticsEvent::where('tenant_id', $record->id)
                            ->where('occurred_at', '>=', now()->subDays(7))
                            ->where('event_type', 'forced_relogin')->count();
                        $total = $auto + $forced;
                        if ($total === 0) {
                            return 'gray';
                        }
                        $rate = ($auto / $total) * 100;

                        return $rate >= 90 ? 'success' : ($rate >= 70 ? 'warning' : 'danger');
                    }),

                TextColumn::make('roi_30d')
                    ->label('ROI 30 hari')
                    ->state(function (Tenant $record): string {
                        $events = AnalyticsEvent::where('tenant_id', $record->id)
                            ->where('occurred_at', '>=', now()->subDays(30))
                            ->where('event_type', 'login_success')
                            ->count();
                        $roi = $events * 12500;
                        if ($roi >= 1000000) {
                            return 'Rp '.number_format($roi / 1000000, 1).' Jt';
                        }

                        return 'Rp '.number_format($roi / 1000, 0).' Rb';
                    }),
            ])
            ->paginated(false);
    }
}
