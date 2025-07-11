<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CompanyController extends Controller
{
    /**
     * Display the current user's companies (HCA only) or current company (other roles).
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if ($user->isHoldingCompanyAdmin()) {
                // HCA can see all companies they created
                $query = $user->ownedCompanies()->with(['users', 'employees']);
                
                // Apply filters
                if ($request->has('is_active') && $request->is_active !== '') {
                    $query->where('is_active', (bool) $request->is_active);
                }

                if ($request->has('search') && $request->search) {
                    $search = $request->search;
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                          ->orWhere('email', 'like', "%{$search}%");
                    });
                }

                $companies = $query->paginate($request->get('per_page', 15));
                
                return response()->json([
                    'success' => true,
                    'data' => $companies,
                ]);
            } else {
                // Other roles see their current company
                $company = $user->company;
                
                if (!$company) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No company associated with user',
                    ], 404);
                }

                return response()->json([
                    'success' => true,
                    'data' => $company,
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch companies',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created company (HCA only).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'website' => 'nullable|url|max:255',
            'tax_id' => 'nullable|string|max:50',
            'subscription_expires_at' => 'nullable|date',
            'settings' => 'nullable|array',
        ]);

        try {
            $user = auth()->user();
            
            if (!$user->isHoldingCompanyAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only Holding Company Admins can create companies',
                ], 403);
            }

            $data = $request->validated();
            $data['created_by'] = $user->id;

            $company = Company::create($data);
            $company->load(['creator', 'users', 'employees']);

            return response()->json([
                'success' => true,
                'message' => 'Company created successfully',
                'data' => $company,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create company',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified company.
     */
    public function show(Company $company): JsonResponse
    {
        try {
            $user = auth()->user();
            
            // Check permissions
            if ($user->isHoldingCompanyAdmin()) {
                // HCA can only see companies they created
                if ($company->created_by !== $user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized to access this company',
                    ], 403);
                }
            } else {
                // Other roles can only see their own company
                if ($company->id !== $user->company_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized to access this company',
                    ], 403);
                }
            }

            $company->load(['creator', 'users', 'employees']);

            return response()->json([
                'success' => true,
                'data' => $company,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch company information',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified company.
     */
    public function update(Request $request, Company $company): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|nullable|email|max:255',
            'phone' => 'sometimes|nullable|string|max:20',
            'address' => 'sometimes|nullable|string|max:500',
            'city' => 'sometimes|nullable|string|max:100',
            'state' => 'sometimes|nullable|string|max:100',
            'postal_code' => 'sometimes|nullable|string|max:20',
            'country' => 'sometimes|nullable|string|max:100',
            'website' => 'sometimes|nullable|url|max:255',
            'tax_id' => 'sometimes|nullable|string|max:50',
            'subscription_expires_at' => 'sometimes|nullable|date',
            'is_active' => 'sometimes|boolean',
            'settings' => 'sometimes|nullable|array',
        ]);

        try {
            $user = auth()->user();
            
            // Check permissions
            if ($user->isHoldingCompanyAdmin()) {
                // HCA can only update companies they created
                if ($company->created_by !== $user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized to modify this company',
                    ], 403);
                }
            } else {
                // Other roles need manage_company permission for their company
                if ($company->id !== $user->company_id || !$user->hasPermission('manage_company')) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized to modify this company',
                    ], 403);
                }
            }

            $data = $request->validated();
            
            // Merge settings if provided
            if (isset($data['settings'])) {
                $data['settings'] = array_merge($company->settings ?? [], $data['settings']);
            }

            $company->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Company updated successfully',
                'data' => $company->fresh(['creator', 'users', 'employees']),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update company',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified company (HCA only).
     */
    public function destroy(Company $company): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user->isHoldingCompanyAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only Holding Company Admins can delete companies',
                ], 403);
            }

            // HCA can only delete companies they created
            if ($company->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to delete this company',
                ], 403);
            }

            // Check if company has employees or users
            if ($company->employees()->count() > 0 || $company->users()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete company with existing employees or users. Please transfer or remove them first.',
                ], 422);
            }

            $company->delete();

            return response()->json([
                'success' => true,
                'message' => 'Company deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete company',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get company statistics.
     */
    public function stats(Company $company): JsonResponse
    {
        try {
            $user = auth()->user();
            
            // Check permissions
            if ($user->isHoldingCompanyAdmin()) {
                // HCA can only see stats for companies they created
                if ($company->created_by !== $user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized to access this company statistics',
                    ], 403);
                }
            } else {
                // Other roles can only see their own company stats
                if ($company->id !== $user->company_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized to access this company statistics',
                    ], 403);
                }
            }

            $stats = [
                'total_employees' => $company->employees()->count(),
                'active_employees' => $company->employees()->where('status', 'active')->count(),
                'total_users' => $company->users()->count(),
                'departments' => $company->employees()
                    ->select('department')
                    ->whereNotNull('department')
                    ->distinct()
                    ->count(),
                'recent_payrolls' => $company->payrolls()
                    ->where('created_at', '>=', now()->subMonth())
                    ->count(),
                'company_status' => [
                    'is_active' => $company->is_active,
                    'has_active_subscription' => $company->hasActiveSubscription(),
                    'subscription_expires_at' => $company->subscription_expires_at,
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch company statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Set default company for HCA user.
     */
    public function setDefault(Company $company): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user->isHoldingCompanyAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only Holding Company Admins can set default companies',
                ], 403);
            }

            // HCA can only set default for companies they created
            if ($company->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to set this company as default',
                ], 403);
            }

            $user->update(['default_company_id' => $company->id]);

            return response()->json([
                'success' => true,
                'message' => 'Default company set successfully',
                'data' => [
                    'default_company' => $company,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to set default company',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get HCA user's current default company.
     */
    public function getDefault(): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user->isHoldingCompanyAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only Holding Company Admins have default companies',
                ], 403);
            }

            $defaultCompany = $user->defaultCompany;

            return response()->json([
                'success' => true,
                'data' => [
                    'default_company' => $defaultCompany,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch default company',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}