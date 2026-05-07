<?php

namespace App\Filament\Tenant\Resources\DeviceLogResource;

use App\Models\DeviceFingerprint;
use App\Models\Router;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DeviceLogResource extends Resource
{
    protected static ?string $model = DeviceFingerprint::class;

    protected static ?string $slug = 'device-logs';

    protected static ?string $tenantOwnershipRelationshipName = null;

    public static function isScopedToTenant(): bool
    {
        return false;
    }

    protected static ?string $navigationIcon = 'heroicon-o-finger-print';

    protected static ?string $navigationLabel = 'Log Device';

    protected static ?string $navigationGroup = 'Pengunjung';

    protected static ?int $navigationSort = 4;

    protected static ?string $label = 'Log Device & Fingerprint';

    protected static ?string $pluralLabel = 'Log Device & Fingerprint';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $tenantId = filament()->getTenant()?->id;
        $routerIds = Router::where('tenant_id', $tenantId)->pluck('nas_identifier')->toArray();

        return parent::getEloquentQuery()
            ->whereIn('nas_id', $routerIds)
            ->orderByDesc('updated_at');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.identity_value')
                    ->label('User')
                    ->searchable()
                    ->default('-'),

                Tables\Columns\TextColumn::make('trust_score')
                    ->label('Trust Score')
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 80 => 'success',
                        $state >= 50 => 'warning',
                        default => 'danger',
                    }),

                Tables\Columns\TextColumn::make('confidence')
                    ->label('Confidence')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'high' => 'success',
                        'medium' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_known_device')
                    ->label('Known')
                    ->boolean(),

                Tables\Columns\TextColumn::make('match_count')
                    ->label('Matches')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('browser_name')
                    ->label('Browser')
                    ->formatStateUsing(fn ($state, $record) => $state . ' ' . ($record->browser_version ?? '')),

                Tables\Columns\TextColumn::make('os_name')
                    ->label('OS')
                    ->formatStateUsing(fn ($state, $record) => $state . ' ' . ($record->os_version ?? '')),

                Tables\Columns\TextColumn::make('platform')
                    ->label('Platform')
                    ->badge(),

                Tables\Columns\TextColumn::make('fingerprint_hash')
                    ->label('Fingerprint')
                    ->copyable()
                    ->fontFamily('mono')
                    ->size('text-xs')
                    ->limit(16),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('First Seen')
                    ->formatStateUsing(fn ($record) => \App\Helpers\TenantTime::format($record->created_at))
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Seen')
                    ->formatStateUsing(fn ($record) => \App\Helpers\TenantTime::format($record->updated_at))
                    ->sortable(),

                Tables\Columns\TextColumn::make('login_count')
                    ->label('Login Count')
                    ->state(fn ($record) => \App\Models\UserSession::where('fingerprint_hash', $record->fingerprint_hash)->count())
                    ->badge()
                    ->color('primary'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_known_device')
                    ->label('Known Device')
                    ->options([1 => 'Yes', 0 => 'No']),
                Tables\Filters\SelectFilter::make('confidence')
                    ->options(['high' => 'High', 'medium' => 'Medium', 'low' => 'Low']),
            ])
            ->defaultSort('updated_at', 'desc')
            ->paginated([15, 25, 50])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->label('Hapus')
                    ->modalHeading('Hapus fingerprint ini?')
                    ->modalDescription('Data fingerprint akan dihapus permanen.')
                    ->successNotificationTitle('Fingerprint berhasil dihapus'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->label('Hapus yang dipilih')
                    ->modalHeading('Hapus fingerprint yang dipilih?'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeviceLogs::route('/'),
        ];
    }
}