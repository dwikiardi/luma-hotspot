<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitorProfile extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'total_visits',
        'total_sessions',
        'first_visit_at',
        'last_visit_at',
        'avg_session_minutes',
        'preferred_login_method',
        'visitor_type',
    ];

    protected $casts = [
        'total_visits' => 'integer',
        'total_sessions' => 'integer',
        'first_visit_at' => 'datetime',
        'last_visit_at' => 'datetime',
        'avg_session_minutes' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
