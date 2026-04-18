<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class Router extends Model
{
    protected $fillable = [
        'tenant_id',
        'nas_identifier',
        'name',
        'location',
        'ip_address',
        'hotspot_address',
        'model',
        'firmware_version',
        'routeros_version',
        'is_active',
        'notes',
    ];

    protected static function booted(): void
    {
        static::created(function (Router $router) {
            DB::table('nas')->insert([
                'nasname' => $router->ip_address ?? '0.0.0.0',
                'shortname' => $router->nas_identifier,
                'type' => 'other',
                'secret' => Config::get('services.radius.secret', 'luma_radius_secret'),
                'description' => $router->name.' ('.$router->tenant->name.')',
            ]);
        });

        static::updated(function (Router $router) {
            DB::table('nas')
                ->where('shortname', $router->nas_identifier)
                ->update([
                    'nasname' => $router->ip_address ?? '0.0.0.0',
                    'description' => $router->name.' ('.$router->tenant->name.')',
                ]);
        });

        static::deleted(function (Router $router) {
            DB::table('nas')
                ->where('shortname', $router->nas_identifier)
                ->delete();
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function userSessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }
}
