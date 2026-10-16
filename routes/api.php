<?php

use App\Http\Controllers\Api\V1\Admin;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ContentController;
use App\Http\Controllers\Api\V1\GameCategoryController;
use App\Http\Controllers\Api\V1\GameController;
use App\Http\Controllers\Api\V1\GameProviderController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\WalletController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::middleware('throttle:10,1')->group(function () {
            Route::post('/register', [AuthController::class, 'register']);
            Route::post('/login', [AuthController::class, 'login']);
            Route::post('/otp/request', [AuthController::class, 'requestOtp']);
            Route::post('/otp/verify', [AuthController::class, 'verifyOtp']);
            Route::post('/google', [AuthController::class, 'google']);
            Route::post('/facebook', [AuthController::class, 'facebook']);
        });

        Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    });

    Route::prefix('casino')->group(function () {
        Route::get('/categories', [GameCategoryController::class, 'index']);
        Route::get('/categories/{slug}', [GameCategoryController::class, 'show']);
        Route::get('/providers', [GameProviderController::class, 'index']);
        Route::get('/games', [GameController::class, 'index']);
        Route::get('/games/{slug}', [GameController::class, 'show']);
    });

    Route::prefix('content')->group(function () {
        Route::get('/banners', [ContentController::class, 'banners']);
        Route::get('/promotions', [ContentController::class, 'promotions']);
        Route::get('/promotions/{slug}', [ContentController::class, 'promotion']);
        Route::get('/posts', [ContentController::class, 'posts']);
        Route::get('/posts/{slug}', [ContentController::class, 'post']);
    });

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::get('/me', [ProfileController::class, 'show']);
        Route::put('/me', [ProfileController::class, 'update']);
        Route::put('/me/password', [ProfileController::class, 'password']);

        Route::post('/casino/games/{slug}/launch', [GameController::class, 'launch']);

        Route::prefix('wallet')->group(function () {
            Route::get('/', [WalletController::class, 'summary']);
            Route::get('/transactions', [WalletController::class, 'transactions']);
            Route::post('/deposit', [WalletController::class, 'deposit'])->middleware('throttle:6,1');
            Route::post('/withdraw', [WalletController::class, 'withdraw'])->middleware('throttle:6,1');
        });

        Route::prefix('admin')->middleware('backoffice')->group(function () {
            Route::get('/stats', Admin\StatsController::class)->middleware('permission:dashboard.view');

            Route::middleware('permission:users.view')->group(function () {
                Route::get('/users', [Admin\UserController::class, 'index']);
                Route::get('/users/{id}', [Admin\UserController::class, 'show'])->whereNumber('id');
            });

            Route::middleware('permission:users.manage')->group(function () {
                Route::post('/users', [Admin\UserController::class, 'store']);
                Route::put('/users/{id}', [Admin\UserController::class, 'update'])->whereNumber('id');
                Route::delete('/users/{id}', [Admin\UserController::class, 'destroy'])->whereNumber('id');
            });

            Route::post('/users/{id}/balance', [Admin\UserController::class, 'adjustBalance'])
                ->whereNumber('id')
                ->middleware('permission:users.balance');

            Route::middleware('permission:games.manage')->group(function () {
                Route::apiResource('games', Admin\GameController::class)->parameters(['games' => 'id']);
                Route::apiResource('categories', Admin\GameCategoryController::class)->parameters(['categories' => 'id']);
                Route::apiResource('providers', Admin\GameProviderController::class)->parameters(['providers' => 'id']);
            });

            Route::middleware('permission:content.manage')->group(function () {
                Route::apiResource('banners', Admin\BannerController::class)->parameters(['banners' => 'id']);
                Route::apiResource('promotions', Admin\PromotionController::class)->parameters(['promotions' => 'id']);
                Route::apiResource('posts', Admin\PostController::class)->parameters(['posts' => 'id']);
            });

            Route::post('/uploads', Admin\UploadController::class)->middleware('permission:games.manage|content.manage');

            Route::get('/transactions', [Admin\TransactionController::class, 'index'])->middleware('permission:transactions.view');

            Route::middleware('permission:transactions.process')->group(function () {
                Route::post('/transactions/{id}/approve', [Admin\TransactionController::class, 'approve'])->whereNumber('id');
                Route::post('/transactions/{id}/reject', [Admin\TransactionController::class, 'reject'])->whereNumber('id');
            });

            Route::middleware('permission:roles.manage')->group(function () {
                Route::get('/roles', [Admin\RoleController::class, 'index']);
                Route::post('/roles', [Admin\RoleController::class, 'store']);
                Route::put('/roles/{id}', [Admin\RoleController::class, 'update'])->whereNumber('id');
                Route::delete('/roles/{id}', [Admin\RoleController::class, 'destroy'])->whereNumber('id');
            });
        });
    });
});
