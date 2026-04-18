<?php

namespace App\Http\Controllers;

use App\Models\UserSession;
use App\Services\GracePeriodEngine;
use Illuminate\Http\Request;

class RadiusAccountingController extends Controller
{
    public function __construct(
        private GracePeriodEngine $graceEngine
    ) {}

    public function handle(Request $request)
    {
        $statusType = $request->input('Acct-Status-Type');
        $mac = strtoupper(str_replace([':', '-'], '', $request->input('Calling-Station-Id')));
        $nasId = $request->input('NAS-Identifier');
        $sessionId = $request->input('Acct-Session-Id');
        $inputOctets = $request->input('Acct-Input-Octets', 0);
        $outputOctets = $request->input('Acct-Output-Octets', 0);

        switch ($statusType) {
            case 'Start':
                $this->handleStart($mac, $nasId, $sessionId);
                break;

            case 'Interim-Update':
                $this->handleInterimUpdate($mac, $nasId, $sessionId, $inputOctets, $outputOctets);
                break;

            case 'Stop':
                $this->handleStop($mac, $nasId, $sessionId, $inputOctets, $outputOctets);
                break;
        }

        return response('OK', 200);
    }

    private function handleStart(string $mac, string $nasId, string $sessionId): void
    {
        $session = UserSession::where('mac_address', $mac)
            ->where('nas_id', $nasId)
            ->where('status', 'active')
            ->first();

        if ($session) {
            $session->update(['last_seen_at' => now()]);
        }
    }

    private function handleInterimUpdate(
        string $mac,
        string $nasId,
        string $sessionId,
        int $inputOctets,
        int $outputOctets
    ): void {
        $session = UserSession::where('mac_address', $mac)
            ->where('nas_id', $nasId)
            ->where('status', 'active')
            ->first();

        if ($session) {
            $session->update([
                'last_seen_at' => now(),
                'meta' => array_merge($session->meta ?? [], [
                    'input_octets' => $inputOctets,
                    'output_octets' => $outputOctets,
                ]),
            ]);
        }
    }

    private function handleStop(
        string $mac,
        string $nasId,
        string $sessionId,
        int $inputOctets,
        int $outputOctets
    ): void {
        $session = UserSession::where('mac_address', $mac)
            ->where('nas_id', $nasId)
            ->where('status', 'active')
            ->first();

        if ($session) {
            $session->update([
                'disconnected_at' => now(),
                'last_seen_at' => now(),
                'status' => 'disconnected',
                'meta' => array_merge($session->meta ?? [], [
                    'input_octets' => $inputOctets,
                    'output_octets' => $outputOctets,
                ]),
            ]);

            $this->graceEngine->onDisconnect($mac, $nasId);
        }
    }
}
