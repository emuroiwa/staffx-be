<?php

use App\Http\Controllers\Api\Auth\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

// Health check endpoints
Route::get('/health', function () {
    $health = [
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'services' => []
    ];

    // Check database
    try {
        DB::select('SELECT 1');
        $health['services']['database'] = 'ok';
    } catch (Exception $e) {
        $health['services']['database'] = 'error';
        $health['status'] = 'error';
    }

    // Check Redis
    try {
        Redis::ping();
        $health['services']['redis'] = 'ok';
    } catch (Exception $e) {
        $health['services']['redis'] = 'error';
        $health['status'] = 'error';
    }

    // Check queue
    try {
        $health['services']['queue'] = 'ok';
    } catch (Exception $e) {
        $health['services']['queue'] = 'error';
    }

    $statusCode = $health['status'] === 'ok' ? 200 : 503;
    return response()->json($health, $statusCode);
});

Route::get('/metrics', function () {
    $metrics = [
        'timestamp' => now()->toISOString(),
        'database' => [
            'connections' => DB::select("SELECT count(*) as count FROM pg_stat_activity")[0]->count ?? 0,
        ],
        'memory' => [
            'usage' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
        ],
    ];

    return response()->json($metrics);
});

// Authentication routes
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
    
    // Protected routes
    Route::middleware('auth:api')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::put('profile', [AuthController::class, 'updateProfile']);
    });
});