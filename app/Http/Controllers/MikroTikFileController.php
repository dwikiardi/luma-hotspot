<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use ZipArchive;

class MikroTikFileController extends Controller
{
    public function downloadHotspotFiles(Request $request)
    {
        $nasId = $request->get('nas_id');
        
        if (!$nasId) {
            return response()->json(['error' => 'NAS ID is required'], 400);
        }
        
        $serverUrl = Config::get('app.server_url', 'http://103.137.140.6:8081');
        $portalUrl = $serverUrl.'/portal';

        $loginHtml = $this->generateLoginHtml($portalUrl, $nasId);
        $redirectHtml = $this->generateRedirectHtml($serverUrl, $nasId);
        $logoutHtml = $this->generateLogoutHtml();
        $statusHtml = $this->generateStatusHtml();
        $readmeContent = $this->generateReadme($serverUrl, $nasId);

        $tempDir = storage_path('app/temp_hotspot_'.time());
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        file_put_contents($tempDir.'/login.html', $loginHtml);
        file_put_contents($tempDir.'/redirect.html', $redirectHtml);
        file_put_contents($tempDir.'/logout.html', $logoutHtml);
        file_put_contents($tempDir.'/status.html', $statusHtml);
        file_put_contents($tempDir.'/README.txt', $readmeContent);

        $zipPath = storage_path('app/hotspot_files_'.$nasId.'_'.date('YmdHis').'.zip');

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return response()->json(['error' => 'Cannot create ZIP file'], 500);
        }

        $zip->addFile($tempDir.'/login.html', 'hotspot/login.html');
        $zip->addFile($tempDir.'/redirect.html', 'hotspot/redirect.html');
        $zip->addFile($tempDir.'/logout.html', 'hotspot/logout.html');
        $zip->addFile($tempDir.'/status.html', 'hotspot/status.html');
        $zip->addFile($tempDir.'/README.txt', 'README.txt');

        $zip->close();

        array_map('unlink', glob($tempDir.'/*'));
        rmdir($tempDir);

        return response()->download($zipPath, 'mikrotik_hotspot_files_'.$nasId.'.zip')->deleteFileAfterSend(true);
    }

    private function generateLoginHtml($portalUrl, $nasId)
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WiFi Login - Luma Network</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 400px;
            width: 90%;
            text-align: center;
        }
        .logo {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 20px;
        }
        h1 { color: #333; margin-bottom: 10px; font-size: 24px; }
        p { color: #666; margin-bottom: 20px; }
        .loader {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">WiFi</div>
        <h1>Connecting to WiFi</h1>
        <p>Please wait while we redirect you to the login portal...</p>
        <div class="loader"></div>
        <p style="font-size: 12px; color: #999;">$(if error)
        Error: $(error)
        $(endif)</p>
    </div>
    <script>
        setTimeout(function() {
            window.location.href = "'.$portalUrl.'?nas_id='.$nasId.'&redirect=$(link-orig)&mac=$(mac)&ip=$(ip)&user=$(username)";
        }, 500);
    </script>
</body>
</html>';
    }

    private function generateRedirectHtml($serverUrl, $nasId)
    {
        $portalUrl = $serverUrl.'/portal?nas_id='.$nasId;

        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redirecting...</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }
        .container { text-align: center; }
        .loader {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="container">
        <h2>Redirecting to WiFi Portal</h2>
        <div class="loader"></div>
        <p>If you are not redirected, <a href="'.$portalUrl.'">click here</a></p>
    </div>
    <script>
        window.location.href = "'.$portalUrl.'";
    </script>
</body>
</html>';
    }

    private function generateLogoutHtml()
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Logged Out</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 16px;
            text-align: center;
            max-width: 400px;
        }
        h1 { color: #333; margin-bottom: 16px; }
        p { color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <h1>You have been logged out</h1>
        <p>Thank you for using our WiFi service.</p>
        <p style="margin-top: 20px;">$(if username)Username: $(username)$(endif)</p>
    </div>
    <script>
        setTimeout(function() {
            window.location.reload();
        }, 3000);
    </script>
</body>
</html>';
    }

    private function generateStatusHtml()
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Connection Status</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { color: #333; margin-bottom: 20px; }
        .info { margin: 10px 0; }
        .label { color: #666; }
        .value { font-weight: bold; }
        .status-connected { color: #10b981; }
        .status-disconnected { color: #ef4444; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Connection Status</h1>
        <div class="info"><span class="label">Status:</span> <span class="value status-connected">Connected</span></div>
        <div class="info"><span class="label">Username:</span> <span class="value">$(username)</span></div>
        <div class="info"><span class="label">IP Address:</span> <span class="value">$(ip)</span></div>
        <div class="info"><span class="label">MAC Address:</span> <span class="value">$(mac)</span></div>
        <div class="info"><span class="label">Session Time:</span> <span class="value">$(uptime)</span></div>
        <div class="info"><span class="label">Bytes In/Out:</span> <span class="value">$(bytes-in) / $(bytes-out)</span></div>
    </div>
</body>
</html>';
    }

    private function generateReadme($serverUrl, $nasId)
    {
        return 'MIKROTIK HOTSPOT FILES - LUMA NETWORK
==========================================
Router NAS ID: '.$nasId.'

INSTALLATION INSTRUCTIONS:
--------------------------

1. Open WinBox and connect to your MikroTik router

2. Go to Files

3. Navigate to the "hotspot" folder (create if it does not exist)

4. Upload the following files:
   - login.html
   - redirect.html
   - logout.html
   - status.html

5. IMPORTANT: Verify the login.html contains your NAS ID:
   - nas_id='.$nasId.'

6. Restart hotspot service:
   /ip hotspot disable [find]
   /ip hotspot enable [find]

7. Test by connecting a device to the hotspot

PORTAL URL: '.$serverUrl.'/portal?nas_id='.$nasId.'

TROUBLESHOOTING:
----------------

If users see "Akses Tidak Valid":
   - Check that login.html contains the correct nas_id
   - Verify the nas_id matches your router configuration
   - Check that the router is registered in Luma Network

If redirect does not work:
   - Check DNS resolution
   - Verify portal URL is accessible
   - Check firewall rules

Generated: '.date('Y-m-d H:i:s').'
';
    }
}
