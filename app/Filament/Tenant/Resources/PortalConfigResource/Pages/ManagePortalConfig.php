<?php

namespace App\Filament\Tenant\Resources\PortalConfigResource\Pages;

use App\Filament\Tenant\Resources\PortalConfigResource\PortalConfigResource;
use App\Models\PortalConfig;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;

class ManagePortalConfig extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = PortalConfigResource::class;

    protected static string $view = 'filament.tenant.resources.portal-config-resource.pages.manage-portal-config';

    public ?array $data = [];

    public function mount(): void
    {
        $config = $this->getConfig();

        $this->form->fill([
            'branding' => $config->branding ?? ['name' => '', 'color' => '#6366f1', 'logo' => null],
            'active_login_methods' => $config->active_login_methods ?? ['google' => true, 'wa' => true, 'email' => false],
            'grace_period_enabled' => $config->grace_period_enabled ?? true,
            'grace_period_seconds' => $config->grace_period_seconds ?? 7200,
            'custom_login_enabled' => $config->custom_login_enabled ?? true,
            'custom_login_label' => $config->custom_login_label ?? 'Nomor Kamar',
            'custom_login_placeholder' => $config->custom_login_placeholder ?? 'Contoh: 101',
            'hotspot_profile_name' => $config->hotspot_profile_name ?? 'luma-portal',
            'address_pool_name' => $config->address_pool_name ?? 'hotspot-pool',
            'dns_name' => $config->dns_name ?? 'portal.lumanetwork.id',
            'session_timeout' => $config->session_timeout ?? 14400,
            'idle_timeout' => $config->idle_timeout ?? 1800,
            'shared_users' => $config->shared_users ?? 3,
            'room_validation_enabled' => $config->room_validation_enabled ?? false,
            'room_validation_mode' => $config->room_validation_mode ?? 'range',
            'room_validation_config' => $config->room_validation_config ?? [],
        ]);
    }

    protected function getConfig(): PortalConfig
    {
        $tenantId = auth('tenant_users')->user()->tenant_id;
        
        $config = PortalConfig::where('tenant_id', $tenantId)->first();
        
        if (!$config) {
            $config = PortalConfig::create([
                'tenant_id' => $tenantId,
                'branding' => ['name' => '', 'color' => '#6366f1', 'logo' => null],
                'active_login_methods' => ['google' => false, 'wa' => true, 'email' => false, 'room' => true],
                'grace_period_enabled' => true,
                'grace_period_seconds' => 7200,
                'custom_login_enabled' => false,
                'hotspot_profile_name' => 'luma-portal',
                'address_pool_name' => 'hotspot-pool',
                'dns_name' => 'portal.lumanetwork.id',
                'session_timeout' => 14400,
                'idle_timeout' => 1800,
                'shared_users' => 3,
                'room_validation_enabled' => false,
                'room_validation_mode' => 'range',
                'room_validation_config' => [],
            ]);
        }
        
        return $config;
    }

    public function form(Form $form): Form
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
                    ->description('Aktifkan login dengan field custom')
                    ->schema([
                        Forms\Components\Toggle::make('custom_login_enabled')
                            ->label('Aktifkan Login Custom')
                            ->default(false)
                            ->live(),

                        Forms\Components\TextInput::make('custom_login_label')
                            ->label('Label Field')
                            ->placeholder('Contoh: Nomor Kamar')
                            ->maxLength(255)
                            ->visible(fn (Forms\Get $get) => $get('custom_login_enabled')),

                        Forms\Components\TextInput::make('custom_login_placeholder')
                            ->label('Placeholder Input')
                            ->placeholder('Contoh: Masukkan nomor kamar Anda')
                            ->maxLength(255)
                            ->visible(fn (Forms\Get $get) => $get('custom_login_enabled')),
                    ]),

                Forms\Components\Section::make("Validasi Nomor Kamar/Villa")
                    ->description("Konfigurasi validasi untuk nomor kamar, villa, atau ID lainnya")
                    ->schema([
                        Forms\Components\Toggle::make("room_validation_enabled")
                            ->label("Aktifkan Validasi")
                            ->default(false)
                            ->helperText("Hanya izinkan nomor tertentu untuk login")
                            ->live(),

                        Forms\Components\Select::make("room_validation_mode")
                            ->label("Mode Validasi")
                            ->options([
                                "range" => "Rentang Nomor (misal: 1000-1010)",
                                "list" => "Daftar Spesifik (misal: Villa 1, Villa 2)",
                                "pattern" => "Pattern Regex (untuk format khusu)",
                            ])
                            ->default("range")
                            ->visible(fn (Forms\Get $get) => $get("room_validation_enabled"))
                            ->live(),

                        Forms\Components\Textarea::make("room_validation_config")
                            ->label("Konfigurasi Validasi")
                            ->placeholder("Contoh mode Range: [{\"from\": 1000, \"to\": 1010}]\nContoh mode List: [\"Villa 1\", \"Villa 2\", \"Villa 3\"]\nContoh mode Pattern: {\"pattern\": \"^[0-9]{4}$\", \"description\": \"4 digit room number\"}")
                            ->visible(fn (Forms\Get $get) => $get("room_validation_enabled"))
                            ->helperText("Format JSON sesuai mode yang dipilih")
                            ->rows(5),
                    ])->collapsible(),

                Forms\Components\Section::make('MikroTik Hotspot Profile')
                    ->description('Konfigurasi untuk profile hotspot MikroTik.')
                    ->schema([
                        Forms\Components\TextInput::make('hotspot_profile_name')
                            ->label('Nama Hotspot Profile')
                            ->default('luma-portal'),
                        
                        Forms\Components\TextInput::make('address_pool_name')
                            ->label('Nama Address Pool')
                            ->default('hotspot-pool'),
                        
                        Forms\Components\TextInput::make('dns_name')
                            ->label('DNS Name untuk Portal')
                            ->default('portal.lumanetwork.id'),
                        
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
                            ->default('14400'),
                        
                        Forms\Components\Select::make('idle_timeout')
                            ->label('Idle Timeout')
                            ->options([
                                '300' => '5 menit',
                                '600' => '10 menit',
                                '900' => '15 menit',
                                '1800' => '30 menit',
                                '3600' => '1 jam',
                            ])
                            ->default('1800'),
                        
                        Forms\Components\Select::make('shared_users')
                            ->label('Shared Users')
                            ->options([
                                '1' => '1 user',
                                '2' => '2 users',
                                '3' => '3 users',
                                '5' => '5 users',
                                '10' => '10 users',
                            ])
                            ->default('3'),
                    ])->collapsible(),

                Forms\Components\Section::make('Grace Period')
                    ->schema([
                        Forms\Components\Toggle::make('grace_period_enabled')
                            ->label('Aktifkan Grace Period')
                            ->default(true),
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
                            ->default('7200'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $config = $this->getConfig();
        $config->update($this->data);

        Notification::make()->title("Berhasil!")->body("Konfigurasi portal berhasil disimpan.")->success()->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Simpan Perubahan')
                ->submit('save')
                ->keyBindings(['mod+s'])
                ->color('primary'),
        ];
    }
}
