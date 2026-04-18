<?php

namespace App\Filament\Tenant\Resources\TenantStaffResource;

use App\Models\TenantUser;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TenantStaffResource extends Resource
{
    protected static ?string $model = TenantUser::class;

    protected static ?string $slug = 'staff';

    protected static ?string $tenantOwnershipRelationshipName = 'tenant';

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Tim & Staff';

    protected static ?string $navigationGroup = 'Konfigurasi WiFi';

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        $tenantId = filament()->getTenant()?->id;

        return parent::getEloquentQuery()->where('tenant_id', $tenantId);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi User')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\Select::make('role')
                            ->label('Role')
                            ->options([
                                'owner' => 'Owner',
                                'staff' => 'Staff',
                            ])
                            ->required()
                            ->default('staff'),
                    ])->columns(2),

                Forms\Components\Section::make('Password')
                    ->schema([
                        Forms\Components\Placeholder::make('password_info')
                            ->label('')
                            ->content('Kosongkan jika tidak ingin mengubah password (saat edit)'),
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->nullable(fn (string $operation): bool => $operation === 'edit')
                            ->minLength(8)
                            ->revealable()
                            ->autocomplete('new-password'),
                        Forms\Components\TextInput::make('password_confirmation')
                            ->password()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->nullable(fn (string $operation): bool => $operation === 'edit')
                            ->same('password')
                            ->revealable(),
                    ])->columns(2),

                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $tenantId = filament()->getTenant()?->id;

                return $query->where('tenant_id', $tenantId);
            })
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->description(fn (TenantUser $record): string => $record->email),

                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'owner' => 'warning',
                        'staff' => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'owner' => 'Owner',
                        'staff' => 'Staff',
                        default => $state,
                    }),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options([
                        'owner' => 'Owner',
                        'staff' => 'Staff',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (TenantUser $record): bool => $record->id === auth('tenant_users')->id()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenantStaff::route('/'),
            'create' => Pages\CreateTenantStaff::route('/create'),
            'edit' => Pages\EditTenantStaff::route('/{record}/edit'),
        ];
    }
}
