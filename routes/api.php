<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\UserDataController;
use App\Http\Controllers\Api\UserSettingsController;
use App\Http\Controllers\Api\Employee\EmployeeController;
use App\Http\Controllers\Api\Employee\PositionController;
use App\Http\Controllers\Api\Employee\DepartmentController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\CompanyPayrollTemplateController;
use App\Http\Controllers\EmployeePayrollItemController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\CurrencyController;
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
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('password.forgot');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('password.reset');
    
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

// Employee Management Module routes (company-scoped)
Route::middleware(['auth:api', 'company.context'])->group(function () {
    
    // Employee routes
    Route::prefix('employees')->group(function () {
        Route::get('/', [EmployeeController::class, 'index'])
            ->middleware('permission:manage_employees');
        
        Route::post('/', [EmployeeController::class, 'store'])
            ->middleware('permission:manage_employees');
        
        Route::get('/statistics', [EmployeeController::class, 'stats'])
            ->middleware('permission:view_reports');
        
        Route::get('/organogram', [EmployeeController::class, 'organogram'])
            ->middleware('permission:view_reports');
        
        Route::get('/potential-managers/{employee?}', [EmployeeController::class, 'potentialManagers'])
            ->middleware('permission:manage_employees');
        
        Route::get('/{employee}', [EmployeeController::class, 'show'])
            ->middleware('permission:manage_employees');
        
        Route::put('/{employee}', [EmployeeController::class, 'update'])
            ->middleware('permission:manage_employees');
        
        Route::delete('/{employee}', [EmployeeController::class, 'destroy'])
            ->middleware('permission:manage_employees');
        
        Route::patch('/{employee}/status', [EmployeeController::class, 'updateStatus'])
            ->middleware('permission:manage_employees');
    });
    
    // Position routes
    Route::prefix('positions')->group(function () {
        Route::get('/', [PositionController::class, 'index'])
            ->middleware('permission:manage_employees');
        
        Route::post('/', [PositionController::class, 'store'])
            ->middleware('permission:manage_employees');
        
        Route::get('/statistics', [PositionController::class, 'statistics'])
            ->middleware('permission:view_reports');
        
        Route::get('/{position}', [PositionController::class, 'show'])
            ->middleware('permission:manage_employees');
        
        Route::put('/{position}', [PositionController::class, 'update'])
            ->middleware('permission:manage_employees');
        
        Route::delete('/{position}', [PositionController::class, 'destroy'])
            ->middleware('permission:manage_employees');
    });
    
    // Department routes
    Route::prefix('departments')->group(function () {
        Route::get('/', [DepartmentController::class, 'index'])
            ->middleware('permission:manage_employees');
        
        Route::post('/', [DepartmentController::class, 'store'])
            ->middleware('permission:manage_employees');
        
        Route::get('/statistics', [DepartmentController::class, 'statistics'])
            ->middleware('permission:view_reports');
        
        Route::get('/{department}', [DepartmentController::class, 'show'])
            ->middleware('permission:manage_employees');
        
        Route::put('/{department}', [DepartmentController::class, 'update'])
            ->middleware('permission:manage_employees');
        
        Route::delete('/{department}', [DepartmentController::class, 'destroy'])
            ->middleware('permission:manage_employees');
    });
    
    // Currency routes
    Route::prefix('currencies')->group(function () {
        Route::get('/', [CurrencyController::class, 'index']);
        Route::get('/exchange-rates', [CurrencyController::class, 'exchangeRates']);
        Route::get('/{currency}', [CurrencyController::class, 'show'])
            ->middleware('permission:manage_settings');
        Route::post('/', [CurrencyController::class, 'store'])
            ->middleware('permission:manage_settings');
        Route::put('/{currency}', [CurrencyController::class, 'update'])
            ->middleware('permission:manage_settings');
        Route::delete('/{currency}', [CurrencyController::class, 'destroy'])
            ->middleware('permission:manage_settings');
    });
});

// Payroll management routes (company-scoped)
Route::middleware(['auth:api', 'company.context'])->group(function () {
    
    // Payroll routes
    Route::prefix('payrolls')->group(function () {
        Route::get('/', [PayrollController::class, 'index'])
            ->middleware('permission:manage_payroll');
        
        Route::post('/', [PayrollController::class, 'store'])
            ->middleware('permission:manage_payroll');
        
        Route::post('/preview', [PayrollController::class, 'preview'])
            ->middleware('permission:manage_payroll');
        
        Route::get('/statistics', [PayrollController::class, 'statistics'])
            ->middleware('permission:view_reports');
        
        Route::get('/{payroll}', [PayrollController::class, 'show'])
            ->middleware('permission:manage_payroll');
        
        Route::put('/{payroll}', [PayrollController::class, 'update'])
            ->middleware('permission:manage_payroll');
        
        Route::delete('/{payroll}', [PayrollController::class, 'destroy'])
            ->middleware('permission:manage_payroll');
        
        Route::post('/{payroll}/approve', [PayrollController::class, 'approve'])
            ->middleware('permission:approve_payroll');
        
        Route::post('/{payroll}/process', [PayrollController::class, 'process'])
            ->middleware('permission:process_payroll');
    });
    
    // Company Payroll Template routes
    Route::prefix('payroll-templates')->group(function () {
        Route::get('/', [CompanyPayrollTemplateController::class, 'index']);
        
        Route::post('/', [CompanyPayrollTemplateController::class, 'store']);
        
        Route::get('/{template}', [CompanyPayrollTemplateController::class, 'show']);
        
        Route::put('/{template}', [CompanyPayrollTemplateController::class, 'update']);
        
        Route::delete('/{template}', [CompanyPayrollTemplateController::class, 'destroy']);
        
        Route::post('/{template}/toggle-status', [CompanyPayrollTemplateController::class, 'toggleStatus']);
        
        Route::post('/{template}/test-calculation', [CompanyPayrollTemplateController::class, 'testCalculation']);
    });
    
    // Employee Payroll Item routes
    Route::prefix('employee-payroll-items')->group(function () {
        Route::get('/', [EmployeePayrollItemController::class, 'index'])
            ->middleware('permission:manage_payroll');
        
        Route::post('/', [EmployeePayrollItemController::class, 'store'])
            ->middleware('permission:manage_payroll');
        
        Route::get('/{item}', [EmployeePayrollItemController::class, 'show'])
            ->middleware('permission:manage_payroll');
        
        Route::put('/{item}', [EmployeePayrollItemController::class, 'update'])
            ->middleware('permission:manage_payroll');
        
        Route::delete('/{item}', [EmployeePayrollItemController::class, 'destroy'])
            ->middleware('permission:manage_payroll');
        
        Route::post('/{item}/approve', [EmployeePayrollItemController::class, 'approve'])
            ->middleware('permission:approve_payroll');
        
        Route::post('/{item}/suspend', [EmployeePayrollItemController::class, 'suspend'])
            ->middleware('permission:manage_payroll');
        
        Route::post('/{item}/calculate-preview', [EmployeePayrollItemController::class, 'calculatePreview'])
            ->middleware('permission:manage_payroll');
    });
});

// Company management routes
Route::middleware(['auth:api'])->prefix('companies')->group(function () {
    // List companies (HCA gets their created companies, others get their current company)
    Route::get('/', [CompanyController::class, 'index']);
    
    // Create new company (HCA only)
    Route::post('/', [CompanyController::class, 'store']);
    
    // Search companies (HCA only)
    Route::get('/search', [CompanyController::class, 'search']);
    
    // Get companies for selection/dropdown (HCA only)
    Route::get('/selection', [CompanyController::class, 'selection']);
    
    // Dashboard statistics (HCA only)
    Route::get('/dashboard', [CompanyController::class, 'dashboard']);
    
    // Default company management (HCA only)
    Route::get('/default', [CompanyController::class, 'getDefault']);
    
    // Bulk operations (HCA only)
    Route::patch('/bulk/status', [CompanyController::class, 'bulkUpdateStatus']);
    
    // Company by slug
    Route::get('/slug/{slug}', [CompanyController::class, 'getBySlug']);
    
    // Individual company routes
    Route::get('/{id}', [CompanyController::class, 'show'])
        ->where('id', '[0-9]+');
    
    Route::put('/{id}', [CompanyController::class, 'update'])
        ->where('id', '[0-9]+');
    
    Route::delete('/{id}', [CompanyController::class, 'destroy'])
        ->where('id', '[0-9]+');
    
    Route::get('/{id}/stats', [CompanyController::class, 'stats'])
        ->where('id', '[0-9]+');
    
    // Set default company (HCA only)
    Route::post('/{id}/set-default', [CompanyController::class, 'setDefault'])
        ->where('id', '[0-9]+');
});