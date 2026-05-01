<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceFingerprint extends Model
{
    protected $fillable = [
        'user_id',
        'device_id',
        'fingerprint_hash',
        'visitor_id',
        'canvas_hash',
        'webgl_hash',
        'webgl_vendor',
        'webgl_renderer',
        'fonts_hash',
        'audio_hash',
        'screen_resolution',
        'color_depth',
        'device_memory',
        'hardware_concurrency',
        'timezone',
        'languages',
        'touch_support',
        'platform',
        'os_name',
        'os_version',
        'browser_name',
        'browser_version',
        'user_agent',
        'ip_address',
        'nas_id',
        'mac',
        'trust_score',
        'confidence',
        'is_known_device',
        'risk_factors',
        'match_count',
    ];

    protected $casts = [
        'touch_support' => 'boolean',
        'is_known_device' => 'boolean',
        'risk_factors' => 'array',
        'trust_score' => 'integer',
        'match_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public static function findOrCreateFromAnalysis(array $data, array $analysisResult): self
    {
        $hash = $analysisResult['fingerprint_hash'];

        $existing = static::where('fingerprint_hash', $hash)->first();

        if ($existing) {
            $existing->update([
                'trust_score' => $analysisResult['trust_score'] ?? 0,
                'confidence' => $analysisResult['confidence'] ?? 'low',
                'is_known_device' => $analysisResult['is_known_device'] ?? false,
                'risk_factors' => $analysisResult['risk_factors'] ?? [],
                'match_count' => $existing->match_count + 1,
            ]);

            return $existing;
        }

        return static::create([
            'fingerprint_hash' => $hash,
            'user_id' => $data['user_id'] ?? null,
            'device_id' => $data['device_id'] ?? null,
            'visitor_id' => $data['visitor_id'] ?? null,
            'canvas_hash' => $data['canvas_hash'] ?? null,
            'webgl_hash' => $data['webgl_hash'] ?? null,
            'webgl_vendor' => $data['webgl_vendor'] ?? null,
            'webgl_renderer' => $data['webgl_renderer'] ?? null,
            'fonts_hash' => $data['fonts_hash'] ?? null,
            'audio_hash' => $data['audio_hash'] ?? null,
            'screen_resolution' => $data['screen_resolution'] ?? null,
            'color_depth' => $data['color_depth'] ?? null,
            'device_memory' => $data['device_memory'] ?? null,
            'hardware_concurrency' => $data['hardware_concurrency'] ?? null,
            'timezone' => $data['timezone'] ?? null,
            'languages' => $data['languages'] ?? null,
            'touch_support' => $data['touch_support'] ?? null,
            'platform' => $data['platform'] ?? null,
            'os_name' => $data['os_name'] ?? null,
            'os_version' => $data['os_version'] ?? null,
            'browser_name' => $data['browser_name'] ?? null,
            'browser_version' => $data['browser_version'] ?? null,
            'user_agent' => $data['user_agent'] ?? null,
            'ip_address' => $data['ip'] ?? null,
            'nas_id' => $data['nas_id'] ?? null,
            'mac' => $data['mac'] ?? null,
            'trust_score' => $analysisResult['trust_score'] ?? 0,
            'confidence' => $analysisResult['confidence'] ?? 'low',
            'is_known_device' => $analysisResult['is_known_device'] ?? false,
            'risk_factors' => $analysisResult['risk_factors'] ?? [],
            'match_count' => 1,
        ]);
    }
}