<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\JsonResponse;

class CountryController extends Controller
{
    /**
     * Get all active countries for registration.
     */
    public function index(): JsonResponse
    {
        $countries = Country::where('is_active', true)
            ->select('uuid', 'name', 'iso_code', 'currency_code', 'is_supported_for_payroll')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $countries,
        ]);
    }

    /**
     * Get countries supported for payroll.
     */
    public function payrollSupported(): JsonResponse
    {
        $countries = Country::where('is_active', true)
            ->where('is_supported_for_payroll', true)
            ->select('uuid', 'name', 'iso_code', 'currency_code')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $countries,
        ]);
    }
}