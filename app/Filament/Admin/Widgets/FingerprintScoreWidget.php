<?php

namespace App\Filament\Admin\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

class FingerprintScoreWidget extends Widget
{
    protected static string $view = 'filament.admin.widgets.fingerprint-score';

    protected static ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return [
            'prints' => $this->getFingerprints(),
            'stats' => $this->getStats(),
        ];
    }

    public function getFingerprints()
    {
        return DB::table('device_fingerprints')
            ->select([
                'id',
                'fingerprint_hash',
                'visitor_id',
                'trust_score',
                'confidence',
                'is_known_device',
                'match_count',
                'platform',
                'browser_name',
                'ip_address',
                'nas_id',
                'created_at',
                'updated_at',
            ])
            ->orderByDesc('updated_at')
            ->limit(15)
            ->get()
            ->map(function ($row) {
                $row->hash_short = substr($row->fingerprint_hash, 0, 12) . '...';
                $row->confidence_color = match ($row->confidence) {
                    'high' => 'green',
                    'medium' => 'yellow',
                    default => 'red',
                };
                return $row;
            });
    }

    public function getStats()
    {
        $total = DB::table('device_fingerprints')->count();
        $known = DB::table('device_fingerprints')->where('is_known_device', true)->count();
        $avgScore = DB::table('device_fingerprints')->avg('trust_score') ?? 0;
        $highConfidence = DB::table('device_fingerprints')->where('confidence', 'high')->count();

        return (object) [
            'total' => $total,
            'known' => $known,
            'avg_score' => round($avgScore, 1),
            'high_confidence' => $highConfidence,
        ];
    }
}