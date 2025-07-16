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
            'type' => 'sometimes|in:allowance,deduction,employer_contribution',
            'is_active' => 'sometimes|boolean',
            'per_page' => 'sometimes|integer|min:1|max:100'
        ]);

        // if ($validator->fails()) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Validation failed',
        //         'errors' => $validator->errors()
        //     ], 422);
        // }

        \Log::info("xxxxxxxxx " .\print_r(Auth::user(), \true));

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
            'type' => 'required|in:allowance,deduction,employer_contribution',
            'calculation_method' => 'required|in:fixed_amount,percentage_of_salary,percentage_of_basic,formula',
            'amount' => 'required_if:calculation_method,fixed_amount|nullable|numeric|min:0',
            'default_percentage' => 'required_if:calculation_method,percentage_of_salary,percentage_of_basic|nullable|numeric|min:0|max:100',
            'formula_expression' => 'required_if:calculation_method,formula|nullable|string',
            'minimum_amount' => 'sometimes|nullable|numeric|min:0',
            'maximum_amount' => 'sometimes|nullable|numeric|min:0|gte:minimum_amount',
            
            // Employer contribution specific fields
            'contribution_type' => 'required_if:type,employer_contribution|nullable|string|in:pension,medical_aid,provident_fund,group_life,disability,training_levy,other',
            'has_employee_match' => 'sometimes|boolean',
            'match_logic' => 'required_if:has_employee_match,true|nullable|string|in:equal,percentage,custom',
            'employee_match_amount' => 'required_if:match_logic,custom|nullable|numeric|min:0',
            'employee_match_percentage' => 'required_if:match_logic,percentage,custom|nullable|numeric|min:0|max:100',
            
            'is_taxable' => [
                'sometimes',
                'boolean',
                function ($attribute, $value, $fail) use ($request) {
                    if (($request->type === 'deduction' || $request->type === 'employer_contribution') && $value === true) {
                        $fail('Deductions and employer contributions cannot be taxable.');
                    }
                }
            ],
            'is_pensionable' => [
                'sometimes',
                'boolean',
                function ($attribute, $value, $fail) use ($request) {
                    if (($request->type === 'deduction' || $request->type === 'employer_contribution') && $value === true) {
                        $fail('Deductions and employer contributions cannot be pensionable.');
                    }
                }
            ],
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

            // Automatically set taxable/pensionable based on type
            $isTaxable = ($request->type === 'deduction' || $request->type === 'employer_contribution') ? false : $request->get('is_taxable', true);
            $isPensionable = ($request->type === 'deduction' || $request->type === 'employer_contribution') ? false : $request->get('is_pensionable', true);

            $template = CompanyPayrollTemplate::create([
                'company_uuid' => $company->uuid,
                'code' => $request->code,
                'name' => $request->name,
                'description' => $request->description,
                'type' => $request->type,
                'contribution_type' => $request->contribution_type,
                'has_employee_match' => $request->get('has_employee_match', false),
                'match_logic' => $request->match_logic,
                'employee_match_amount' => $request->employee_match_amount,
                'employee_match_percentage' => $request->employee_match_percentage,
                'calculation_method' => $request->calculation_method,
                'amount' => $request->amount,
                'default_percentage' => $request->default_percentage,
                'formula_expression' => $request->formula_expression,
                'minimum_amount' => $request->minimum_amount,
                'maximum_amount' => $request->maximum_amount,
                'is_taxable' => $isTaxable,
                'is_pensionable' => $isPensionable,
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
            
            // Employer contribution specific fields
            'contribution_type' => 'sometimes|nullable|string|in:pension,medical_aid,provident_fund,group_life,disability,training_levy,other',
            'has_employee_match' => 'sometimes|boolean',
            'match_logic' => 'sometimes|nullable|string|in:equal,percentage,custom',
            'employee_match_amount' => 'sometimes|nullable|numeric|min:0',
            'employee_match_percentage' => 'sometimes|nullable|numeric|min:0|max:100',
            
            'is_taxable' => [
                'sometimes',
                'boolean',
                function ($attribute, $value, $fail) use ($request, $template) {
                    $type = $request->type ?? $template->type;
                    if (($type === 'deduction' || $type === 'employer_contribution') && $value === true) {
                        $fail('Deductions and employer contributions cannot be taxable.');
                    }
                }
            ],
            'is_pensionable' => [
                'sometimes',
                'boolean',
                function ($attribute, $value, $fail) use ($request, $template) {
                    $type = $request->type ?? $template->type;
                    if (($type === 'deduction' || $type === 'employer_contribution') && $value === true) {
                        $fail('Deductions and employer contributions cannot be pensionable.');
                    }
                }
            ],
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
            $updateData = $request->only([
                'name', 'description', 'calculation_method', 'amount', 
                'default_percentage', 'formula_expression', 'minimum_amount',
                'maximum_amount', 'eligibility_rules', 'requires_approval', 'is_active',
                'contribution_type', 'has_employee_match', 'match_logic',
                'employee_match_amount', 'employee_match_percentage'
            ]);

            // Handle taxable/pensionable based on type
            $currentType = $request->type ?? $template->type;
            if ($currentType === 'deduction' || $currentType === 'employer_contribution') {
                $updateData['is_taxable'] = false;
                $updateData['is_pensionable'] = false;
            } else {
                if ($request->has('is_taxable')) {
                    $updateData['is_taxable'] = $request->is_taxable;
                }
                if ($request->has('is_pensionable')) {
                    $updateData['is_pensionable'] = $request->is_pensionable;
                }
            }

            $template->update($updateData);

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
            $mockEmployee = new \App\Models\Employee();
            $mockEmployee->salary = $request->employee_basic_salary;
            $mockEmployee->hire_date = now()->subYears(2); // 2 years of service for testing
            $mockEmployee->exists = false; // Mark as not persisted to avoid save attempts

            $grossSalary = $request->get('gross_salary', $request->employee_basic_salary);
            
            // Handle employer contributions differently
            if ($template->type === 'employer_contribution') {
                $contributionResult = $template->calculateEmployerContribution($mockEmployee, $grossSalary);
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'calculated_amount' => $contributionResult['employer_amount'],
                        'employer_amount' => $contributionResult['employer_amount'],
                        'employee_amount' => $contributionResult['employee_amount'],
                        'total_amount' => $contributionResult['total_amount'],
                        'employee_basic_salary' => $request->employee_basic_salary,
                        'gross_salary_used' => $grossSalary,
                        'calculation_method' => $template->calculation_method,
                        'calculation_details' => $contributionResult['calculation_details'],
                        'template_settings' => [
                            'type' => $template->type,
                            'contribution_type' => $template->contribution_type,
                            'has_employee_match' => $template->has_employee_match,
                            'match_logic' => $template->match_logic,
                            'amount' => $template->amount,
                            'default_percentage' => $template->default_percentage,
                            'employee_match_amount' => $template->employee_match_amount,
                            'employee_match_percentage' => $template->employee_match_percentage,
                            'formula_expression' => $template->formula_expression,
                            'minimum_amount' => $template->minimum_amount,
                            'maximum_amount' => $template->maximum_amount
                        ]
                    ],
                    'message' => 'Employer contribution calculation test completed successfully'
                ]);
            }
            
            // Regular calculation for allowances and deductions
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