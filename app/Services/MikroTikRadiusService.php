<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class MikroTikRadiusService
{
    private string $secret;

    private int $coaPort = 3799;

    private string $coaHost;

    public function __construct()
    {
        $this->secret = env('RADIUS_SECRET', 'luma_radius_secret');
    }

    public function setCoaHost(string $host): self
    {
        $this->coaHost = $host;

        return $this;
    }

    public function acceptUser(string $mac, string $nasId, int $sessionTimeout): bool
    {
        try {
            $packet = $this->buildCoARequest($mac, $nasId, $sessionTimeout);
            $response = $this->sendUdpPacket($packet);

            return $this->validateCoAAck($response);
        } catch (\Exception $e) {
            Log::error('MikroTik CoA failed', [
                'mac' => $mac,
                'nasId' => $nasId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function rejectUser(string $mac, string $nasId): bool
    {
        try {
            $packet = $this->buildCoAReject($mac, $nasId);
            $response = $this->sendUdpPacket($packet);

            return $this->validateCoAAck($response);
        } catch (\Exception $e) {
            Log::error('MikroTik CoA reject failed', [
                'mac' => $mac,
                'nasId' => $nasId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function buildCoARequest(string $mac, string $nasId, int $sessionTimeout): string
    {
        $packet = $this->createRadiusPacket();
        $this->addAttribute($packet, 1, $mac);
        $this->addAttribute($packet, 4, pack('N', time()));
        $this->addAttribute($packet, 5, 1);
        $this->addAttribute($packet, 6, 2);
        $this->addAttribute($packet, 27, $sessionTimeout);
        $this->addAttribute($packet, 32, $nasId);

        return $this->signPacket($packet);
    }

    private function buildCoAReject(string $mac, string $nasId): string
    {
        $packet = $this->createRadiusPacket();
        $this->addAttribute($packet, 1, $mac);
        $this->addAttribute($packet, 4, pack('N', time()));
        $this->addAttribute($packet, 5, 1);
        $this->addAttribute($packet, 6, 3);
        $this->addAttribute($packet, 32, $nasId);

        return $this->signPacket($packet);
    }

    private function createRadiusPacket(): array
    {
        return [
            'code' => 43,
            'id' => random_int(1, 255),
            'length' => 20,
            'auth' => random_bytes(16),
            'attributes' => [],
        ];
    }

    private function addAttribute(array &$packet, int $type, mixed $value): void
    {
        if (is_string($value)) {
            $data = $value;
        } elseif (is_int($value)) {
            $data = pack('N', $value);
        } else {
            $data = $value;
        }

        $packet['attributes'][] = [
            'type' => $type,
            'length' => strlen($data) + 2,
            'value' => $data,
        ];
        $packet['length'] += strlen($data) + 2;
    }

    private function signPacket(array $packet): string
    {
        $message = chr($packet['code'])
            .chr($packet['id'])
            .pack('n', $packet['length'])
            .$packet['auth'];

        foreach ($packet['attributes'] as $attr) {
            $message .= chr($attr['type']).chr($attr['length']).$attr['value'];
        }

        $signature = md5($message.$this->secret);

        return chr($packet['code']).chr($packet['id'])
            .pack('n', $packet['length']).$packet['auth'].$signature.substr($message, 20);
    }

    private function sendUdpPacket(string $packet): string
    {
        if (empty($this->coaHost)) {
            throw new \Exception('CoA host not set');
        }

        $socket = @fsockopen('udp://'.$this->coaHost, $this->coaPort, $errno, $errstr, 5);
        if (! $socket) {
            throw new \Exception("UDP connection failed: $errstr ($errno)");
        }

        stream_set_timeout($socket, 5);
        fwrite($socket, $packet);

        $response = fread($socket, 1024);
        fclose($socket);

        return $response ?: '';
    }

    private function validateCoAAck(?string $response): bool
    {
        if (empty($response)) {
            return false;
        }

        if (strlen($response) < 4) {
            return false;
        }

        $code = ord($response[0]);

        return $code === 44;
    }
}
