<?php

namespace App\Filament\Admin\Resources\TenantResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class PortalConfigRelationManager extends RelationManager
{
    protected static string $relationship = 'portalConfig';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Metode Login')
                    ->schema([
                        Forms\Components\Toggle::make('active_login_methods.google')
                            ->label('Login Google'),
                        Forms\Components\Toggle::make('active_login_methods.wa')
                            ->label('Login WhatsApp'),
                        Forms\Components\Toggle::make('active_login_methods.email')
                            ->label('Login Email'),
                        Forms\Components\Toggle::make('active_login_methods.room')
                            ->label('Login Nomor Kamar'),
                        Forms\Components\Toggle::make('active_login_methods.promo')
                            ->label('Login Kode Promo'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Branding')
                    ->schema([
                        Forms\Components\TextInput::make('branding.name')
                            ->label('Nama Venue'),
                        Forms\Components\ColorPicker::make('branding.color')
                            ->label('Warna Utama'),
                        Forms\Components\TextInput::make('branding.logo')
                            ->label('Logo URL'),
                    ]),
                Forms\Components\Section::make('Grace Period')
                    ->schema([
                        Forms\Components\Toggle::make('grace_period_enabled')
                            ->label('Grace Period Enabled'),
                        Forms\Components\TextInput::make('grace_period_seconds')
                            ->label('Custom Seconds')
                            ->numeric()
                            ->helperText('Contoh: 3600 = 1 jam, 7200 = 2 jam'),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table;
    }
}
