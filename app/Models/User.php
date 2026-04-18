<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Model
{
    protected $fillable = [
        'identity_value',
        'identity_type',
        'name',
        'avatar',
    ];

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }

    public function visitorProfiles(): HasMany
    {
        return $this->hasMany(VisitorProfile::class);
    }
}
