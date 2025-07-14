<?php

namespace App\Http\Controllers;

use App\Models\CompanyPayrollTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CompanyPayrollTemplateController extends Controller
{
    /**
     * Display a listing of company payroll templates.
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'sometimes|in:allowance,deduction',
            'is_active' => 'sometimes|boolean',
            'per_page' => 'sometimes|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $company = Auth::user()->company;
            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'User must belong to a company'
                ], 403);
            }

            $query = CompanyPayrollTemplate::with(['category'])
                ->where('company_uuid', $company->uuid)
                ->orderBy('created_at', 'desc');

            // Filter by type
            if ($request->filled('type')) {
                $query->where('type', $request->type);
            }

            // Filter by active status
            if ($request->filled('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $perPage = $request->get('per_page', 15);
            $templates = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $templates,
                'message' => 'Company payroll templates retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve company payroll templates: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created company payroll template.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'description' => 'sometimes|string|max:1000',
            'type' => 'required|in:allowance,deduction',
            'calculation_method' => 'required|in:fixed_amount,percentage_of_salary,percentage_of_basic,formula',
            'amount' => 'required_if:calculation_method,fixed_amount|nullable|numeric|min:0',
            'default_percentage' => 'required_if:calculation_method,percentage_of_salary,percentage_of_basic|nullable|numeric|min:0|max:100',
            'formula_expression' => 'required_if:calculation_method,formula|nullable|string',
            'minimum_amount' => 'sometimes|nullable|numeric|min:0',
            'maximum_amount' => 'sometimes|nullable|numeric|min:0|gte:minimum_amount',
            'is_taxable' => 'sometimes|boolean',
            'is_pensionable' => 'sometimes|boolean',
            'eligibility_rules' => 'sometimes|array',
            'requires_approval' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $company = Auth::user()->company;
            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'User must belong to a company'
                ], 403);
            }

            // Check for duplicate code within company
            $existingTemplate = CompanyPayrollTemplate::where('company_uuid', $company->uuid)
                ->where('code', $request->code)
                ->first();

            if ($existingTemplate) {
                return response()->json([
                    'success' => false,
                    'message' => 'A payroll template with this code already exists'
                ], 422);
            }

            $template = CompanyPayrollTemplate::create([
                'company_uuid' => $company->uuid,
                'code' => $request->code,
                'name' => $request->name,
                'description' => $request->description,
                'type' => $request->type,
                'calculation_method' => $request->calculation_method,
                'amount' => $request->amount,
                'default_percentage' => $request->default_percentage,
                'formula_expression' => $request->formula_expression,
                'minimum_amount' => $request->minimum_amount,
                'maximum_amount' => $request->maximum_amount,
                'is_taxable' => $request->get('is_taxable', true),
                'is_pensionable' => $request->get('is_pensionable', true),
                'eligibility_rules' => $request->get('eligibility_rules', []),
                'requires_approval' => $request->get('requires_approval', false),
                'is_active' => true
            ]);

            return response()->json([
                'success' => true,
                'data' => $template->load(['category']),
                'message' => 'Company payroll template created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create company payroll template: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified company payroll template.
     */
    public function show(string $uuid): JsonResponse
    {
        $template = CompanyPayrollTemplate::with(['category', 'employeeItems'])
            ->where('uuid', $uuid)
            ->first();

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Company payroll template not found'
            ], 404);
        }

        // Verify access through company
        $company = Auth::user()->company;
        if (!$company || $template->company_uuid !== $company->uuid) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $template,
            'message' => 'Company payroll template retrieved successfully'
        ]);
    }

    /**
     * Update the specified company payroll template.
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $template = CompanyPayrollTemplate::where('uuid', $uuid)->first();

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Company payroll template not found'
            ], 404);
        }

        // Verify access through company
        $company = Auth::user()->company;
        if (!$company || $template->company_uuid !== $company->uuid) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|max:1000',
            'calculation_method' => 'sometimes|in:fixed_amount,percentage_of_salary,percentage_of_basic,formula',
            'amount' => 'sometimes|nullable|numeric|min:0',
            'default_percentage' => 'sometimes|nullable|numeric|min:0|max:100',
            'formula_expression' => 'sometimes|nullable|string',
            'minimum_amount' => 'sometimes|nullable|numeric|min:0',
            'maximum_amount' => 'sometimes|nullable|numeric|min:0',
            'is_taxable' => 'sometimes|boolean',
            'is_pensionable' => 'sometimes|boolean',
            'eligibility_rules' => 'sometimes|array',
            'requires_approval' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $template->update($request->only([
                'name', 'description', 'calculation_method', 'amount', 
                'default_percentage', 'formula_expression', 'minimum_amount',
                'maximum_amount', 'is_taxable', 'is_pensionable', 
                'eligibility_rules', 'requires_approval', 'is_active'
            ]));

            return response()->json([
                'success' => true,
                'data' => $template->fresh(['category', 'employeeItems']),
                'message' => 'Company payroll template updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update company payroll template: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified company payroll template.
     */
    public function destroy(string $uuid): JsonResponse
    {
        $template = CompanyPayrollTemplate::where('uuid', $uuid)->first();

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Company payroll template not found'
            ], 404);
        }

        // Verify access through company
        $company = Auth::user()->company;
        if (!$company || $template->company_uuid !== $company->uuid) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        try {
            // Check if template is being used
            $usageCount = $template->employeeItems()->count();
            if ($usageCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete template. It is currently used by {$usageCount} employee payroll item(s). Deactivate it instead."
                ], 422);
            }

            $template->delete();

            return response()->json([
                'success' => true,
                'message' => 'Company payroll template deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete company payroll template: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle the active status of a template.
     */
    public function toggleStatus(string $uuid): JsonResponse
    {
        $template = CompanyPayrollTemplate::where('uuid', $uuid)->first();

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Company payroll template not found'
            ], 404);
        }

        // Verify access through company
        $company = Auth::user()->company;
        if (!$company || $template->company_uuid !== $company->uuid) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        try {
            $template->update(['is_active' => !$template->is_active]);

            $status = $template->is_active ? 'activated' : 'deactivated';

            return response()->json([
                'success' => true,
                'data' => $template->fresh(['category']),
                'message' => "Company payroll template {$status} successfully"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle template status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test calculation for a template.
     */
    public function testCalculation(Request $request, string $uuid): JsonResponse
    {
        $template = CompanyPayrollTemplate::where('uuid', $uuid)->first();

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Company payroll template not found'
            ], 404);
        }

        // Verify access through company
        $company = Auth::user()->company;
        if (!$company || $template->company_uuid !== $company->uuid) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'employee_basic_salary' => 'required|numeric|min:0',
            'gross_salary' => 'sometimes|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Create a mock employee for testing
            $mockEmployee = (object) [
                'salary' => $request->employee_basic_salary,
                'hire_date' => now()->subYears(2) // 2 years of service for testing
            ];

            $grossSalary = $request->get('gross_salary', $request->employee_basic_salary);
            $calculatedAmount = $template->calculateAmount($mockEmployee, $grossSalary);

            return response()->json([
                'success' => true,
                'data' => [
                    'calculated_amount' => $calculatedAmount,
                    'employee_basic_salary' => $request->employee_basic_salary,
                    'gross_salary_used' => $grossSalary,
                    'calculation_method' => $template->calculation_method,
                    'template_settings' => [
                        'amount' => $template->amount,
                        'default_percentage' => $template->default_percentage,
                        'formula_expression' => $template->formula_expression,
                        'minimum_amount' => $template->minimum_amount,
                        'maximum_amount' => $template->maximum_amount
                    ]
                ],
                'message' => 'Calculation test completed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to test calculation: ' . $e->getMessage()
            ], 500);
        }
    }
}