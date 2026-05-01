<?php

namespace App\Filament\Tenant\Resources\VisitorSessionResource;

use App\Models\Router;
use App\Models\UserSession;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class VisitorSessionResource extends Resource
{
    protected static ?string $model = UserSession::class;
    protected static ?string $slug = "visitor-sessions";
    protected static ?string $navigationIcon = "heroicon-o-user-group";
    protected static ?string $navigationLabel = "Pengunjung Aktif";
    protected static ?string $navigationGroup = "Pengunjung";
    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(function () {
                $tenantId = filament()->getTenant()?->id;
                $routerIds = Router::where('tenant_id', $tenantId)->pluck('id')->toArray();

                if (empty($routerIds)) {
                    return UserSession::where('id', 0);
                }

                return UserSession::whereIn('router_id', $routerIds)
                    ->with(['user', 'router'])
                    ->orderBy('login_at', 'desc');
            })
            ->columns([
                Tables\Columns\TextColumn::make("user.name")
                    ->label("Nama")
                    ->default(fn ($record) => $record->user?->name ?? $record->user?->identity_value ?? '-')
                    ->searchable(),
                Tables\Columns\TextColumn::make("user.identity_value")
                    ->label("User")
                    ->formatStateUsing(function ($state, UserSession $record): string {
                        if ($record->user) {
                            $type = $record->user->identity_type ?? "room";
                            $val = $record->user->identity_value ?? "-";
                            return $type === "phone" ? "+" . $val : $val;
                        }
                        return "-";
                    }),
                Tables\Columns\TextColumn::make("login_method")
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        "google" => "danger",
                        "wa" => "success",
                        "facebook" => "info",
                        "instagram" => "warning",
                        "email" => "gray",
                        "room" => "primary",
                        default => "gray",
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        "google" => "Google",
                        "wa" => "WhatsApp",
                        "facebook" => "Facebook",
                        "instagram" => "Instagram",
                        "email" => "Email",
                        "room" => "Nomor Kamar",
                        default => ucfirst($state),
                    }),
                Tables\Columns\TextColumn::make("mac_address")
                    ->label("MAC")
                    ->copyable()
                    ->fontFamily("mono")
                    ->color("gray")
                    ->size("text-sm"),
                Tables\Columns\TextColumn::make("ip_address")
                    ->label("IP")
                    ->fontFamily("mono")
                    ->size("text-sm"),
                Tables\Columns\TextColumn::make("router.name")
                    ->label("Router")
                    ->default(fn ($record) => $record->router?->name ?? '-')
                    ->size("text-sm"),
                Tables\Columns\TextColumn::make("status")
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        "active" => "success",
                        "disconnected" => "warning",
                        "expired" => "danger",
                        default => "gray",
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        "active" => "Online",
                        "disconnected" => "Grace",
                        "expired" => "Expired",
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make("login_at")
                    ->label("Login")
                    ->dateTime("d M H:i"),
                Tables\Columns\TextColumn::make("duration")
                    ->label("Durasi")
                    ->state(fn (UserSession $record): string => $record->login_at
                        ? $record->login_at->diffForHumans(now(), true)
                        : '-'
                    ),
                Tables\Columns\TextColumn::make("expires_at")
                    ->label("Sisa Waktu")
                    ->state(fn (UserSession $record): string => match ($record->status) {
                        'active' => $record->expires_at
                            ? $record->expires_at->diffForHumans(now(), true)
                            : '-',
                        'disconnected' => $record->seconds_remaining > 0
                            ? $record->seconds_remaining . 's grace'
                            : 'expired',
                        default => '-',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make("status")
                    ->options(["active" => "Online", "disconnected" => "Grace", "expired" => "Expired"]),
                Tables\Filters\SelectFilter::make("login_method")
                    ->options(["google" => "Google", "wa" => "WhatsApp", "facebook" => "Facebook", "instagram" => "Instagram", "email" => "Email", "room" => "Nomor Kamar"]),
            ])
            ->defaultSort("login_at", "desc")
            ->paginated([15, 25, 50]);
    }

    public static function getPages(): array
    {
        return ["index" => Pages\ListVisitorSessions::route("/")];
    }
}