<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GoogleController;
use App\Http\Controllers\BasicAuthController;
use App\Http\Controllers\Controller;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/


Route::get('/signin/google',[GoogleController::class,'redirectToGoogle']);
Route::get('/google/callback',[GoogleController::class,'handleGoogleCallback']);



Route::post('/register',[BasicAuthController::class,'register']);
Route::post('/verify-otp', [BasicAuthController::class, 'verifyOtp']);

Route::post('/housemaid/register-with-questions',[BasicAuthController::class,'verifyQuestions']);



Route::post('/login',[BasicAuthController::class,'login']);







// Protected routes (Sanctum Middleware)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/logout', [Controller::class, 'logout']);
});



