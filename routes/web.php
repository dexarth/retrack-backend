<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;
use App\Http\Controllers\ProfileTokenController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Artisan;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/clear-cache', function () {
    Artisan::call('cache:clear');      // Clear application cache
    Artisan::call('config:clear');     // Clear config cache
    Artisan::call('config:cache');     // Rebuild config cache
    Artisan::call('route:clear');      // Clear route cache
    Artisan::call('view:clear');       // Clear compiled views
    Artisan::call('event:clear');      // Clear event cache
    Artisan::call('optimize:clear');   // Clear all optimizations
    
    return "All caches cleared successfully!";
});

// Swagger Documentation JSON Route
Route::get('/api/docs/{jsonFile?}', function ($jsonFile = 'api-docs.json') {
    $path = storage_path("api-docs/{$jsonFile}");

    if (!File::exists($path)) {
        abort(404, 'API documentation not found.');
    }

    return Response::file($path, [
        'Content-Type' => 'application/json',
    ]);
})->name('l5-swagger.default.docs');

// Swagger OAuth2 Callback Route
Route::get('/api/oauth2-callback', fn() => '')->name('l5-swagger.default.oauth2_callback');

// Swagger UI Route (Made public for login testing)
Route::get('/api/documentation', function () {
    $documentation = Config::get('l5-swagger.default');
    $documentationTitle = Config::get("l5-swagger.documentations.{$documentation}.api.title");
    $useAbsolutePath = Config::get('l5-swagger.paths.use_absolute_path', true);

    $urlsToDocs = [];
    foreach (Config::get('l5-swagger.documentations') as $name => $config) {
        $urlsToDocs[] = [
            'url' => route("l5-swagger.{$name}.docs", ['api-docs.json']),
            'name' => $config['api']['title'] ?? $name,
        ];
    }

    return view('vendor.l5-swagger.index', compact(
        'documentation',
        'documentationTitle',
        'urlsToDocs',
        'useAbsolutePath'
    ));
})->name('l5-swagger.documentation');

// Authenticated Routes (Sanctum + Jetstream + Email Verified)
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', fn() => view('dashboard'))->name('dashboard');
    Route::post('/generate-token', [ProfileTokenController::class, 'generate'])->name('token.generate');
});

// Logout
// Route::get('/logout', [UserController::class, 'logout']);