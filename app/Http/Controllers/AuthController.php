<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Router;
use App\Models\User;
use App\Services\AnalyticsEngine;
use App\Services\GracePeriodEngine;
use App\Services\MikroTikRadiusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function __construct(
        private GracePeriodEngine $graceEngine,
        private AnalyticsEngine $analytics,
        private MikroTikRadiusService $radius
    ) {}

    public function googleRedirect(Request $request)
    {
        $request->session()->put('nas_id', $request->query('nas_id'));
        $request->session()->put('client_mac', $request->query('client_mac'));
        $request->session()->put('login_method', 'google');

        return Socialite::driver('google')->redirect();
    }

    public function googleCallback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            $user = User::updateOrCreate(
                [
                    'identity_value' => $googleUser->getEmail(),
                    'identity_type' => 'google',
                ],
                [
                    'name' => $googleUser->getName(),
                    'avatar' => $googleUser->getAvatar(),
                ]
            );

            return $this->processLoginRedirect($request, $user, 'google');
        } catch (\Exception $e) {
            Log::error('Google OAuth failed', ['error' => $e->getMessage()]);

            return redirect('/portal?'.http_build_query([
                'nas_id' => $request->session()->get('nas_id'),
                'client_mac' => $request->session()->get('client_mac'),
                'error' => 'Authentication failed',
            ]));
        }
    }

    public function waRequest(Request $request)
    {
        $request->validate([
            'phone' => 'required|digits_between:10,15',
            'nas_id' => 'required',
            'client_mac' => 'required',
        ]);

        $phone = $request->input('phone');
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $request->session()->put('wa_phone', $phone);
        $request->session()->put('wa_otp', $otp);
        $request->session()->put('wa_otp_expires', now()->addMinutes(5)->timestamp);
        $request->session()->put('nas_id', $request->input('nas_id'));
        $request->session()->put('client_mac', $request->input('client_mac'));

        Log::info('WA OTP for development', ['phone' => $phone, 'otp' => $otp]);

        return response()->json([
            'success' => true,
            'message' => 'OTP sent to WhatsApp',
            'dev_mode' => true,
            'otp' => $otp,
        ]);
    }

    public function waVerify(Request $request): JsonResponse
    {
        $request->validate([
            'otp' => 'required|digits:6',
            'nas_id' => 'required',
            'client_mac' => 'required',
        ]);

        $storedOtp = $request->session()->get('wa_otp');
        $expires = $request->session()->get('wa_otp_expires');

        if (! $storedOtp || ! $expires || now()->timestamp > $expires) {
            return response()->json([
                'success' => false,
                'message' => 'OTP expired',
            ], 400);
        }

        if ($request->input('otp') !== $storedOtp) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP',
            ], 400);
        }

        $phone = $request->session()->get('wa_phone');
        $user = User::updateOrCreate(
            [
                'identity_value' => $phone,
                'identity_type' => 'wa',
            ],
            [
                'name' => 'User '.substr($phone, -4),
            ]
        );

        $request->session()->forget(['wa_phone', 'wa_otp', 'wa_otp_expires']);

        return $this->processLoginJson($request, $user, 'wa');
    }

    public function room(Request $request): JsonResponse
    {
        $request->validate([
            'room_number' => 'required',
            'nas_id' => 'required',
            'client_mac' => 'required',
        ]);

        $roomNumber = $request->input('room_number');

        // Get router config for validation
        $router = Router::where('nas_identifier', $request->input('nas_id'))->first();
        if ($router) {
            $config = $router->tenant->portalConfig;
            if (! $this->validateRoomNumber($roomNumber, $config)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nomor kamar tidak valid',
                ], 400);
            }
        }

        $request->session()->put('nas_id', $request->input('nas_id'));
        $request->session()->put('client_mac', $request->input('client_mac'));

        $user = User::updateOrCreate(
            [
                'identity_value' => $roomNumber,
                'identity_type' => 'room',
            ],
            [
                'name' => 'Room '.$roomNumber,
            ]
        );

        // Insert user to RADIUS radcheck table for authentication
        \DB::table('radcheck')->updateOrInsert(
            ['username' => $roomNumber],
            [
                'attribute' => 'Cleartext-Password',
                'op' => ':=',
                'value' => $roomNumber,
            ]
        );

        return $this->processLoginJson($request, $user, 'room');
    }

    private function processLogin(Request $request, User $user, string $method): array
    {
        $nasId = $request->session()->get('nas_id') ?? $request->query('nas_id');
        $mac = $request->session()->get('client_mac') ?? $request->query('client_mac');

        $router = Router::where('nas_identifier', $nasId)->firstOrFail();
        $config = $router->tenant->portalConfig;

        $fingerprintHash = $request->header('X-Fingerprint');

        $device = Device::firstOrCreate(
            ['fingerprint_hash' => $fingerprintHash],
            ['user_id' => $user->id]
        );

        if ($device->user_id !== $user->id) {
            $device->update(['user_id' => $user->id]);
        }

        $session = $this->graceEngine->createSession($request, $user, $device, $router);

        $this->analytics->track('login_success', [
            'tenant_id' => $router->tenant_id,
            'router_id' => $router->id,
            'user_id' => $user->id,
            'device_id' => $device->id,
            'mac' => $mac,
            'ip' => $request->ip(),
            'login_method' => $method,
        ]);

        $this->analytics->upsertVisitorProfile($router->tenant_id, $user->id, $method);

        $coaHost = $router->ip_address;
        if ($coaHost) {
            $this->radius->setCoaHost($coaHost);
            $coaResult = $this->radius->acceptUser(
                mac: $mac,
                nasId: $router->nas_identifier,
                sessionTimeout: $config->grace_period_seconds
            );
            Log::info('CoA sent', [
                'host' => $coaHost,
                'mac' => $mac,
                'nasId' => $router->nas_identifier,
                'result' => $coaResult,
            ]);
        } else {
            Log::warning('Router has no IP address, skipping CoA', [
                'router_id' => $router->id,
                'nas_identifier' => $router->nas_identifier,
            ]);
        }

        // #region agent log H4/H2: login success redirect + cookie attributes
        $debugLogPath = base_path('.cursor/debug-4dc385.log');
        $debugPayload = [
            'sessionId' => '4dc385',
            'runId' => 'debug_initial',
            'hypothesisId' => 'H4',
            'location' => 'AuthController.php:processLogin',
            'message' => 'processLogin redirecting to fixed external URL',
            'data' => [
                'target_url' => 'https://www.google.com',
                'login_method' => $method,
                'nas_id' => $nasId,
                'client_mac' => $mac,
                'luma_session_cookie_minutes' => (int) ($config->grace_period_seconds / 60),
                'request_secure' => $request->secure(),
                'has_X-Fingerprint' => $request->header('X-Fingerprint') !== null,
            ],
            'timestamp' => (int) round(microtime(true) * 1000),
        ];
        file_put_contents($debugLogPath, json_encode($debugPayload)."\n", FILE_APPEND);
        // #endregion

        return [
            'redirect' => 'https://www.google.com',
            'cookie' => cookie(
                'luma_session',
                $session->cookie_token,
                (int) ($config->grace_period_seconds / 60),
                '/',
                null,
                true,
                true,
                false,
                'Lax'
            ),
        ];
    }

    private function processLoginRedirect(Request $request, User $user, string $method): RedirectResponse
    {
        $result = $this->processLogin($request, $user, $method);

        return redirect($result['redirect'])->withCookie($result['cookie']);
    }

    private function processLoginJson(Request $request, User $user, string $method): JsonResponse
    {
        $result = $this->processLogin($request, $user, $method);

        return response()->json([
            'success' => true,
            'redirect' => $result['redirect'],
        ])->withCookie($result['cookie']);
    }

    private function validateRoomNumber($roomNumber, $config): bool
    {
        if (! ($config->room_validation_enabled ?? false)) {
            return true;
        }

        $validationConfig = $config->room_validation_config ?? [];
        if (is_string($validationConfig)) {
            $validationConfig = json_decode($validationConfig, true) ?: [];
        }

        $mode = $config->room_validation_mode ?? 'range';

        switch ($mode) {
            case 'range':
                return $this->validateRoomRange($roomNumber, $validationConfig);
            case 'list':
                return $this->validateRoomList($roomNumber, $validationConfig);
            case 'pattern':
                return $this->validateRoomPattern($roomNumber, $validationConfig);
            default:
                return true;
        }
    }

    private function validateRoomRange($roomNumber, $ranges): bool
    {
        if (preg_match('/(\d+)/', $roomNumber, $matches)) {
            $roomNum = intval($matches[1]);
            foreach ($ranges as $range) {
                $from = $range['from'] ?? null;
                $to = $range['to'] ?? null;
                if ($from !== null && $to !== null) {
                    if ($roomNum >= $from && $roomNum <= $to) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function validateRoomList($roomNumber, $list): bool
    {
        $roomLower = strtolower(trim((string) $roomNumber));
        foreach ($list as $validRoom) {
            if (is_array($validRoom)) {
                $from = $validRoom['from'] ?? null;
                $to = $validRoom['to'] ?? null;
                if ($from !== null && $to !== null && is_numeric($roomNumber)) {
                    if ((int) $roomNumber >= (int) $from && (int) $roomNumber <= (int) $to) {
                        return true;
                    }
                }
                continue;
            }
            if (strtolower(trim((string) $validRoom)) === $roomLower) {
                return true;
            }
        }

        return false;
    }

    private function validateRoomPattern($roomNumber, $config): bool
    {
        $pattern = $config['pattern'] ?? '';
        if (empty($pattern)) {
            return true;
        }

        return preg_match('/'.$pattern.'/', $roomNumber) === 1;
    }
}
