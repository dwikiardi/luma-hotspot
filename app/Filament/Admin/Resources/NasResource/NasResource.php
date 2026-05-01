<?php

namespace App\Filament\Admin\Resources\NasResource;

use App\Filament\Admin\Resources\NasResource\Pages;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

use Illuminate\Database\Eloquent\Builder;

class NasResource extends Resource
{
    protected static ?string $model = \App\Models\Router::class;

    protected static ?string $slug = 'nas';

    protected static ?string $navigationIcon = 'heroicon-o-server';

    protected static ?string $navigationLabel = 'NAS / Routers';

    protected static ?string $navigationGroup = 'RADIUS';

    protected static ?int $navigationSort = 2;

    protected static ?string $label = 'NAS';

    protected static ?string $pluralLabel = 'NAS';

    public static function getEloquentQuery(): Builder
    {
        return \App\Models\Router::query();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(\App\Models\Router::query()->orderByDesc('id'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Router Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('nas_identifier')
                    ->label('NAS Identifier')
                    ->searchable()
                    ->copyable()
                    ->fontFamily('mono'),
                Tables\Columns\TextColumn::make('hotspot_address')
                    ->label('Hotspot IP')
                    ->default('-')
                    ->copyable()
                    ->fontFamily('mono'),
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Tenant')
                    ->searchable(),
                Tables\Columns\TextColumn::make('nas_secret')
                    ->label('RADIUS Secret')
                    ->formatStateUsing(function ($record) {
                        $nas = DB::table('nas')
                            ->where('shortname', $record->nas_identifier)
                            ->first();
                        return $nas?->secret ?? '-';
                    })
                    ->copyable()
                    ->fontFamily('mono'),
                Tables\Columns\TextColumn::make('nas_ip')
                    ->label('NAS IP')
                    ->formatStateUsing(function ($record) {
                        $nas = DB::table('nas')
                            ->where('shortname', $record->nas_identifier)
                            ->first();
                        return $nas?->nasname ?? '-';
                    })
                    ->fontFamily('mono'),
                Tables\Columns\TextColumn::make('active_users')
                    ->label('Active Users')
                    ->formatStateUsing(fn ($record) => \App\Models\UserSession::where('router_id', $record->id)->where('status', 'active')->count())
                    ->badge(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Tenant')
                    ->relationship('tenant', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Router Name')
                    ->required(),
                Forms\Components\TextInput::make('nas_identifier')
                    ->label('NAS Identifier')
                    ->required()
                    ->helperText('Must match the NAS-Identifier in RADIUS requests from this router'),
                Forms\Components\TextInput::make('hotspot_address')
                    ->label('Hotspot IP Address')
                    ->placeholder('e.g., 192.168.100.1')
                    ->helperText('MikroTik hotspot IP for login redirects'),
                Forms\Components\Select::make('tenant_id')
                    ->label('Tenant')
                    ->relationship('tenant', 'name')
                    ->required(),
                Forms\Components\Fieldset::make('RADIUS Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('nas_ip')
                            ->label('NAS IP Address')
                            ->helperText('IP address of the NAS as seen by FreeRADIUS')
                            ->dehydrated(false)
                            ->formatStateUsing(function ($record) {
                                if (! $record) return '';
                                $nas = DB::table('nas')->where('shortname', $record->nas_identifier)->first();
                                return $nas?->nasname ?? '';
                            }),
                        Forms\Components\TextInput::make('nas_secret')
                            ->label('RADIUS Secret')
                            ->helperText('Shared secret between NAS and FreeRADIUS')
                            ->dehydrated(false)
                            ->formatStateUsing(function ($record) {
                                if (! $record) return '';
                                $nas = DB::table('nas')->where('shortname', $record->nas_identifier)->first();
                                return $nas?->secret ?? '';
                            }),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNas::route('/'),
            'edit' => Pages\EditNas::route('/{record}/edit'),
        ];
    }
}