<?php

namespace App\Models;

use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model implements HasName
{
    protected $fillable = ['name', 'slug', 'venue_type', 'is_active'];

    public function getFilamentName(): string
    {
        return $this->name;
    }

    public function routers(): HasMany
    {
        return $this->hasMany(Router::class);
    }

    public function portalConfig()
    {
        return $this->hasOne(PortalConfig::class);
    }

    public function walledGardens(): HasMany
    {
        return $this->hasMany(WalledGarden::class);
    }

    public function analyticsEvents(): HasMany
    {
        return $this->hasMany(AnalyticsEvent::class);
    }

    public function visitorProfiles(): HasMany
    {
        return $this->hasMany(VisitorProfile::class);
    }
}
