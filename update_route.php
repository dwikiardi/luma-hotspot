<?php

$content = file_get_contents('/var/www/html/routes/web.php');

$old = 'Route::get(\'/impersonate/{userId}\', function (int $userId) {
    $user = \App\Models\TenantUser::with(\'tenant\')->findOrFail($userId);
    
    // Clear all session data
    session()->flush();
    session()->save();
    
    // Login as the impersonated user
    \Illuminate\Support\Facades\Auth::guard(\'tenant_users\')->login($user);
    
    // Redirect to the tenant dashboard
    $slug = $user->tenant?->slug ?? \'unknown\';
    
    return redirect("/dashboard/venue/{$slug}");
})->name(\'impersonate\');';

$new = 'Route::get(\'/impersonate/{userId}\', function (int $userId) {
    $user = \App\Models\TenantUser::with(\'tenant\')->findOrFail($userId);
    session()->flush();
    \Illuminate\Support\Facades\Auth::guard(\'tenant_users\')->login($user);
    session()->regenerate();
    $slug = $user->tenant?->slug ?? \'unknown\';
    return redirect("/dashboard/venue/{$slug}");
})->name(\'impersonate\');';

$content = str_replace($old, $new, $content);
file_put_contents('/var/www/html/routes/web.php', $content);
echo "Done\n";
