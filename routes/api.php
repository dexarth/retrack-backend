<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WeatherController;
use App\Http\Controllers\UserController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

//for testing purpose
Route::middleware('auth:sanctum')->get('/weather/{city}', [WeatherController::class, 'getWeatherByCity']);

//get user role
Route::middleware('auth:sanctum')->get('/me', [UserController::class, 'me']);

//login
Route::post('/login', [UserController::class, 'login']);