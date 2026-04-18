<?php

namespace App\Filament\Admin\Resources\ActivityLogResource;

use App\Filament\Admin\Traits\HasAdminPermissions;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

class ActivityLogResource extends Resource
{
    use HasAdminPermissions;

    protected static ?string $model = Activity::class;

    protected static ?string $slug = 'activity-log';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Activity Log';

    protected static ?string $navigationGroup = 'User Management';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return auth('admin')->user()?->isSuperAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('log_name')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'auth' => 'info',
                        'portal' => 'success',
                        'system' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('description')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('causer.name')
                    ->label('Dilakukan oleh')
                    ->default('System'),

                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Tipe')
                    ->formatStateUsing(function (string $state): string {
                        $class = class_basename($state);

                        return match ($class) {
                            'Tenant' => 'Tenant',
                            'TenantUser' => 'Tenant User',
                            'PortalConfig' => 'Portal Config',
                            'AdminUser' => 'Admin User',
                            default => $class,
                        };
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->defaultSort('desc'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('log_name')
                    ->options([
                        'auth' => 'Auth',
                        'portal' => 'Portal',
                        'system' => 'System',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->paginated([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogs::route('/'),
        ];
    }
}
