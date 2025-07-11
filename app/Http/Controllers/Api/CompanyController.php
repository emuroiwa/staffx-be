<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CompanyService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class CompanyController extends Controller
{
    public function __construct(
        private CompanyService $companyService
    ) {}

    /**
     * Display the current user's companies (HCA only) or current company (other roles).
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if ($user->isHoldingCompanyAdmin()) {
                // HCA can see all companies they created with pagination and filters
                $filters = [
                    'search' => $request->get('search'),
                    'is_active' => $request->get('is_active'),
                    'subscription_status' => $request->get('subscription_status'),
                ];

                $perPage = $request->get('per_page', 15);
                $companies = $this->companyService->getCompaniesForUser($user, $filters, $perPage);
                
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
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
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
            'website' => 'nullable|url|max:255',
            'tax_id' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'is_active' => 'sometimes|boolean',
            'subscription_expires_at' => 'nullable|date|after:today',
        ]);

        try {
            $user = auth()->user();
            $data = $request->validated();
            
            // Set default values
            $data['is_active'] = $data['is_active'] ?? true;

            $company = $this->companyService->createCompany($data, $user);

            return response()->json([
                'success' => true,
                'message' => 'Company created successfully',
                'data' => $company->load(['creator']),
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getTraceAsString() : null,
            ], $this->getStatusCodeFromException($e));
        }
    }

    /**
     * Display the specified company.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $company = $this->companyService->getCompanyById($id, $user);

            return response()->json([
                'success' => true,
                'data' => $company,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getTraceAsString() : null,
            ], $this->getStatusCodeFromException($e));
        }
    }

    /**
     * Update the specified company.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|nullable|email|max:255',
            'phone' => 'sometimes|nullable|string|max:20',
            'website' => 'sometimes|nullable|url|max:255',
            'tax_id' => 'sometimes|nullable|string|max:50',
            'address' => 'sometimes|nullable|string|max:500',
            'city' => 'sometimes|nullable|string|max:100',
            'state' => 'sometimes|nullable|string|max:100',
            'postal_code' => 'sometimes|nullable|string|max:20',
            'country' => 'sometimes|nullable|string|max:100',
            'is_active' => 'sometimes|boolean',
            'subscription_expires_at' => 'sometimes|nullable|date',
        ]);

        try {
            $user = auth()->user();
            $data = $request->validated();

            $company = $this->companyService->updateCompany($id, $data, $user);

            return response()->json([
                'success' => true,
                'message' => 'Company updated successfully',
                'data' => $company,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getTraceAsString() : null,
            ], $this->getStatusCodeFromException($e));
        }
    }

    /**
     * Remove the specified company (HCA only).
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $this->companyService->deleteCompany($id, $user);

            return response()->json([
                'success' => true,
                'message' => 'Company deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getTraceAsString() : null,
            ], $this->getStatusCodeFromException($e));
        }
    }

    /**
     * Get company statistics.
     */
    public function stats(int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $stats = $this->companyService->getCompanyStats($id, $user);

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getTraceAsString() : null,
            ], $this->getStatusCodeFromException($e));
        }
    }

    /**
     * Set default company for HCA user.
     */
    public function setDefault(int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $company = $this->companyService->setDefaultCompany($id, $user);

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
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getTraceAsString() : null,
            ], $this->getStatusCodeFromException($e));
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

            $defaultCompany = $this->companyService->getDefaultCompany($user);

            return response()->json([
                'success' => true,
                'data' => [
                    'default_company' => $defaultCompany,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getTraceAsString() : null,
            ], $this->getStatusCodeFromException($e));
        }
    }

    /**
     * Search companies for HCA user.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:1|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            $user = auth()->user();
            $search = $request->get('q');
            $filters = $request->only(['is_active']);

            $companies = $this->companyService->searchCompanies($search, $user, $filters);

            return response()->json([
                'success' => true,
                'data' => $companies,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getTraceAsString() : null,
            ], $this->getStatusCodeFromException($e));
        }
    }

    /**
     * Get companies for selection (dropdown, etc.).
     */
    public function selection(): JsonResponse
    {
        try {
            $user = auth()->user();
            $companies = $this->companyService->getCompaniesForSelection($user);

            return response()->json([
                'success' => true,
                'data' => $companies,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getTraceAsString() : null,
            ], $this->getStatusCodeFromException($e));
        }
    }

    /**
     * Bulk update company status.
     */
    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        $request->validate([
            'company_ids' => 'required|array|min:1',
            'company_ids.*' => 'integer|exists:companies,id',
            'is_active' => 'required|boolean',
        ]);

        try {
            $user = auth()->user();
            $companyIds = $request->get('company_ids');
            $isActive = $request->get('is_active');

            $updatedCount = $this->companyService->bulkUpdateCompanyStatus($companyIds, $isActive, $user);

            return response()->json([
                'success' => true,
                'message' => "Successfully updated {$updatedCount} companies",
                'data' => [
                    'updated_count' => $updatedCount,
                    'is_active' => $isActive,
                ],
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getTraceAsString() : null,
            ], $this->getStatusCodeFromException($e));
        }
    }

    /**
     * Get dashboard statistics for HCA.
     */
    public function dashboard(): JsonResponse
    {
        try {
            $user = auth()->user();
            $stats = $this->companyService->getDashboardStats($user);

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getTraceAsString() : null,
            ], $this->getStatusCodeFromException($e));
        }
    }

    /**
     * Get company by slug.
     */
    public function getBySlug(string $slug): JsonResponse
    {
        try {
            $user = auth()->user();
            $company = $this->companyService->getCompanyBySlug($slug, $user);

            return response()->json([
                'success' => true,
                'data' => $company,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getTraceAsString() : null,
            ], $this->getStatusCodeFromException($e));
        }
    }

    /**
     * Get appropriate HTTP status code from exception message.
     */
    private function getStatusCodeFromException(\Exception $e): int
    {
        $message = $e->getMessage();
        
        if (str_contains($message, 'not found') || str_contains($message, 'not have permission')) {
            return 404;
        }
        
        if (str_contains($message, 'Only Holding Company Admins') || str_contains($message, 'Unauthorized')) {
            return 403;
        }
        
        if (str_contains($message, 'trial has expired') || str_contains($message, 'limit')) {
            return 422;
        }
        
        return 500;
    }
}