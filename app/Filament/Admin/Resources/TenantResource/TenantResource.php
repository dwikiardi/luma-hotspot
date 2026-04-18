<?php

namespace App\Filament\Admin\Resources\TenantResource;

use App\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $slug = 'tenants';

    protected static ?string $navigationGroup = 'Manajemen Tenant';

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationLabel = 'Tenant';

    protected static ?string $modelLabel = 'Tenant';

    protected static ?string $pluralModelLabel = 'Tenants';

    public static function canViewAny(): bool
    {
        return true;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Forms\Components\Select::make('venue_type')
                    ->options([
                        'hotel' => 'Hotel',
                        'cafe' => 'Kafe',
                        'coworking' => 'Co-working',
                        'mall' => 'Mall',
                        'custom' => 'Custom',
                    ])
                    ->required(),
                Forms\Components\Toggle::make('is_active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('venue_type')
                    ->colors([
                        'primary' => 'hotel',
                        'warning' => 'cafe',
                        'success' => 'coworking',
                        'gray' => ['mall', 'custom'],
                    ]),
                Tables\Columns\ToggleColumn::make('is_active'),
                Tables\Columns\TextColumn::make('routers_count')
                    ->counts('routers')
                    ->label('Jumlah Router'),
                Tables\Columns\TextColumn::make('created_at')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('venue_type')
                    ->options([
                        'hotel' => 'Hotel',
                        'cafe' => 'Kafe',
                        'coworking' => 'Co-working',
                        'mall' => 'Mall',
                        'custom' => 'Custom',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                //
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\RoutersRelationManager::class,
            RelationManagers\PortalConfigRelationManager::class,
            RelationManagers\WalledGardenRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'view' => Pages\ViewTenant::route('/{record}'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
