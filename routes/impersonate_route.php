<?php
// This file updates the impersonate route in web.php

$newRoute = <<'ROUTE'
// Impersonation route - login as tenant user
Route::get('/impersonate/{userId}', function (int $userId) {
    $user = \App\Models\TenantUser::with('tenant')->findOrFail($userId);
    
    // Logout any existing session
    \Illuminate\Support\Facades\Auth::guard('tenant_users')->logout();
    
    // Login as the impersonated user
    \Illuminate\Support\Facades\Auth::guard('tenant_users')->login($user);
    
    // Regenerate session to prevent fixation
    session()->regenerate();
    
    // Redirect to tenant dashboard
    $slug = $user->tenant?->slug ?? 'unknown';
    
    return redirect("/dashboard/venue/{$slug}");
})->name('impersonate');
ROUTE;

$content = file_get_contents('/var/www/html/routes/web.php');
if (strpos($content, "Route::get('/impersonate/{userId}'") !== false) {
    $pattern = "/Route::get\('\\\\/impersonate\\\\/{userId}'.*?->name\('impersonate'\);/s";
    $content = preg_replace($pattern, $newRoute, $content);
} else {
    $content .= "\n" . $newRoute;
}
file_put_contents('/var/www/html/routes/web.php', $content);
echo 'Done';
