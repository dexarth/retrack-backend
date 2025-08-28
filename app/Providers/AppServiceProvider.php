<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\LoginResponse;
use App\Actions\CustomLoginResponse;
use App\Actions\RegisterLoginResponse;
use App\Events\PusherTestEvent;
use Illuminate\Notifications\DatabaseNotification;
use Studio\Totem\Totem;
use Opcodes\LogViewer\Facades\LogViewer;
use Illuminate\Support\Facades\Auth;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
        // $this->app->singleton(LoginResponse::class, CustomLoginResponse::class);
        // $this->app->singleton(RegisterResponse::class, RegisterLoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        DatabaseNotification::created(function (DatabaseNotification $note) {
            $payload = $note->data;
            event(new PusherTestEvent($payload));
        });

        Totem::auth(function ($request) {
            return Auth::check() && (Auth::user()->role ?? null) === 'dev';
        });

        LogViewer::auth(function ($request) {
            return auth()->check() && (auth()->user()->role ?? null) === 'dev';
        });


    }
}
