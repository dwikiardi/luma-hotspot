<?php

namespace App\Filament\Admin\Resources\AdminUserResource;

use App\Filament\Admin\Traits\HasAdminPermissions;
use App\Models\AdminUser;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AdminUserResource extends Resource
{
    use HasAdminPermissions;

    protected static ?string $model = AdminUser::class;

    protected static ?string $slug = 'admin-users';

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Admin Users';

    protected static ?string $navigationGroup = 'User Management';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return auth('admin')->user()?->isSuperAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        $isCreate = ! $form->getRecord();

        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Akun')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->minLength(3)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\Select::make('role')
                            ->options([
                                'admin' => 'Admin',
                                'super_admin' => 'Super Admin',
                            ])
                            ->required()
                            ->default('admin')
                            ->visible(auth('admin')->user()?->isSuperAdmin() ?? false),
                    ])->columns(2),

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
                            ->default(true)
                            ->disabled(fn ($record) => $record?->id === auth('admin')->id())
                            ->helperText('Admin yang dinonaktifkan tidak bisa login'),
                        Forms\Components\TextInput::make('avatar')
                            ->url()
                            ->nullable()
                            ->maxLength(2048),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('avatar')
                    ->circular()
                    ->defaultImageUrl(url('/images/default-avatar.png')),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (AdminUser $record): string => $record->email),

                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'super_admin' => 'warning',
                        'admin' => 'primary',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'super_admin' => '⭐ Super Admin',
                        'admin' => 'Admin',
                        default => $state,
                    }),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->disabled(fn (AdminUser $record): bool => $record->id === auth('admin')->id()),

                Tables\Columns\TextColumn::make('last_login_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Belum pernah login')
                    ->since(),

                Tables\Columns\TextColumn::make('created_at')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options([
                        'super_admin' => 'Super Admin',
                        'admin' => 'Admin',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Akun'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
                    ->action(function (AdminUser $record, array $data): void {
                        $record->update(['password' => $data['new_password']]);
                    }),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (AdminUser $record): bool => $record->role === 'super_admin' && AdminUser::where('role', 'super_admin')->count() <= 1),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('nonaktifkan')
                        ->label('Nonaktifkan')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function ($records): void {
                            $records->each->update(['is_active' => false]);
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdminUsers::route('/'),
            'create' => Pages\CreateAdminUser::route('/create'),
            'edit' => Pages\EditAdminUser::route('/{record}/edit'),
        ];
    }
}
