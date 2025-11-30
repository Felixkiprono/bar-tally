<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use Illuminate\Http\Request;

// Public routes
Route::get('/', function () {
    return view('welcome');
});

// Route::get('/test-policy', function () {
//     $user = auth()->user();
//     $policyClass = Gate::getPolicyFor(\App\Models\User::class);

//     return [
//         'user_role' => $user->role,
//         'policy_class' => $policyClass ? get_class($policyClass) : 'No policy found',
//         'can_view_any' => Gate::allows('viewAny', \App\Models\User::class),
//         'policy_for_user' => Gate::getPolicyFor(\App\Models\User::class) ? 'exists' : 'missing',
//     ];
// });

// // Debugging route to test tenant middleware
// Route::get('/test-tenant', function (Request $request) {
//     return response()->json([
//         'has_tenant' => app()->bound('currentTenant'),
//         'tenant_info' => app()->bound('currentTenant') ? [
//             'id' => app('currentTenant')->id,
//             'name' => app('currentTenant')->name,
//             'domain' => app('currentTenant')->domain,
//         ] : null,
//         'debug_info' => $request->attributes->get('tenant_debug_info', []),
//         'app_environment' => app()->environment(),
//         'server_info' => [
//             'hostname' => gethostname(),
//             'server_name' => $_SERVER['SERVER_NAME'] ?? null,
//             'http_host' => $_SERVER['HTTP_HOST'] ?? null,
//         ],
//     ]);
// });

// // Test route with middleware explicitly applied
// Route::get('/test-tenant-explicit', function (Request $request) {
//     return response()->json([
//         'has_tenant' => app()->bound('currentTenant'),
//         'tenant_info' => app()->bound('currentTenant') ? [
//             'id' => app('currentTenant')->id,
//             'name' => app('currentTenant')->name,
//             'domain' => app('currentTenant')->domain,
//         ] : null,
//         'debug_info' => $request->attributes->get('tenant_debug_info', []),
//     ]);
// })->middleware(\App\Http\Middleware\SetCurrentTenantMiddleware::class);

// Filament handles its own routes and authentication
