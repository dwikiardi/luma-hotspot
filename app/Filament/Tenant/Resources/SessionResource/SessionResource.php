<?php

namespace App\Filament\Tenant\Resources\SessionResource;

use App\Filament\Tenant\Resources\SessionResource\Pages;
use App\Models\Router;
use App\Models\UserSession;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SessionResource extends Resource
{
    protected static ?string $model = UserSession::class;

    protected static ?string $slug = 'sessions';

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Riwayat Sesi';

    protected static ?string $navigationGroup = 'Pengunjung';

    protected static ?int $navigationSort = 3;

    protected static ?string $label = 'Riwayat Sesi';

    protected static ?string $pluralLabel = 'Riwayat Sesi';

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $tenantId = filament()->getTenant()?->id;
        $routerIds = Router::where('tenant_id', $tenantId)->pluck('id')->toArray();

        if (empty($routerIds)) {
            return UserSession::where('id', 0);
        }

        return UserSession::whereIn('router_id', $routerIds)
            ->with(['user', 'router'])
            ->orderByDesc('login_at');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.identity_value')
                    ->label('Username')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Nama')
                    ->default('-')
                    ->searchable(),
                Tables\Columns\TextColumn::make('login_method')
                    ->label('Metode')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'google' => 'danger',
                        'wa' => 'success',
                        'room' => 'primary',
                        'email' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'room' => 'Kamar',
                        'wa' => 'WhatsApp',
                        default => ucfirst($state),
                    }),
                Tables\Columns\TextColumn::make('mac_address')
                    ->label('MAC')
                    ->copyable()
                    ->fontFamily('mono')
                    ->default('-'),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->fontFamily('mono')
                    ->default('-'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'disconnected' => 'warning',
                        'expired' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'Online',
                        'disconnected' => 'Grace',
                        'expired' => 'Expired',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('login_at')
                    ->label('Login')
                    ->dateTime('d M H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('duration')
                    ->label('Durasi')
                    ->state(fn ($record) => $record->login_at ? $record->login_at->diffForHumans(now(), true) : '-'),
                Tables\Columns\TextColumn::make('traffic')
                    ->label('Traffic')
                    ->state(function ($record) {
                        $input = $record->meta['input_octets'] ?? 0;
                        $output = $record->meta['output_octets'] ?? 0;
                        if ($input === 0 && $output === 0) return '-';
                        return number_format($input / 1024 / 1024, 1) . '↓ ' . number_format($output / 1024 / 1024, 1) . '↑ MB';
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(['active' => 'Online', 'disconnected' => 'Grace', 'expired' => 'Expired']),
                Tables\Filters\SelectFilter::make('login_method')
                    ->label('Metode')
                    ->options(['room' => 'Kamar', 'google' => 'Google', 'wa' => 'WhatsApp', 'email' => 'Email']),
                Tables\Filters\Filter::make('today')
                    ->label('Hari Ini')
                    ->query(fn ($query) => $query->whereDate('login_at', today())),
                Tables\Filters\Filter::make('week')
                    ->label('Minggu Ini')
                    ->query(fn ($query) => $query->where('login_at', '>=', now()->startOfWeek())),
            ])
            ->defaultSort('login_at', 'desc')
            ->paginated([15, 25, 50]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSessions::route('/'),
        ];
    }
}