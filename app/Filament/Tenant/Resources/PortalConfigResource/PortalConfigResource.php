<?php

namespace App\Filament\Tenant\Resources\PortalConfigResource;

use App\Models\PortalConfig;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;

class PortalConfigResource extends Resource
{
    protected static ?string $model = PortalConfig::class;

    protected static ?string $slug = 'portal-config';

    protected static ?string $tenantOwnershipRelationshipName = 'tenant';

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Konfigurasi Portal';

    protected static ?string $navigationGroup = 'Konfigurasi WiFi';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $tenantId = filament()->getTenant()?->id;

        return parent::getEloquentQuery()->where('tenant_id', $tenantId);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Branding')
                    ->schema([
                        Forms\Components\TextInput::make('branding.name')
                            ->label('Nama Venue')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\ColorPicker::make('branding.color')
                            ->label('Warna Tema')
                            ->default('#6366f1'),
                        Forms\Components\TextInput::make('branding.logo')
                            ->label('URL Logo')
                            ->url()
                            ->nullable(),
                    ])->columns(2),

                Forms\Components\Section::make('Metode Login')
                    ->description('Pilih metode login yang tersedia di captive portal')
                    ->schema([
                        Forms\Components\Toggle::make('active_login_methods.google')
                            ->label('Google')
                            ->default(true)
                            ->helperText('Login dengan akun Google'),
                        Forms\Components\Toggle::make('active_login_methods.wa')
                            ->label('WhatsApp')
                            ->default(true)
                            ->helperText('Login dengan OTP WhatsApp'),
                        Forms\Components\Toggle::make('active_login_methods.email')
                            ->label('Email')
                            ->default(false)
                            ->helperText('Login dengan email'),
                    ])->columns(2),

                Forms\Components\Section::make('Login Custom')
                    ->description('Aktifkan login dengan field custom (nomor kamar, nama villa, dll)')
                    ->schema([
                        Forms\Components\Toggle::make('custom_login_enabled')
                            ->label('Aktifkan Login Custom')
                            ->default(false)
                            ->live(),

                        Forms\Components\TextInput::make('custom_login_label')
                            ->label('Label Field')
                            ->placeholder('Contoh: Nomor Kamar, Nama Villa, No. Unit')
                            ->maxLength(255)
                            ->visible(fn (Forms\Get $get) => $get('custom_login_enabled')),

                        Forms\Components\TextInput::make('custom_login_placeholder')
                            ->label('Placeholder Input')
                            ->placeholder('Contoh: Masukkan nomor kamar Anda')
                            ->maxLength(255)
                            ->visible(fn (Forms\Get $get) => $get('custom_login_enabled')),
                    ]),

                Forms\Components\Section::make('MikroTik Hotspot Profile')
                    ->description('Konfigurasi untuk profile hotspot MikroTik. Isi sesuai konfigurasi router.')
                    ->schema([
                        Forms\Components\TextInput::make('hotspot_profile_name')
                            ->label('Nama Hotspot Profile')
                            ->default('luma-portal')
                            ->placeholder('luma-portal')
                            ->helperText('Nama profile hotspot di MikroTik'),

                        Forms\Components\TextInput::make('address_pool_name')
                            ->label('Nama Address Pool')
                            ->default('hotspot-pool')
                            ->placeholder('hotspot-pool')
                            ->helperText('Nama pool IP untuk user hotspot'),

                        Forms\Components\TextInput::make('dns_name')
                            ->label('DNS Name untuk Portal')
                            ->default('portal.lumanetwork.id')
                            ->placeholder('portal.lumanetwork.id')
                            ->helperText('DNS name captive portal (bisa beda dengan router)'),

                        Forms\Components\Select::make('session_timeout')
                            ->label('Session Timeout')
                            ->options([
                                '1800' => '30 menit',
                                '3600' => '1 jam',
                                '7200' => '2 jam',
                                '14400' => '4 jam',
                                '28800' => '8 jam',
                                '43200' => '12 jam',
                                '86400' => '24 jam',
                            ])
                            ->default('14400')
                            ->helperText('Durasi maksimal 1 sesi'),

                        Forms\Components\Select::make('idle_timeout')
                            ->label('Idle Timeout')
                            ->options([
                                '300' => '5 menit',
                                '600' => '10 menit',
                                '900' => '15 menit',
                                '1800' => '30 menit',
                                '3600' => '1 jam',
                            ])
                            ->default('1800')
                            ->helperText('Auto logout jika tidak ada aktivitas'),

                        Forms\Components\Select::make('shared_users')
                            ->label('Shared Users')
                            ->options([
                                '1' => '1 user',
                                '2' => '2 users',
                                '3' => '3 users',
                                '5' => '5 users',
                                '10' => '10 users',
                            ])
                            ->default('3')
                            ->helperText('Jumlah device yang bisa login bersamaan'),
                    ])->collapsible(),

                Forms\Components\Section::make('Grace Period')
                    ->schema([
                        Forms\Components\Toggle::make('grace_period_enabled')
                            ->label('Aktifkan Grace Period')
                            ->default(true)
                            ->helperText('User tidak perlu login ulang jika reconnect dalam waktu tertentu'),
                        Forms\Components\Select::make('grace_period_seconds')
                            ->label('Durasi Grace Period')
                            ->options([
                                '900' => '15 menit',
                                '1800' => '30 menit',
                                '3600' => '1 jam',
                                '7200' => '2 jam',
                                '14400' => '4 jam',
                                '28800' => '8 jam',
                                '43200' => '12 jam',
                                '86400' => '24 jam',
                            ])
                            ->required()
                            ->default('7200')
                            ->helperText('Semakin lama, semakin nyaman untuk tamu'),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManagePortalConfig::route('/'),
        ];
    }
}
