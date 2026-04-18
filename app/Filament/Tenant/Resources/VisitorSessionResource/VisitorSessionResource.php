<?php

namespace App\Filament\Tenant\Resources\VisitorSessionResource;

use App\Models\Router;
use App\Models\UserSession;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VisitorSessionResource extends Resource
{
    protected static ?string $model = UserSession::class;
    protected static ?string $slug = "visitor-sessions";
    protected static ?string $navigationIcon = "heroicon-o-user-group";
    protected static ?string $navigationLabel = "Pengunjung Aktif";
    protected static ?string $navigationGroup = "Pengunjung";
    protected static ?int $navigationSort = 1;
    protected static ?string $tenantOwnershipRelationshipName = "router";

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        $tenantId = filament()->getTenant()?->id;

        return $table
            ->modifyQueryUsing(function ($query) use ($tenantId) {
                $routerIds = Router::where("tenant_id", $tenantId)->pluck("id");
                return $query->whereIn("router_id", $routerIds)->with(["user", "router"]);
            })
            ->columns([
                Tables\Columns\TextColumn::make("user.name")
                    ->label("Nama")
                    ->searchable(),
                Tables\Columns\TextColumn::make("user.identity_value")
                    ->label("Email / HP")
                    ->formatStateUsing(function ($state, UserSession $record): string {
                        if ($record->user) {
                            $type = $record->user->identity_type ?? "email";
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
