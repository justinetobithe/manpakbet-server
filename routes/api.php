<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

Route::get('/me', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::post('/auth/otp/request', [AuthController::class, 'requestOtp']);
Route::post('/auth/otp/verify', [AuthController::class, 'verifyOtp']);

Route::get('/auth/google/redirect', [AuthController::class, 'googleRedirect']);
Route::get('/auth/google/callback', [AuthController::class, 'googleCallback']);

Route::get('/auth/facebook/redirect', [AuthController::class, 'facebookRedirect']);
Route::get('/auth/facebook/callback', [AuthController::class, 'facebookCallback']);
