<?php

namespace App\Filament\Admin\Resources\RadUserResource;

use App\Filament\Admin\Resources\RadUserResource\Pages;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class RadUserResource extends Resource
{
    protected static ?string $model = \App\Models\User::class;

    protected static ?string $slug = 'rad-users';

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'RADIUS Users';

    protected static ?string $navigationGroup = 'RADIUS';

    protected static ?int $navigationSort = 1;

    protected static ?string $label = 'RADIUS User';

    protected static ?string $pluralLabel = 'RADIUS Users';

    public static function table(Table $table): Table
    {
        return $table
            ->query(\App\Models\User::query()->orderByDesc('id'))
            ->columns([
                Tables\Columns\TextColumn::make('identity_value')
                    ->label('Username')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('identity_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'room' => 'primary',
                        'google' => 'danger',
                        'wa' => 'success',
                        'email' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'room' => 'Kamar',
                        'wa' => 'WhatsApp',
                        default => ucfirst($state),
                    }),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->default('-'),
                Tables\Columns\TextColumn::make('radcheck_password')
                    ->label('Password')
                    ->formatStateUsing(function ($record) {
                        $check = DB::table('radcheck')
                            ->where('username', $record->identity_value)
                            ->where('attribute', 'Cleartext-Password')
                            ->first();
                        return $check?->value ?? '-';
                    })
                    ->copyable()
                    ->fontFamily('mono'),
                Tables\Columns\TextColumn::make('active_sessions')
                    ->label('Sesi Aktif')
                    ->formatStateUsing(function ($record) {
                        return \App\Models\UserSession::where('user_id', $record->id)
                            ->where('status', 'active')
                            ->count();
                    })
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('groups')
                    ->label('Group')
                    ->formatStateUsing(function ($record) {
                        $groups = DB::table('radusergroup')
                            ->where('username', $record->identity_value)
                            ->pluck('groupname')
                            ->toArray();
                        return implode(', ', $groups) ?: '-';
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('identity_type')
                    ->label('Type')
                    ->options([
                        'room' => 'Kamar',
                        'google' => 'Google',
                        'wa' => 'WhatsApp',
                        'email' => 'Email',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('identity_value')
                    ->label('Username / Room Number')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('identity_type')
                    ->label('Type')
                    ->options([
                        'room' => 'Nomor Kamar',
                        'google' => 'Google',
                        'wa' => 'WhatsApp',
                        'email' => 'Email',
                    ])
                    ->default('room')
                    ->required(),
                Forms\Components\TextInput::make('name')
                    ->label('Name')
                    ->maxLength(255),
                Forms\Components\TextInput::make('password')
                    ->label('RADIUS Password')
                    ->password()
                    ->revealable()
                    ->helperText('Cleartext-Password for RADIUS authentication. Leave empty to keep existing.')
                    ->dehydrated(fn ($state) => filled($state)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRadUsers::route('/'),
        ];
    }
}