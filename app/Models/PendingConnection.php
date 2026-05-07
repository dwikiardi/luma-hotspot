<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PendingConnection extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'mac_address', 'ip_address', 'hostname', 'router_id', 'dhcp_server', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function router()
    {
        return $this->belongsTo(Router::class);
    }
}
