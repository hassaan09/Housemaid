<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GoogleController;
use App\Http\Controllers\BasicAuthController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\MediaController;
use Illuminate\Support\Facades\Storage;

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

// Auth APIs

Route::post('/register',[BasicAuthController::class,'register']);
Route::post('/verify-otp', [BasicAuthController::class, 'verifyOtp']);

Route::post('/login',[BasicAuthController::class,'login']);

Route::post('/forget-password',[BasicAuthController::class,'forgetPassword']);
Route::post('/change-password',[BasicAuthController::class,'changePassword']);

Route::get('/signin/google',[GoogleController::class,'redirectToGoogle']);
Route::get('/google/callback',[GoogleController::class,'handleGoogleCallback']);



Route::post('/housemaid/register-with-questions',[BasicAuthController::class,'verifyQuestions']);











// Protected routes (Sanctum Middleware)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/logout', [Controller::class, 'logout']);

});



