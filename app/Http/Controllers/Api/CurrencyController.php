<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class CurrencyController extends Controller
{
    /**
     * Display a listing of active currencies.
     */
    public function index(Request $request): JsonResponse
    {
        $currencies = Currency::active()
            ->orderBy('code')
            ->get()
            ->map(function ($currency) {
                return [
                    'uuid' => $currency->uuid,
                    'code' => $currency->code,
                    'name' => $currency->name,
                    'symbol' => $currency->symbol,
                    'exchange_rate' => $currency->exchange_rate,
                    'display_name' => $currency->display_name,
                    'full_name' => $currency->full_name,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Currencies retrieved successfully',
            'data' => $currencies
        ]);
    }

    /**
     * Store a newly created currency.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|size:3|unique:currencies,code',
            'name' => 'required|string|max:255',
            'symbol' => 'required|string|max:10',
            'exchange_rate' => 'required|numeric|min:0.000001',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $currency = Currency::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Currency created successfully',
            'data' => [
                'uuid' => $currency->uuid,
                'code' => $currency->code,
                'name' => $currency->name,
                'symbol' => $currency->symbol,
                'exchange_rate' => $currency->exchange_rate,
                'is_active' => $currency->is_active,
                'display_name' => $currency->display_name,
                'full_name' => $currency->full_name,
            ]
        ], 201);
    }

    /**
     * Display the specified currency.
     */
    public function show(string $uuid): JsonResponse
    {
        $currency = Currency::where('uuid', $uuid)->first();

        if (!$currency) {
            return response()->json([
                'success' => false,
                'message' => 'Currency not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Currency retrieved successfully',
            'data' => [
                'uuid' => $currency->uuid,
                'code' => $currency->code,
                'name' => $currency->name,
                'symbol' => $currency->symbol,
                'exchange_rate' => $currency->exchange_rate,
                'is_active' => $currency->is_active,
                'display_name' => $currency->display_name,
                'full_name' => $currency->full_name,
                'created_at' => $currency->created_at,
                'updated_at' => $currency->updated_at,
            ]
        ]);
    }

    /**
     * Update the specified currency.
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $currency = Currency::where('uuid', $uuid)->first();

        if (!$currency) {
            return response()->json([
                'success' => false,
                'message' => 'Currency not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'code' => 'sometimes|required|string|size:3|unique:currencies,code,' . $currency->uuid . ',uuid',
            'name' => 'sometimes|required|string|max:255',
            'symbol' => 'sometimes|required|string|max:10',
            'exchange_rate' => 'sometimes|required|numeric|min:0.000001',
            'is_active' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $currency->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Currency updated successfully',
            'data' => [
                'uuid' => $currency->uuid,
                'code' => $currency->code,
                'name' => $currency->name,
                'symbol' => $currency->symbol,
                'exchange_rate' => $currency->exchange_rate,
                'is_active' => $currency->is_active,
                'display_name' => $currency->display_name,
                'full_name' => $currency->full_name,
            ]
        ]);
    }

    /**
     * Remove the specified currency.
     */
    public function destroy(string $uuid): JsonResponse
    {
        $currency = Currency::where('uuid', $uuid)->first();

        if (!$currency) {
            return response()->json([
                'success' => false,
                'message' => 'Currency not found'
            ], 404);
        }

        // Check if currency is in use
        $employeeCount = $currency->employees()->count();
        $payrollCount = $currency->payrolls()->count();

        if ($employeeCount > 0 || $payrollCount > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete currency that is in use by employees or payrolls',
                'data' => [
                    'employees_count' => $employeeCount,
                    'payrolls_count' => $payrollCount
                ]
            ], 409);
        }

        $currency->delete();

        return response()->json([
            'success' => true,
            'message' => 'Currency deleted successfully'
        ]);
    }

    /**
     * Get exchange rates for conversion.
     */
    public function exchangeRates(): JsonResponse
    {
        $currencies = Currency::active()
            ->orderBy('code')
            ->get(['uuid', 'code', 'exchange_rate'])
            ->mapWithKeys(function ($currency) {
                return [$currency->code => $currency->exchange_rate];
            });

        return response()->json([
            'success' => true,
            'message' => 'Exchange rates retrieved successfully',
            'data' => $currencies
        ]);
    }
}
