<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;
use App\Http\Controllers\ProfileTokenController;
use App\Http\Controllers\UserController;

Route::get('/', function () {
    return view('welcome');
});

// Define the Swagger docs routes with proper naming convention
Route::get('/api/docs/{jsonFile?}', function ($jsonFile = 'api-docs.json') {
    $path = storage_path("api-docs/{$jsonFile}");
    if (!File::exists($path)) {
        abort(404, 'API documentation not found.');
    }

    return Response::file($path, [
        'Content-Type' => 'application/json',
    ]);
})->name('l5-swagger.default.docs');

// Define OAuth2 callback route for Swagger
Route::get('/api/oauth2-callback', function () {
    return '';
})->name('l5-swagger.default.oauth2_callback');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::post('/generate-token', [ProfileTokenController::class, 'generate'])->name('token.generate');
    
    Route::middleware(['auth'])->get('/api/documentation', function () {
        $documentation = Config::get('l5-swagger.default');
        $documentationTitle = Config::get("l5-swagger.documentations.{$documentation}.api.title");
        $useAbsolutePath = Config::get('l5-swagger.paths.use_absolute_path', true);
        
        // Correct way to build URLs to docs for different documentations
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
});

Route::get('/logout', [UserController::class, 'logout']);