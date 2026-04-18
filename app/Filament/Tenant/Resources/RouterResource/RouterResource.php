<?php

namespace App\Filament\Tenant\Resources\RouterResource;

use App\Models\Router;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\HtmlString;

class RouterResource extends Resource
{
    protected static ?string $model = Router::class;

    protected static ?string $slug = 'routers';

    protected static ?string $tenantOwnershipRelationshipName = 'tenant';

    protected static ?string $navigationIcon = 'heroicon-o-signal';

    protected static ?string $navigationLabel = 'Router & Access Point';

    protected static ?string $navigationGroup = 'Konfigurasi WiFi';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('tenant_id', auth('tenant_users')->user()->tenant_id);
    }

    public static function getMikroTikScript(
        string $nasId,
        string $version = 'v7',
        bool $includeAddressPool = false,
        bool $includeHotspotProfile = false,
        bool $includeHotspotServer = false,
        bool $includeWalledGarden = true,
        string $hotspotAddress = '192.168.88.1',
        string $poolName = 'hotspot-pool',
        string $profileName = 'luma-portal'
    ): string {
        $serverIp = Config::get('app.server_ip', '103.137.140.6');
        $serverUrl = Config::get('app.server_url', 'http://103.137.140.6:8081');
        $radiusSecret = Config::get('services.radius.secret', 'luma_radius_secret');
        $portalUrl = $serverUrl.'/portal';
        $lines = [];
        
        // Remove CIDR notation if present (e.g., 192.168.100.1/24 -> 192.168.100.1)
        $hotspotAddress = preg_replace('/\/\d+$/', '', $hotspotAddress);
        
        // Extract network base for pool example (e.g., 192.168.100.1 -> 192.168.100)
        $networkBase = substr($hotspotAddress, 0, strrpos($hotspotAddress, '.'));
        
        $lines[] = '# MikroTik Configuration - Luma Network';
        $lines[] = '# RouterOS Version: '.$version;
        $lines[] = '';
        $lines[] = '# ============================================================';
        $lines[] = '# MANUAL STEP: Configure IP Pool (Do this first!)';
        $lines[] = '# ============================================================';
        $lines[] = '# For network '.$hotspotAddress.'/24, create pool:';
        $lines[] = '# /ip pool add name='.$poolName.' ranges='.$networkBase.'.10-'.$networkBase.'.254';
        $lines[] = '#';
        $lines[] = '# Or use existing pool already configured on your router';
        $lines[] = '# ============================================================';
        $lines[] = '';
        $lines[] = '# 1. System Identity';
        $lines[] = '/system identity';
        $lines[] = 'set name="'.$nasId.'"';
        $lines[] = '';
        $lines[] = '# 2. RADIUS Server';
        $lines[] = '/radius';
        $lines[] = 'add service=hotspot address='.$serverIp.' secret="'.$radiusSecret.'" authentication-port=1812 accounting-port=1813';
        $lines[] = '';
        if ($includeHotspotProfile) {
            if ($version === 'v7') {
                $lines[] = '# 4. Hotspot Profile (RouterOS v7)';
                $lines[] = '/ip hotspot profile';
                $lines[] = 'add name='.$profileName.' hotspot-address='.$hotspotAddress.' login-by=http-pap,http-chap,cookie http-cookie-lifetime=1d use-radius=yes radius-accounting=yes radius-interim-update=5m http-redirect=yes redirect-url='.$portalUrl.'?nas_id='.$nasId;
                $lines[] = '';
                $lines[] = '# 4b. DNS - Redirect queries to MikroTik (required for captive portal)';
                $lines[] = '/ip dns';
                $lines[] = 'set allow-remote-requests=yes cache-size=4096';
                $lines[] = '';
                $lines[] = '# 4c. NAT - Redirect DNS to MikroTik';
                $lines[] = '/ip firewall nat';
                $lines[] = 'add chain=dstnat protocol=udp dst-port=53 action=redirect to-ports=53 comment="DNS Redirect"';
                $lines[] = 'add chain=dstnat protocol=tcp dst-port=53 action=redirect to-ports=53 comment="DNS Redirect TCP"';
            } else {
                $lines[] = '# 4. Hotspot Profile (RouterOS v6)';
                $lines[] = '/ip hotspot profile';
                $lines[] = 'add name='.$profileName.' hotspot-address='.$hotspotAddress.' login-by=http-pap,http-chap,cookie http-cookie-lifetime=1d use-radius=yes radius-accounting=yes radius-interim-update=5m';
                $lines[] = '';
                $lines[] = '# Note: For v6, upload custom hotspot files (login.html) to redirect to portal';
                $lines[] = '# Download hotspot files from the button below';
            }
            $lines[] = '';
        }
        if ($includeHotspotServer) {
            $lines[] = '# 5. Hotspot Server';
            $lines[] = '/ip hotspot';
            $lines[] = 'add name='.$profileName.'-server interface=bridge-lan address-pool='.$poolName.' profile='.$profileName.' disabled=no';
            $lines[] = '';
        }
        if ($includeWalledGarden) {
            $lines[] = '# Walled Garden (Allow access to portal and CNA detection)';
            $lines[] = '/ip hotspot walled-garden ip';
            $lines[] = 'add dst-address='.$serverIp.' action=accept comment="Luma Portal Server"';
            $lines[] = 'add dst-port=53 protocol=udp action=accept comment="DNS"';
            $lines[] = 'add dst-port=53 protocol=tcp action=accept comment="DNS TCP"';
            $lines[] = 'add dst-host=*.lumanetwork.id action=accept comment="Luma Domain"';
            $lines[] = 'add dst-host=captive.apple.com action=accept comment="iOS CNA"';
            $lines[] = 'add dst-host=*.apple.com action=accept comment="Apple Services"';
            $lines[] = 'add dst-host=connectivitycheck.gstatic.com action=accept comment="Android CNA"';
            $lines[] = 'add dst-host=*.google.com action=accept comment="Google"';
            $lines[] = 'add dst-host=*.googleapis.com action=accept comment="Google APIs"';
            $lines[] = 'add dst-host=*.facebook.com action=accept comment="Facebook"';
            $lines[] = 'add dst-host=*.whatsapp.com action=accept comment="WhatsApp"';
            $lines[] = 'add dst-host=*.whatsapp.net action=accept comment="WhatsApp CDN"';
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    public static function form(Form $form): Form
    {
        $serverIp = Config::get('app.server_ip', '103.137.140.6');
        $serverUrl = Config::get('app.server_url', 'http://103.137.140.6:8081');
        $radiusSecret = Config::get('services.radius.secret', 'luma_radius_secret');

        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Dasar')
                    ->schema([
                        Forms\Components\TextInput::make('name')->label('Nama Router')->required()->maxLength(255)->placeholder('Contoh: Router Lobby'),
                        Forms\Components\TextInput::make('nas_identifier')->label('NAS Identifier')->required()->unique(ignoreRecord: true)->maxLength(255)->placeholder('Contoh: hotel-lobby-01')->helperText('ID unik untuk MikroTik RADIUS.')->reactive()->afterStateUpdated(fn () => null)->extraAttributes(['oninput' => 'window.updateMikroTikScript&&window.updateMikroTikScript()']),
                        Forms\Components\TextInput::make('location')->label('Lokasi')->maxLength(255)->placeholder('Contoh: Lantai 1, Lobby'),
                    ])->columns(2),
                Forms\Components\Section::make('Konfigurasi Jaringan')
                    ->schema([
                        Forms\Components\TextInput::make('ip_address')->label('IP Address Router (Publik)')->placeholder('192.168.1.1')->helperText('IP publik router, biarkan kosong jika router di belakang NAT.'),
                        Forms\Components\TextInput::make('hotspot_address')->label('Hotspot IP Address')->placeholder('192.168.88.1')->helperText('IP address MikroTik yang diakses client (utk redirect setelah login).')->reactive()->extraAttributes(['oninput' => 'window.updateMikroTikScript&&window.updateMikroTikScript()']),
                        Forms\Components\TextInput::make('model')->label('Model Router')->maxLength(255)->placeholder('Contoh: MikroTik RB951Ui-2HnD'),
                        Forms\Components\Select::make('routeros_version')->label('RouterOS Version')->options(['v6' => 'RouterOS v6 (requires hotspot files)', 'v7' => 'RouterOS v7 (built-in HTTP redirect)'])->default('v7')->required()->helperText('Pilih versi MikroTik RouterOS.')->reactive(),
                    ])->columns(2),
                Forms\Components\Section::make('Status & Catatan')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')->label('Aktif')->default(true),
                        Forms\Components\Textarea::make('notes')->label('Catatan')->rows(3),
                    ]),
                Forms\Components\Section::make('Script MikroTik')
                    ->schema([
                        Forms\Components\Placeholder::make('script_ui')
                            ->label('')
                            ->content(new HtmlString(
                                '<div x-data x-init="setTimeout(function(){if(window.updateMikroTikScript)window.updateMikroTikScript();},200)" class="space-y-4">'.
                                '<div class="bg-blue-50 border border-blue-200 rounded-lg p-4">'.
                                '<p class="text-sm text-blue-800 font-medium mb-3">Pilihan Setup (centang sesuai kebutuhan):</p>'.
                                '<div class="grid grid-cols-1 md:grid-cols-2 gap-3">'.
                                '<label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" id="opt_pool" onchange="updateMikroTikScript()" class="rounded"> <span>Setup Address Pool</span></label>'.
                                '<label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" id="opt_profile" onchange="updateMikroTikScript()" class="rounded"> <span>Setup Hotspot Profile</span></label>'.
                                '<label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" id="opt_server" onchange="updateMikroTikScript()" class="rounded"> <span>Setup Hotspot Server</span></label>'.
                                '<label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" id="opt_walled" checked onchange="updateMikroTikScript()" class="rounded"> <span>Setup Walled Garden (Portal Access)</span></label>'.
                                '</div>'.
                                '<div id="hotspot_config" class="mt-3 hidden">'.
                                '<p class="text-xs text-gray-600 mb-1">Hotspot Configuration:</p>'.
                                '<div class="grid grid-cols-3 gap-2 mt-2">'.
                                '<input type="text" id="hotspot_ip" value="192.168.88.1" placeholder="Hotspot IP" class="text-sm border rounded px-2 py-1" onchange="updateMikroTikScript()">'.
                                '<input type="text" id="pool_name" value="hotspot-pool" placeholder="Pool Name" class="text-sm border rounded px-2 py-1" onchange="updateMikroTikScript()">'.
                                '<input type="text" id="profile_name" value="luma-portal" placeholder="Profile Name" class="text-sm border rounded px-2 py-1" onchange="updateMikroTikScript()">'.
                                '</div></div>'.
                                '<div id="v6_download" class="mt-4 hidden">'.
                                '<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">'.
                                '<p class="text-sm text-yellow-800 font-medium">RouterOS v6 requires custom hotspot files:</p>'.
                                '<ol class="text-xs text-yellow-700 mt-2 list-decimal list-inside">'.
                                '<li>Download the hotspot files below</li>'.
                                '<li>Upload to MikroTik via WinBox → Files → hotspot folder</li>'.
                                '<li>Restart hotspot service</li>'.
                                '</ol>'.
                                '<a id="v6_download_link" href="/mikrotik/hotspot-files" download style="background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); color: white; text-decoration: none; display: inline-flex; align-items: center; padding: 10px 16px; border-radius: 8px; font-weight: 600; font-size: 14px; margin-top: 12px; box-shadow: 0 4px 6px -1px rgba(249, 115, 22, 0.4); transition: all 0.2s;" onmouseover="this.style.transform=\'translateY(-1px)\';this.style.boxShadow=\'0 6px 8px -1px rgba(249, 115, 22, 0.5)\'" onmouseout="this.style.transform=\'translateY(0)\';this.style.boxShadow=\'0 4px 6px -1px rgba(249, 115, 22, 0.4)\'"><svg style="width: 16px; height: 16px; margin-right: 8px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>Download Hotspot Files (ZIP)</a>'.
                                '</div></div>'.
                                '<div class="flex justify-end mt-6 mb-4">'.
                                '<button type="button" onclick="copyMikroTikScript(this)" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer; box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.4); transition: all 0.2s;" onmouseover="this.style.transform=\'translateY(-1px)\';this.style.boxShadow=\'0 6px 8px -1px rgba(59, 130, 246, 0.5)\'" onmouseout="this.style.transform=\'translateY(0)\';this.style.boxShadow=\'0 4px 6px -1px rgba(59, 130, 246, 0.4)\'">Copy Script</button>'.
                                '</div>'.
                                '<pre id="mikrotik-script-box" class="font-mono text-xs bg-slate-900 text-green-400 p-4 rounded-lg whitespace-pre overflow-x-auto max-h-96 overflow-y-auto"># Ketik NAS Identifier di atas untuk generate script</pre></div>'.
                                '<script src="/js/mikrotik-script-generator.js?v=3"></script>'.
                                '<script>window.configServerIp="'.e($serverIp).'";window.configServerUrl="'.e($serverUrl).'";window.configRadiusSecret="'.e($radiusSecret).'";setTimeout(function(){window.updateMikroTikScript&&window.updateMikroTikScript();},100);</script>'
                            )),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nama')->searchable()->description(fn (Router $record): string => $record->nas_identifier),
                Tables\Columns\TextColumn::make('ip_address')->label('IP Address')->fontFamily('mono'),
                Tables\Columns\TextColumn::make('routeros_version')->label('ROS Version')->badge()->color(fn (string $state): string => match ($state) {
                    'v7' => 'success', 'v6' => 'warning', default => 'gray',
                }),
                Tables\Columns\TextColumn::make('location')->label('Lokasi')->placeholder('-'),
                Tables\Columns\IconColumn::make('is_active')->label('Status')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->label('Dibuat')->date()->sortable(),
            ])
            ->filters([Tables\Filters\TernaryFilter::make('is_active')->label('Status')])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->paginated([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRouters::route('/'),
            'create' => Pages\CreateRouter::route('/create'),
            'edit' => Pages\EditRouter::route('/{record}/edit'),
        ];
    }
}
