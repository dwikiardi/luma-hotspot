<?php

namespace App\Filament\Admin\Resources\TenantUserResource;

use App\Filament\Admin\Traits\HasAdminPermissions;
use App\Models\Tenant;
use App\Models\TenantUser;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TenantUserResource extends Resource
{
    use HasAdminPermissions;

    protected static ?string $model = TenantUser::class;

    protected static ?string $slug = 'tenant-users';

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Tenant Users';

    protected static ?string $navigationGroup = 'User Management';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        $isCreate = ! $form->getRecord();

        return $form
            ->schema([
                Forms\Components\Section::make('Informasi User')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\Select::make('role')
                            ->options([
                                'owner' => 'Owner',
                                'staff' => 'Staff',
                            ])
                            ->required()
                            ->default('owner'),
                    ])->columns(2),

                Forms\Components\Section::make('Akses Venue')
                    ->schema([
                        Forms\Components\Select::make('tenant_id')
                            ->label('Venue')
                            ->options(Tenant::pluck('name', 'id')->toArray())
                            ->required()
                            ->searchable()
                            ->preload()
                            ->helperText('User hanya bisa akses dashboard venue ini'),
                    ]),

                Forms\Components\Section::make('Password')
                    ->schema($isCreate ? [
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->required()
                            ->minLength(8)
                            ->revealable()
                            ->autocomplete('new-password'),
                        Forms\Components\TextInput::make('password_confirmation')
                            ->password()
                            ->required()
                            ->same('password')
                            ->revealable(),
                    ] : [
                        Forms\Components\Placeholder::make('password_hint')
                            ->content('Kosongkan jika tidak ingin mengubah password'),
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->nullable()
                            ->minLength(8)
                            ->revealable()
                            ->autocomplete('new-password'),
                        Forms\Components\TextInput::make('password_confirmation')
                            ->password()
                            ->nullable()
                            ->same('password')
                            ->revealable(),
                    ])->columns(2),

                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->description(fn (TenantUser $record): string => $record->email),

                Tables\Columns\TextColumn::make('tenant.name')
                    ->searchable()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'owner' => 'warning',
                        'staff' => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'owner' => '👑 Owner',
                        'staff' => 'Staff',
                        default => $state,
                    }),

                Tables\Columns\ToggleColumn::make('is_active'),

                Tables\Columns\TextColumn::make('created_at')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Venue')
                    ->relationship('tenant', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('role')
                    ->options([
                        'owner' => 'Owner',
                        'staff' => 'Staff',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('impersonate')
                    ->label('Login sebagai')
                    ->icon('heroicon-o-arrow-right-start-on-rectangle')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn () => auth('admin')->user()?->isSuperAdmin() ?? false)
                    ->url(function (TenantUser $record): string {
                        return '/impersonate/'.$record->id;
                    })
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('resetPassword')
                    ->label('Reset Password')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\TextInput::make('new_password')
                            ->password()
                            ->required()
                            ->minLength(8)
                            ->default(fn () => str()->password(12))
                            ->revealable(),
                    ])
                    ->action(function (TenantUser $record, array $data): void {
                        $record->update(['password' => $data['new_password']]);
                    }),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListTenantUsers::route('/'),
            'create' => Pages\CreateTenantUser::route('/create'),
            'edit' => Pages\EditTenantUser::route('/{record}/edit'),
        ];
    }
}
