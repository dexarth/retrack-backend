<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WeatherController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\ImageController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

//HOW TO MAKE NEWLY CREATED API AVAILABLE IN SWAGGER:-
//CLI Command: php artisan l5-swagger:generate

//login
Route::post('/login', [UserController::class, 'login']);

//logout
Route::middleware('auth:sanctum')->post('/logout', [UserController::class, 'logout']);

//for testing purpose
Route::middleware('auth:sanctum')->get('/weather/{city}', [WeatherController::class, 'getWeatherByCity']);

//get user role
Route::middleware('auth:sanctum')->get('/me', [UserController::class, 'me']);

//get listing table
Route::middleware('auth:sanctum')->get('/listing/{table}', [ListingController::class, 'getListing']);

//get listing table with filter
Route::middleware('auth:sanctum')->get('/listing-filter/{table}', [ListingController::class, 'getListingWithFilter']);

//get listing table with join filter
Route::middleware('auth:sanctum')->get('/listing-join-filter/{table}', [ListingController::class, 'getListingJoinFilter']);

//create new records
Route::middleware('auth:sanctum')->post('/form-submit/{formName}', [FormController::class, 'submitForm']);

//update records
Route::middleware('auth:sanctum')->put('/form-submit/{formName}/{id}', [FormController::class, 'updateForm']);

//get single listing data
Route::middleware('auth:sanctum')->get('/form-show/{formName}/{id}', [ListingController::class, 'getSingleRecord']);

//get authenticated user data
Route::middleware('auth:sanctum')->get('/form-show/auth-user', [UserController::class, 'show']);

//get users notification
Route::middleware('auth:sanctum')->get('/notifications', function () {
    return auth()->user()->notifications()->latest()->take(10)->get();}
);



