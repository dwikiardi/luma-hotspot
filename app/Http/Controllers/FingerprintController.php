<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class FingerprintController extends Controller
{
    public function analyze(Request $request)
    {
        $validated = $request->validate([
            'visitor_id' => 'nullable|string',
            'canvas_hash' => 'nullable|string',
            'webgl_hash' => 'nullable|string',
            'webgl_vendor' => 'nullable|string',
            'webgl_renderer' => 'nullable|string',
            'fonts_hash' => 'nullable|string',
            'audio_hash' => 'nullable|string',
            'screen_resolution' => 'nullable|string',
            'color_depth' => 'nullable|integer',
            'device_memory' => 'nullable|integer',
            'hardware_concurrency' => 'nullable|integer',
            'timezone' => 'nullable|string',
            'languages' => 'nullable|string',
            'touch_support' => 'nullable|boolean',
            'platform' => 'nullable|string',
            'os_name' => 'nullable|string',
            'os_version' => 'nullable|string',
            'browser_name' => 'nullable|string',
            'browser_version' => 'nullable|string',
            'user_agent' => 'required|string',
            'nas_id' => 'required|string',
            'mac' => 'nullable|string',
        ]);

        $validated['ip'] = $request->ip();

        try {
            $fastapiUrl = config('services.fastapi.url', 'http://fastapi:8001');
            $response = Http::timeout(10)->post($fastapiUrl.'/api/fingerprint', $validated);
            
            if ($response->successful()) {
                $data = $response->json();
                
                return response()->json([
                    'success' => true,
                    'fingerprint_hash' => $data['fingerprint_hash'],
                    'trust_score' => $data['trust_score'],
                    'confidence' => $data['confidence'],
                    'is_known_device' => $data['is_known_device'],
                    'risk_factors' => $data['risk_factors'],
                ]);
            }
            
            Log::error('FastAPI fingerprint failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            
            return response()->json([
                'success' => false,
                'trust_score' => 50,
                'confidence' => 'low',
                'error' => 'Scoring service unavailable',
            ], 500);
            
        } catch (\Exception $e) {
            Log::error('Fingerprint analysis exception', [
                'error' => $e->getMessage(),
            ]);
            
            $fallbackScore = $this->calculateFallbackScore($validated);
            $fingerprintHash = $this->calculateFallbackHash($validated);
            
            return response()->json([
                'success' => true,
                'fingerprint_hash' => $fingerprintHash,
                'trust_score' => $fallbackScore,
                'confidence' => 'medium',
                'is_known_device' => false,
                'risk_factors' => [],
                'fallback' => true,
            ]);
        }
    }

    private function calculateFallbackScore(array $data): int
    {
        $score = 50;
        
        if (!empty($data['canvas_hash'])) { $score += 10; }
        if (!empty($data['webgl_hash'])) { $score += 10; }
        if (!empty($data['fonts_hash'])) { $score += 5; }
        if (!empty($data['audio_hash'])) { $score += 5; }
        if (!empty($data['screen_resolution'])) { $score += 5; }
        if (!empty($data['timezone'])) { $score += 3; }
        if (!empty($data['platform'])) { $score += 5; }
        if (!empty($data['hardware_concurrency'])) { $score += 3; }
        
        $score = min(100, $score);
        
        return max(0, $score);
    }

    private function calculateFallbackHash(array $data): string
    {
        $components = [
            $data['visitor_id'] ?? '',
            $data['canvas_hash'] ?? '',
            $data['webgl_hash'] ?? '',
            $data['screen_resolution'] ?? '',
            $data['timezone'] ?? '',
            $data['platform'] ?? '',
            $data['user_agent'] ?? '',
            $data['ip'] ?? '',
            $data['nas_id'] ?? '',
        ];
        
        return hash('sha256', implode('|', $components));
    }
}
