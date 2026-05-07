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

        if (empty($routerIds)) {
            return User::where('id', 0);
        }

        return User::whereHas('sessions', function ($q) use ($routerIds) {
                $q->whereIn('router_id', $routerIds);
            })
            ->orderByDesc('id')
            ->withCount(['sessions as active_sessions_count' => function ($q) {
                $q->where('status', 'active');
            }])
            ->withCount(['sessions as total_sessions_count']);
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
                Tables\Columns\TextColumn::make('active_sessions_count')
                    ->label('Online')
                    ->badge(),
                Tables\Columns\TextColumn::make('total_sessions_count')
                    ->label('Total Login'),
                Tables\Columns\TextColumn::make('last_login')
                    ->label('Login Terakhir')
                    ->formatStateUsing(fn ($record) => \App\Models\UserSession::where('user_id', $record->id)->max('login_at'))
                    ->dateTime('d M H:i')
                    ->sortable(),
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
            ->defaultSort('id', 'desc')
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Edit'),
                Tables\Actions\DeleteAction::make()
                    ->label('Hapus')
                    ->modalHeading('Hapus pengguna ini?')
                    ->modalDescription('Semua data pengguna termasuk sesi akan dihapus.')
                    ->successNotificationTitle('Pengguna berhasil dihapus')
                    ->before(function (User $record) {
                        // Delete semua analytics untuk user ini
                        DB::table('analytics_events')->where('user_id', $record->id)->delete();
                        // Delete analytics untuk semua device user ini
                        DB::table('analytics_events')
                            ->whereIn('device_id', function ($q) use ($record) {
                                $q->select('id')->from('devices')->where('user_id', $record->id);
                            })->delete();
                        \App\Models\UserSession::where('user_id', $record->id)->delete();
                        \App\Models\Device::where('user_id', $record->id)->delete();
                        DB::table('device_fingerprints')->where('user_id', $record->id)->delete();
                        DB::table('visitor_profiles')->where('user_id', $record->id)->delete();
                        DB::table('radcheck')->where('username', $record->identity_value)->delete();
                        DB::table('radreply')->where('username', $record->identity_value)->delete();
                        DB::table('radusergroup')->where('username', $record->identity_value)->delete();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->label('Hapus yang dipilih')
                    ->modalHeading('Hapus pengguna yang dipilih?')
                    ->modalDescription('Semua data akan dihapus permanen.')
                    ->before(function (\Illuminate\Support\Collection $records) {
                        foreach ($records as $record) {
                            DB::table('analytics_events')->where('user_id', $record->id)->delete();
                            DB::table('analytics_events')
                                ->whereIn('device_id', function ($q) use ($record) {
                                    $q->select('id')->from('devices')->where('user_id', $record->id);
                                })->delete();
                            \App\Models\UserSession::where('user_id', $record->id)->delete();
                            \App\Models\Device::where('user_id', $record->id)->delete();
                            DB::table('device_fingerprints')->where('user_id', $record->id)->delete();
                            DB::table('visitor_profiles')->where('user_id', $record->id)->delete();
                            DB::table('radcheck')->where('username', $record->identity_value)->delete();
                            DB::table('radreply')->where('username', $record->identity_value)->delete();
                            DB::table('radusergroup')->where('username', $record->identity_value)->delete();
                        }
                    }),
            ]);
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