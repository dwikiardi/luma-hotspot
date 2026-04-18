<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsDaily extends Model
{
    protected $table = 'analytics_daily';

    protected $fillable = [
        'tenant_id',
        'router_id',
        'date',
        'unique_visitors',
        'total_sessions',
        'new_visitors',
        'returning_visitors',
        'auto_reconnects',
        'forced_relogins',
        'reconnect_rate',
        'login_google',
        'login_wa',
        'login_room',
        'login_email',
        'avg_session_minutes',
        'peak_hour',
    ];

    protected $casts = [
        'date' => 'date',
        'unique_visitors' => 'integer',
        'total_sessions' => 'integer',
        'new_visitors' => 'integer',
        'returning_visitors' => 'integer',
        'auto_reconnects' => 'integer',
        'forced_relogins' => 'integer',
        'reconnect_rate' => 'decimal:2',
        'login_google' => 'integer',
        'login_wa' => 'integer',
        'login_room' => 'integer',
        'login_email' => 'integer',
        'avg_session_minutes' => 'integer',
        'peak_hour' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }
}
