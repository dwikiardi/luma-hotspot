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
            'grace_period_seconds' => $this->toSuffix($config->grace_period_seconds ?? 7200),
            'custom_login_enabled' => $config->custom_login_enabled ?? true,
            'custom_login_label' => $config->custom_login_label ?? 'Nomor Kamar',
            'custom_login_placeholder' => $config->custom_login_placeholder ?? 'Contoh: 101',
            'hotspot_profile_name' => $config->hotspot_profile_name ?? 'luma-portal',
            'address_pool_name' => $config->address_pool_name ?? 'hotspot-pool',
            'dns_name' => $config->dns_name ?? 'portal.lumanetwork.id',
            'session_timeout' => $this->toSuffix($config->session_timeout ?? 0),
            'idle_timeout' => $this->toSuffix($config->idle_timeout ?? 0),
            'shared_users' => $config->shared_users ?? 3,
            'room_validation_enabled' => $config->room_validation_enabled ?? false,
            'room_validation_mode' => $config->room_validation_mode ?? 'range',
            'room_validation_config' => $config->room_validation_config ?? [],
            'timezone' => $config->tenant->timezone ?? 'Asia/Jakarta',
        ]);
    }

    /**
     * Convert MikroTik-style duration to seconds.
     * "10m" = 600, "2h" = 7200, "1d" = 86400, "30s" = 30, "3600" = 3600
     */
    protected function toSeconds(?string $val): int
    {
        if (empty($val) || $val === '0') return 0;
        $val = trim(strtolower($val));

        if (str_ends_with($val, 'd')) return (int) $val * 86400;
        if (str_ends_with($val, 'h')) return (int) $val * 3600;
        if (str_ends_with($val, 'm')) return (int) $val * 60;
        if (str_ends_with($val, 's')) return (int) $val;

        return (int) $val;
    }

    /**
     * Convert seconds to MikroTik-style readable format.
     * 86400 => "1d", 7200 => "2h", 600 => "10m", 0 => "0"
     */
    protected function toSuffix(?int $seconds): string
    {
        if (empty($seconds)) return '0';
        if ($seconds >= 86400 && $seconds % 86400 === 0) return ($seconds / 86400) . 'd';
        if ($seconds >= 3600 && $seconds % 3600 === 0) return ($seconds / 3600) . 'h';
        if ($seconds >= 60 && $seconds % 60 === 0) return ($seconds / 60) . 'm';
        return (string) $seconds;
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
                'session_timeout' => 0,
                'idle_timeout' => 0,
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
                        
                        Forms\Components\TextInput::make('session_timeout')
                            ->label('Session Timeout')
                            ->nullable()
                            ->default('0')
                            ->helperText('Format MikroTik: 10m, 2h, 1d, atau detik. 0 = tanpa batas.'),

                        Forms\Components\TextInput::make('idle_timeout')
                            ->label('Idle Timeout')
                            ->nullable()
                            ->default('0')
                            ->helperText('Format MikroTik: 10m, 2h, 1d, atau detik. 0 = tanpa batas.'),

                        Forms\Components\TextInput::make('shared_users')
                            ->label('Shared Users')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->default(3)
                            ->helperText('Jumlah device yang bisa login bersamaan'),
                    ])->collapsible(),

                Forms\Components\Section::make('Zona Waktu')
                    ->description('Waktu lokal venue untuk sinkronisasi jam di dashboard dan log')
                    ->schema([
                        Forms\Components\Select::make('timezone')
                            ->label('Timezone')
                            ->options([
                                'UTC' => 'UTC',
                                'Asia/Jakarta' => 'Asia/Jakarta (WIB +7)',
                                'Asia/Makassar' => 'Asia/Makassar (WITA +8)',
                                'Asia/Jayapura' => 'Asia/Jayapura (WIT +9)',
                                'Asia/Singapore' => 'Asia/Singapore (+8)',
                                'Asia/Kuala_Lumpur' => 'Asia/Kuala Lumpur (+8)',
                                'Asia/Bangkok' => 'Asia/Bangkok (+7)',
                                'Asia/Tokyo' => 'Asia/Tokyo (+9)',
                                'Asia/Seoul' => 'Asia/Seoul (+9)',
                                'Asia/Shanghai' => 'Asia/Shanghai (+8)',
                                'Asia/Dubai' => 'Asia/Dubai (+4)',
                                'Asia/Riyadh' => 'Asia/Riyadh (+3)',
                                'Europe/London' => 'Europe/London (+0)',
                                'Europe/Paris' => 'Europe/Paris (+1/+2)',
                                'Europe/Berlin' => 'Europe/Berlin (+1/+2)',
                                'America/New_York' => 'America/New York (-5/-4)',
                                'America/Chicago' => 'America/Chicago (-6/-5)',
                                'America/Los_Angeles' => 'America/Los Angeles (-8/-7)',
                                'Australia/Sydney' => 'Australia/Sydney (+10/+11)',
                            ])
                            ->searchable()
                            ->default('Asia/Jakarta')
                            ->required(),
                    ]),

                Forms\Components\Section::make('Grace Period')
                    ->schema([
                        Forms\Components\Toggle::make('grace_period_enabled')
                            ->label('Aktifkan Grace Period')
                            ->default(true),
                        Forms\Components\TextInput::make('grace_period_seconds')
                            ->label('Durasi Grace Period')
                            ->required()
                            ->default('2h')
                            ->helperText('Format: 30m, 2h, 4h, 1d, atau detik. Semakin lama semakin nyaman untuk tamu.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $config = $this->getConfig();

        // Convert suffix values ke seconds sebelum simpan
        $data = $this->data;
        $data['session_timeout'] = $this->toSeconds($this->data['session_timeout'] ?? '0');
        $data['idle_timeout'] = $this->toSeconds($this->data['idle_timeout'] ?? '0');
        $data['grace_period_seconds'] = $this->toSeconds($this->data['grace_period_seconds'] ?? '7200');

        $config->update($data);

        $tenantId = auth('tenant_users')->user()->tenant_id;

        // Save timezone to tenant
        if (isset($this->data['timezone'])) {
            \App\Models\Tenant::where('id', $tenantId)->update(['timezone' => $this->data['timezone']]);
        }

        $timeout = $data['session_timeout'] ?? 0;
        $idleTimeout = $data['idle_timeout'] ?? 0;
        $sharedUsers = $this->data['shared_users'] ?? 3;

        $errors = [];

        // Push session/idle timeout ke FreeRADIUS radreply untuk setiap user di router tenant
        if ($timeout > 0 || $idleTimeout > 0) {
            try {
                $routerIds = \App\Models\Router::where('tenant_id', $tenantId)->pluck('id');
                $userIds = \Illuminate\Support\Facades\DB::table('user_sessions')
                    ->whereIn('router_id', $routerIds)
                    ->distinct('user_id')
                    ->pluck('user_id');
                $users = \App\Models\User::whereIn('id', $userIds)->get(['identity_value']);

                foreach ($users as $user) {
                    // Hapus reply lama
                    \Illuminate\Support\Facades\DB::table('radreply')
                        ->where('username', $user->identity_value)
                        ->whereIn('attribute', ['Session-Timeout', 'Idle-Timeout'])
                        ->delete();

                    // Insert baru jika > 0
                    if ($timeout > 0) {
                        \Illuminate\Support\Facades\DB::table('radreply')->insert([
                            'username' => $user->identity_value,
                            'attribute' => 'Session-Timeout',
                            'op' => ':=',
                            'value' => (string) $timeout,
                        ]);
                    }
                    if ($idleTimeout > 0) {
                        \Illuminate\Support\Facades\DB::table('radreply')->insert([
                            'username' => $user->identity_value,
                            'attribute' => 'Idle-Timeout',
                            'op' => ':=',
                            'value' => (string) $idleTimeout,
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                $errors[] = 'RADIUS: ' . $e->getMessage();
            }
        }

        // Push shared_users ke MikroTik
        try {
            $routers = \App\Models\Router::where('tenant_id', $tenantId)->get();
            $mkService = app(\App\Services\MikroTikApiService::class);
            foreach ($routers as $router) {
                $mkService->setHotspotConfig($router, (int) $timeout, (int) $idleTimeout, (int) $sharedUsers);
            }
        } catch (\Throwable $e) {
            $errors[] = 'MikroTik: ' . $e->getMessage();
        }

        if (empty($errors)) {
            Notification::make()->title("Berhasil!")->body("Konfigurasi disimpan. Timeout dipush ke FreeRADIUS, shared_users ke MikroTik.")->success()->send();
        } else {
            Notification::make()->title("Berhasil dengan catatan")->body("Tersimpan di database. " . implode(' | ', $errors))->warning()->send();
        }
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
