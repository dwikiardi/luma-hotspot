<?php

namespace App\Filament\Tenant\Resources\RadUserResource;

use App\Filament\Tenant\Resources\RadUserResource\Pages;
use App\Models\Router;
use App\Models\User;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class RadUserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'rad-users';

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Pengguna WiFi';

    protected static ?string $navigationGroup = 'Pengunjung';

    protected static ?int $navigationSort = 2;

    protected static ?string $label = 'Pengguna WiFi';

    protected static ?string $pluralLabel = 'Pengguna WiFi';

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $tenantId = filament()->getTenant()?->id;
        $routerIds = Router::where('tenant_id', $tenantId)->pluck('id')->toArray();

        return User::whereHas('sessions', function ($q) use ($routerIds) {
            $q->whereIn('router_id', $routerIds);
        })->orWhereHas('devices')->orderByDesc('id');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('identity_value')
                    ->label('Username')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('identity_type')
                    ->label('Tipe')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'room' => 'primary',
                        'google' => 'danger',
                        'wa' => 'success',
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
                Tables\Columns\TextColumn::make('password_value')
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
                    ->label('Online')
                    ->formatStateUsing(fn ($record) => \App\Models\UserSession::where('user_id', $record->id)->where('status', 'active')->count())
                    ->badge(),
                Tables\Columns\TextColumn::make('total_sessions')
                    ->label('Total Login')
                    ->formatStateUsing(fn ($record) => \App\Models\UserSession::where('user_id', $record->id)->count()),
                Tables\Columns\TextColumn::make('last_login')
                    ->label('Login Terakhir')
                    ->formatStateUsing(fn ($record) => \App\Models\UserSession::where('user_id', $record->id)->max('login_at'))
                    ->dateTime('d M H:i'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('identity_type')
                    ->label('Tipe')
                    ->options([
                        'room' => 'Kamar',
                        'google' => 'Google',
                        'wa' => 'WhatsApp',
                        'email' => 'Email',
                    ]),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('identity_value')
                    ->label('Username / Nomor Kamar')
                    ->required(),
                Forms\Components\Select::make('identity_type')
                    ->label('Tipe')
                    ->options([
                        'room' => 'Nomor Kamar',
                        'google' => 'Google',
                        'wa' => 'WhatsApp',
                        'email' => 'Email',
                    ])
                    ->default('room')
                    ->required(),
                Forms\Components\TextInput::make('name')
                    ->label('Nama'),
                Forms\Components\TextInput::make('password')
                    ->label('Password RADIUS')
                    ->password()
                    ->revealable(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRadUsers::route('/'),
        ];
    }
}