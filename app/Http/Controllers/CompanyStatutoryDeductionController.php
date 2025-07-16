<?php

namespace App\Http\Controllers;

use App\Models\CompanyStatutoryDeductionConfiguration;
use App\Models\StatutoryDeductionTemplate;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class CompanyStatutoryDeductionController extends Controller
{
    /**
     * Display a listing of company statutory deduction configurations.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $company = Auth::user()->company;
            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'User must belong to a company'
                ], 403);
            }

            $query = CompanyStatutoryDeductionConfiguration::with(['statutoryDeductionTemplate'])
                ->forCompany($company->uuid)
                ->orderBy('created_at', 'desc');

            // Filter by active status
            if ($request->filled('active')) {
                $isActive = filter_var($request->active, FILTER_VALIDATE_BOOLEAN);
                if ($isActive) {
                    $query->active();
                } else {
                    $query->inactive();
                }
            }

            $perPage = $request->get('per_page', 15);
            $configurations = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $configurations,
                'message' => 'Statutory deduction configurations retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve configurations: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available statutory deduction templates for configuration.
     */
    public function getAvailableTemplates(Request $request): JsonResponse
    {
        try {
            $company = Auth::user()->company;
            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'User must belong to a company'
                ], 403);
            }

            // Get country and jurisdiction for the company
            $country = $company->country;
            if (!$country) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company must have a country set'
                ], 400);
            }

            $jurisdiction = $country->getCurrentTaxJurisdiction();
            if (!$jurisdiction) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active tax jurisdiction found for company country'
                ], 400);
            }

            $templates = StatutoryDeductionTemplate::forJurisdiction($jurisdiction->uuid)
                ->active()
                ->where('is_employer_payable', true)
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $templates,
                'message' => 'Available templates retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve templates: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created company statutory deduction configuration.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'statutory_deduction_template_uuid' => 'required|exists:statutory_deduction_templates,uuid',
            'employer_covers_employee_portion' => 'required|boolean',
            'is_taxable_if_employer_paid' => 'required|boolean',
            'employer_rate_override' => 'nullable|numeric|min:0|max:1',
            'employee_rate_override' => 'nullable|numeric|min:0|max:1',
            'minimum_salary_override' => 'nullable|numeric|min:0',
            'maximum_salary_override' => 'nullable|numeric|min:0',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after:effective_from'
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

            // Check if template is employer payable
            $template = StatutoryDeductionTemplate::where('uuid', $request->statutory_deduction_template_uuid)
                ->where('is_employer_payable', true)
                ->first();

            if (!$template) {
                return response()->json([
                    'success' => false,
                    'message' => 'Template not found or not employer payable'
                ], 404);
            }

            // Check for existing active configuration
            $existingConfig = CompanyStatutoryDeductionConfiguration::forCompany($company->uuid)
                ->where('statutory_deduction_template_uuid', $request->statutory_deduction_template_uuid)
                ->active()
                ->effectiveAt(Carbon::parse($request->effective_from))
                ->first();

            if ($existingConfig) {
                return response()->json([
                    'success' => false,
                    'message' => 'An active configuration already exists for this template and date range'
                ], 422);
            }

            $configuration = CompanyStatutoryDeductionConfiguration::create([
                'company_uuid' => $company->uuid,
                'statutory_deduction_template_uuid' => $request->statutory_deduction_template_uuid,
                'employer_covers_employee_portion' => $request->employer_covers_employee_portion,
                'is_taxable_if_employer_paid' => $request->is_taxable_if_employer_paid,
                'employer_rate_override' => $request->employer_rate_override,
                'employee_rate_override' => $request->employee_rate_override,
                'minimum_salary_override' => $request->minimum_salary_override,
                'maximum_salary_override' => $request->maximum_salary_override,
                'effective_from' => Carbon::parse($request->effective_from),
                'effective_to' => $request->effective_to ? Carbon::parse($request->effective_to) : null,
                'created_by' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'data' => $configuration->load(['statutoryDeductionTemplate']),
                'message' => 'Configuration created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create configuration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified configuration.
     */
    public function show(string $uuid): JsonResponse
    {
        try {
            $company = Auth::user()->company;
            $configuration = CompanyStatutoryDeductionConfiguration::with(['statutoryDeductionTemplate'])
                ->forCompany($company->uuid)
                ->where('uuid', $uuid)
                ->first();

            if (!$configuration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Configuration not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $configuration,
                'message' => 'Configuration retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve configuration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified configuration.
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'employer_covers_employee_portion' => 'sometimes|boolean',
            'is_taxable_if_employer_paid' => 'sometimes|boolean',
            'employer_rate_override' => 'sometimes|nullable|numeric|min:0|max:1',
            'employee_rate_override' => 'sometimes|nullable|numeric|min:0|max:1',
            'minimum_salary_override' => 'sometimes|nullable|numeric|min:0',
            'maximum_salary_override' => 'sometimes|nullable|numeric|min:0',
            'effective_to' => 'sometimes|nullable|date|after:effective_from'
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
            $configuration = CompanyStatutoryDeductionConfiguration::forCompany($company->uuid)
                ->where('uuid', $uuid)
                ->first();

            if (!$configuration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Configuration not found'
                ], 404);
            }

            $configuration->update(array_merge(
                $request->only([
                    'employer_covers_employee_portion',
                    'is_taxable_if_employer_paid',
                    'employer_rate_override',
                    'employee_rate_override',
                    'minimum_salary_override',
                    'maximum_salary_override',
                    'effective_to'
                ]),
                ['updated_by' => Auth::id()]
            ));

            return response()->json([
                'success' => true,
                'data' => $configuration->fresh(['statutoryDeductionTemplate']),
                'message' => 'Configuration updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update configuration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified configuration.
     */
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $company = Auth::user()->company;
            $configuration = CompanyStatutoryDeductionConfiguration::forCompany($company->uuid)
                ->where('uuid', $uuid)
                ->first();

            if (!$configuration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Configuration not found'
                ], 404);
            }

            $configuration->delete();

            return response()->json([
                'success' => true,
                'message' => 'Configuration deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete configuration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle the active status of a configuration.
     */
    public function toggleStatus(string $uuid): JsonResponse
    {
        try {
            $company = Auth::user()->company;
            $configuration = CompanyStatutoryDeductionConfiguration::forCompany($company->uuid)
                ->where('uuid', $uuid)
                ->first();

            if (!$configuration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Configuration not found'
                ], 404);
            }

            $configuration->update([
                'is_active' => !$configuration->is_active,
                'updated_by' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'data' => $configuration->fresh(['statutoryDeductionTemplate']),
                'message' => 'Configuration status updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Preview calculation for a configuration.
     */
    public function previewCalculation(Request $request, string $uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'employee_uuid' => 'required|exists:employees,uuid',
            'gross_salary' => 'required|numeric|min:0',
            'calculation_date' => 'sometimes|date'
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
            $configuration = CompanyStatutoryDeductionConfiguration::with(['statutoryDeductionTemplate'])
                ->forCompany($company->uuid)
                ->where('uuid', $uuid)
                ->first();

            if (!$configuration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Configuration not found'
                ], 404);
            }

            $employee = Employee::where('uuid', $request->employee_uuid)
                ->where('company_uuid', $company->uuid)
                ->first();

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found or does not belong to your company'
                ], 404);
            }

            $calculationDate = $request->filled('calculation_date') 
                ? Carbon::parse($request->calculation_date) 
                : now();

            $calculation = $configuration->calculateDeduction(
                $request->gross_salary,
                $employee->pay_frequency
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'configuration' => $configuration,
                    'employee' => $employee,
                    'calculation' => $calculation,
                    'gross_salary_used' => $request->gross_salary,
                    'calculation_date' => $calculationDate
                ],
                'message' => 'Calculation preview generated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate preview: ' . $e->getMessage()
            ], 500);
        }
    }
}