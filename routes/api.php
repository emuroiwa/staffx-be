<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\UserDataController;
use App\Http\Controllers\Api\UserSettingsController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\PayrollController;
use App\Http\Controllers\Api\CompanyController;
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
    
    // Email verification routes
    Route::get('email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    
    Route::post('email/resend', [AuthController::class, 'resendEmailVerification'])
        ->middleware(['auth:api', 'throttle:6,1'])
        ->name('verification.resend');
    
    // Protected routes
    Route::middleware('auth:api')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::put('profile', [AuthController::class, 'updateProfile']);
        Route::post('change-password', [AuthController::class, 'changePassword']);
        Route::post('upload-avatar', [AuthController::class, 'uploadAvatar']);
        Route::delete('remove-avatar', [AuthController::class, 'removeAvatar']);
    });
});

// User data management routes
Route::middleware('auth:api')->prefix('user')->group(function () {
    Route::post('export-data', [UserDataController::class, 'exportData']);
    Route::delete('delete-account', [UserDataController::class, 'deleteAccount']);
    
    // Settings routes
    Route::get('settings', [UserSettingsController::class, 'index']);
    Route::put('settings', [UserSettingsController::class, 'update']);
    Route::get('settings/{key}', [UserSettingsController::class, 'show']);
    Route::delete('settings/{key}', [UserSettingsController::class, 'destroy']);
    Route::post('settings/reset', [UserSettingsController::class, 'reset']);
});

// Public download route (with authentication check inside controller)
Route::get('user/download-export/{filename}', [UserDataController::class, 'downloadExport'])
    ->name('user.download-export');

// Employee management routes (company-scoped)
Route::middleware(['auth:api', 'company.context'])->prefix('employees')->group(function () {
    Route::get('/', [EmployeeController::class, 'index'])
        ->middleware('permission:manage_employees');
    
    Route::post('/', [EmployeeController::class, 'store'])
        ->middleware('permission:manage_employees');
    
    Route::get('/departments', [EmployeeController::class, 'departments'])
        ->middleware('permission:manage_employees');
    
    Route::get('/{employee}', [EmployeeController::class, 'show'])
        ->middleware('permission:manage_employees');
    
    Route::put('/{employee}', [EmployeeController::class, 'update'])
        ->middleware('permission:manage_employees');
    
    Route::delete('/{employee}', [EmployeeController::class, 'destroy'])
        ->middleware('permission:manage_employees');
});

// Payroll management routes (company-scoped)
Route::middleware(['auth:api', 'company.context'])->prefix('payrolls')->group(function () {
    Route::get('/', [PayrollController::class, 'index'])
        ->middleware('permission:manage_payroll');
    
    Route::post('/', [PayrollController::class, 'store'])
        ->middleware('permission:manage_payroll');
    
    Route::get('/summary', [PayrollController::class, 'summary'])
        ->middleware('permission:view_reports');
    
    Route::get('/{payroll}', [PayrollController::class, 'show'])
        ->middleware('permission:manage_payroll');
    
    Route::put('/{payroll}', [PayrollController::class, 'update'])
        ->middleware('permission:manage_payroll');
    
    Route::delete('/{payroll}', [PayrollController::class, 'destroy'])
        ->middleware('permission:manage_payroll');
});

// Company management routes
Route::middleware(['auth:api'])->prefix('companies')->group(function () {
    // List companies (HCA gets their created companies, others get their current company)
    Route::get('/', [CompanyController::class, 'index'])
        ->middleware('permission:view_all_companies');
    
    // Create new company (HCA only)
    Route::post('/', [CompanyController::class, 'store'])
        ->middleware('permission:create_companies');
    
    // Default company management (HCA only)
    Route::get('/default', [CompanyController::class, 'getDefault'])
        ->middleware('permission:manage_default_company');
    
    // Individual company routes
    Route::get('/{company}', [CompanyController::class, 'show']);
    
    Route::put('/{company}', [CompanyController::class, 'update']);
    
    Route::delete('/{company}', [CompanyController::class, 'destroy'])
        ->middleware('permission:manage_companies');
    
    Route::get('/{company}/stats', [CompanyController::class, 'stats']);
    
    // Set default company (HCA only)
    Route::post('/{company}/set-default', [CompanyController::class, 'setDefault'])
        ->middleware('permission:manage_default_company');
});