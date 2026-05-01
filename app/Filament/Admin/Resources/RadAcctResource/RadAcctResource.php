<?php

namespace App\Filament\Admin\Resources\RadAcctResource;

use App\Models\UserSession;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RadAcctResource extends Resource
{
    protected static ?string $model = UserSession::class;

    protected static ?string $slug = 'rad-acct';

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Accounting';

    protected static ?string $navigationGroup = 'RADIUS';

    protected static ?int $navigationSort = 3;

    protected static ?string $label = 'RADIUS Accounting';

    protected static ?string $pluralLabel = 'RADIUS Accounting';

    public static function table(Table $table): Table
    {
        return $table
            ->query(
                UserSession::query()
                    ->with(['user', 'router'])
                    ->orderByDesc('login_at')
            )
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
                    ->label('IP Address')
                    ->fontFamily('mono')
                    ->default('-'),
                Tables\Columns\TextColumn::make('router.name')
                    ->label('Router')
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
                    ->state(fn ($record) => $record->login_at ? $record->login_at->diffForHumans(now(), true) : '-')
                    ->sortable(false),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime('d M H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('traffic')
                    ->label('Traffic')
                    ->state(function ($record) {
                        $input = $record->meta['input_octets'] ?? 0;
                        $output = $record->meta['output_octets'] ?? 0;
                        if ($input === 0 && $output === 0) return '-';
                        return number_format($input / 1024 / 1024, 1).'↓ '.number_format($output / 1024 / 1024, 1).'↑ MB';
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Online',
                        'disconnected' => 'Grace',
                        'expired' => 'Expired',
                    ]),
                Tables\Filters\SelectFilter::make('login_method')
                    ->label('Method')
                    ->options([
                        'room' => 'Kamar',
                        'google' => 'Google',
                        'wa' => 'WhatsApp',
                        'email' => 'Email',
                    ]),
                Tables\Filters\SelectFilter::make('router_id')
                    ->label('Router')
                    ->relationship('router', 'name'),
                Tables\Filters\Filter::make('today')
                    ->label('Today')
                    ->query(fn ($query) => $query->whereDate('login_at', today())),
                Tables\Filters\Filter::make('week')
                    ->label('This Week')
                    ->query(fn ($query) => $query->where('login_at', '>=', now()->startOfWeek())),
            ])
            ->defaultSort('login_at', 'desc')
            ->paginated([15, 25, 50]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRadAcct::route('/'),
        ];
    }
}