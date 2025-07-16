<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use App\Models\Employee;
use App\Models\Company;
use App\Models\EmployeePayrollItem;
use App\Services\Payroll\PayrollCalculationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PayrollController extends Controller
{
    private PayrollCalculationService $payrollService;

    public function __construct(PayrollCalculationService $payrollService)
    {
        $this->payrollService = $payrollService;
    }

    /**
     * Display a listing of payrolls.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Payroll::with(['company'])
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->filled('period_start') && $request->filled('period_end')) {
            $query->whereBetween('payroll_period_start', [
                $request->period_start,
                $request->period_end
            ]);
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $payrolls = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $payrolls,
            'message' => 'Payrolls retrieved successfully'
        ]);
    }

    /**
     * Store a newly created payroll.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'payroll_period_start' => 'required|date',
            'payroll_period_end' => 'required|date|after_or_equal:payroll_period_start',
            'employee_uuids' => 'required|array|min:1',
            'employee_uuids.*' => 'exists:employees,uuid',
            'options' => 'sometimes|array'
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

            // Get employees
            $employees = Employee::whereIn('uuid', $request->employee_uuids)
                ->where('company_uuid', $company->uuid)
                ->get();

            if ($employees->count() !== count($request->employee_uuids)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Some employees not found or do not belong to your company'
                ], 404);
            }

            $periodStart = Carbon::parse($request->payroll_period_start);
            $periodEnd = Carbon::parse($request->payroll_period_end);

            // Calculate payroll for all employees
            $calculations = $this->payrollService->calculateBatchPayroll(
                $employees,
                $periodStart,
                $periodEnd,
                $request->get('options', [])
            );

            // Create payroll records
            $payroll = $this->payrollService->createPayrollRecords(
                $company,
                $calculations,
                $periodStart,
                $periodEnd,
                'draft'
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'payroll' => $payroll->load(['company', 'payrollItems']),
                    'summary' => $calculations['summary']
                ],
                'message' => 'Payroll created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create payroll: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified payroll.
     */
    public function show(string $uuid): JsonResponse
    {
        $payroll = Payroll::with(['company', 'payrollItems.employee'])
            ->where('uuid', $uuid)
            ->first();

        if (!$payroll) {
            return response()->json([
                'success' => false,
                'message' => 'Payroll not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $payroll,
            'message' => 'Payroll retrieved successfully'
        ]);
    }

    /**
     * Update the specified payroll.
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $payroll = Payroll::where('uuid', $uuid)->first();

        if (!$payroll) {
            return response()->json([
                'success' => false,
                'message' => 'Payroll not found'
            ], 404);
        }

        if ($payroll->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Only draft payrolls can be updated'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'notes' => 'sometimes|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $payroll->update($request->only(['notes']));

            return response()->json([
                'success' => true,
                'data' => $payroll->fresh(['company', 'payrollItems']),
                'message' => 'Payroll updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update payroll: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified payroll.
     */
    public function destroy(string $uuid): JsonResponse
    {
        $payroll = Payroll::where('uuid', $uuid)->first();

        if (!$payroll) {
            return response()->json([
                'success' => false,
                'message' => 'Payroll not found'
            ], 404);
        }

        if ($payroll->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Only draft payrolls can be deleted'
            ], 422);
        }

        try {
            $payroll->delete();

            return response()->json([
                'success' => true,
                'message' => 'Payroll deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete payroll: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve a payroll.
     */
    public function approve(string $uuid): JsonResponse
    {
        $payroll = Payroll::where('uuid', $uuid)->first();

        if (!$payroll) {
            return response()->json([
                'success' => false,
                'message' => 'Payroll not found'
            ], 404);
        }

        try {
            $approved = $this->payrollService->approvePayroll($payroll, Auth::id());

            if (!$approved) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payroll cannot be approved. Only draft payrolls can be approved.'
                ], 422);
            }

            return response()->json([
                'success' => true,
                'data' => $payroll->fresh(['company', 'payrollItems']),
                'message' => 'Payroll approved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve payroll: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process an approved payroll.
     */
    public function process(string $uuid): JsonResponse
    {
        $payroll = Payroll::where('uuid', $uuid)->first();

        if (!$payroll) {
            return response()->json([
                'success' => false,
                'message' => 'Payroll not found'
            ], 404);
        }

        try {
            $processed = $this->payrollService->processPayroll($payroll);

            if (!$processed) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payroll cannot be processed. Only approved payrolls can be processed.'
                ], 422);
            }

            return response()->json([
                'success' => true,
                'data' => $payroll->fresh(['company', 'payrollItems']),
                'message' => 'Payroll processed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process payroll: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Preview payroll calculation for employees.
     */
    public function preview(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'payroll_period_start' => 'required|date',
            'payroll_period_end' => 'required|date|after_or_equal:payroll_period_start',
            'employee_uuids' => 'required|array|min:1',
            'employee_uuids.*' => 'exists:employees,uuid'
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

            // Get employees
            $employees = Employee::whereIn('uuid', $request->employee_uuids)
                ->where('company_uuid', $company->uuid)
                ->get();

            if ($employees->count() !== count($request->employee_uuids)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Some employees not found or do not belong to your company'
                ], 404);
            }

            $periodStart = Carbon::parse($request->payroll_period_start);
            $periodEnd = Carbon::parse($request->payroll_period_end);

            // Calculate payroll preview
            $calculations = $this->payrollService->calculateBatchPayroll(
                $employees,
                $periodStart,
                $periodEnd
            );

            return response()->json([
                'success' => true,
                'data' => $calculations,
                'message' => 'Payroll preview calculated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate payroll preview: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payroll statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $company = Auth::user()->company;
            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'User must belong to a company'
                ], 403);
            }

            $query = Payroll::where('company_uuid', $company->uuid);

            // Filter by date range if provided
            if ($request->filled('period_start') && $request->filled('period_end')) {
                $query->whereBetween('payroll_period_start', [
                    $request->period_start,
                    $request->period_end
                ]);
            } else {
                // Default to current year
                $query->whereYear('payroll_period_start', now()->year);
            }

            $stats = [
                'total_payrolls' => $query->count(),
                'draft_payrolls' => $query->where('status', 'draft')->count(),
                'approved_payrolls' => $query->where('status', 'approved')->count(),
                'processed_payrolls' => $query->where('status', 'processed')->count(),
                'total_gross_salary' => $query->sum('total_gross_salary'),
                'total_net_salary' => $query->sum('total_net_salary'),
                'total_deductions' => $query->sum('total_deductions'),
                'total_employer_contributions' => $query->sum('total_employer_contributions'),
                'average_employees_per_payroll' => $query->avg('total_employees')
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Payroll statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate statutory deductions for an employee
     */
    public function calculateStatutoryDeductions(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'employee_uuid' => 'required|string|exists:employees,uuid',
            'gross_salary' => 'required|numeric|min:0',
            'payroll_date' => 'nullable|date'
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
            $employee = Employee::where('uuid', $request->employee_uuid)
                ->where('company_uuid', $company->uuid)
                ->first();

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found'
                ], 404);
            }

            // Get country and current tax jurisdiction
            $country = $employee->company->country;
    
            $taxJurisdiction = $country?->getCurrentTaxJurisdiction();

            if (!$taxJurisdiction) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active tax jurisdiction found for this employee'
                ], 400);
            }

            // Calculate statutory deductions using the StatutoryDeductionCalculator
            $calculator = new \App\Services\Payroll\StatutoryDeductionCalculator();
            $payrollDate = $request->payroll_date ? Carbon::parse($request->payroll_date) : now();
            $deductions = $calculator->calculateForEmployee($employee, $request->gross_salary, $payrollDate);

            return response()->json([
                'success' => true,
                'data' => [
                    'employee_uuid' => $employee->uuid,
                    'gross_salary' => $request->gross_salary,
                    'payroll_date' => $payrollDate->format('Y-m-d'),
                    'tax_jurisdiction' => $taxJurisdiction->name,
                    'deductions' => $deductions
                ],
                'message' => 'Statutory deductions calculated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate statutory deductions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate payslip for an employee
     */
    public function generatePayslip(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'employee_uuid' => 'required|string|exists:employees,uuid',
            'payroll_items' => 'required|array',
            'payroll_items.*.uuid' => 'required|string|exists:employee_payroll_items,uuid',
            'statutory_deductions' => 'sometimes|array'
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
            $employee = Employee::where('uuid', $request->employee_uuid)
                ->where('company_uuid', $company->uuid)
                ->first();

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found'
                ], 404);
            }

            // Load payroll items
            $payrollItemUuids = collect($request->payroll_items)->pluck('uuid');
            $payrollItems = EmployeePayrollItem::whereIn('uuid', $payrollItemUuids)
                ->where('employee_uuid', $employee->uuid)
                ->where('status', 'active')
                ->get();

            // Calculate basic salary and allowances
            $basicSalary = $employee->salary;
            $allowances = $payrollItems->where('type', 'allowance');
            $deductions = $payrollItems->where('type', 'deduction');

            $totalAllowances = $allowances->sum('calculated_amount');
            $grossSalary = $basicSalary + $totalAllowances;

            // Calculate statutory deductions
            $statutoryDeductions = $request->statutory_deductions ?? [];
            $totalStatutoryDeductions = collect($statutoryDeductions)->sum('amount');

            $totalOtherDeductions = $deductions->sum('calculated_amount');
            $totalDeductions = $totalStatutoryDeductions + $totalOtherDeductions;

            $netSalary = $grossSalary - $totalDeductions;

            $payslipData = [
                'employee' => [
                    'uuid' => $employee->uuid,
                    'name' => $employee->display_name,
                    'employee_id' => $employee->employee_id,
                    'position' => $employee->position?->name,
                    'department' => $employee->department?->name
                ],
                'period' => [
                    'month' => now()->format('F Y'),
                    'generated_at' => now()
                ],
                'earnings' => [
                    'basic_salary' => $basicSalary,
                    'allowances' => $allowances->map(function ($item) {
                        return [
                            'name' => $item->name,
                            'amount' => $item->calculated_amount
                        ];
                    }),
                    'total_allowances' => $totalAllowances,
                    'gross_salary' => $grossSalary
                ],
                'deductions' => [
                    'statutory' => $statutoryDeductions,
                    'other' => $deductions->map(function ($item) {
                        return [
                            'name' => $item->name,
                            'amount' => $item->calculated_amount
                        ];
                    }),
                    'total_statutory' => $totalStatutoryDeductions,
                    'total_other' => $totalOtherDeductions,
                    'total_deductions' => $totalDeductions
                ],
                'summary' => [
                    'gross_salary' => $grossSalary,
                    'total_deductions' => $totalDeductions,
                    'net_salary' => $netSalary
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $payslipData,
                'message' => 'Payslip generated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate payslip: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get comprehensive payroll data for an employee (company templates + employee items + statutory).
     */
    public function getEmployeePayrollData(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'employee_uuid' => 'required|string|exists:employees,uuid',
            'payroll_date' => 'sometimes|date',
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
            $company = Auth::user()->company;
            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'User must belong to a company'
                ], 403);
            }

            // Get employee and verify company access
            $employee = Employee::where('uuid', $request->employee_uuid)
                ->where('company_uuid', $company->uuid)
                ->first();

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found or does not belong to your company'
                ], 404);
            }

            $payrollDate = $request->payroll_date ? Carbon::parse($request->payroll_date) : now();
            $grossSalary = $request->gross_salary ?? $employee->salary;

            // Use PayrollCalculationService to get all payroll data
            $payrollService = new \App\Services\Payroll\PayrollCalculationService(
                new \App\Services\Payroll\StatutoryDeductionCalculator()
            );

            // Calculate comprehensive payroll data
            $payrollData = $payrollService->calculateEmployeePayroll(
                $employee,
                $payrollDate->startOfMonth(),
                $payrollDate->endOfMonth()
            );



            // Transform the data to match frontend expectations
            $transformedData = [
                'employee_uuid' => $employee->uuid,
                'payroll_date' => $payrollDate->format('Y-m-d'),
                'gross_salary' => $grossSalary,
                'payroll_items' => $this->transformPayrollItems($payrollData['payroll_items']),
                'statutory_deductions' => $payrollData['payroll_items']['deductions']['statutory'] ?? [],
                'summary' => [
                    'basic_salary' => $payrollData['basic_salary'],
                    'gross_salary' => $payrollData['gross_salary'],
                    'total_allowances' => $payrollData['total_allowances'],
                    'total_deductions' => $payrollData['total_deductions'],
                    'total_statutory_deductions' => $payrollData['total_statutory_deductions'],
                    'total_garnishments' => $payrollData['total_garnishments'] ?? 0,
                    'total_employer_contributions' => $payrollData['total_employer_contributions'],
                    'disposable_income' => $payrollData['disposable_income'] ?? 0,
                    'net_salary' => $payrollData['net_salary']
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $transformedData,
                'message' => 'Employee payroll data retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve employee payroll data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Transform payroll items for frontend consumption.
     */
    private function transformPayrollItems(array $payrollItems): array
    {
        $transformed = [];
        Log::info('Transforming payroll items', ['payrollItems' => $payrollItems]);

        // Company allowances
        foreach ($payrollItems['allowances']['company'] ?? [] as $item) {
            $transformed[] = [
                'uuid' => $item['template_uuid'],
                'code' => $item['code'] ?? '',
                'name' => $item['name'] ?? '',
                'type' => $item['type'] ?? 'allowance',
                'amount' => $item['amount'] ?? 0,
                'calculated_amount' => $item['amount'] ?? 0,
                'source' => 'company_template',
                'status' => 'active'
            ];
        }

        // Employee allowances
        foreach ($payrollItems['allowances']['employee'] ?? [] as $item) {
            $transformed[] = [
                'uuid' => $item['item_uuid'] ?? '',
                'code' => $item['code'] ?? '',
                'name' => $item['name'] ?? '',
                'type' => $item['type'] ?? 'allowance',
                'amount' => $item['amount'] ?? 0,
                'calculated_amount' => $item['amount'] ?? 0,
                'calculation_method' => $item['calculation_method'],
                'source' => 'employee_specific',
                'status' => 'active'
            ];
        }

        // Company deductions
        foreach ($payrollItems['deductions']['company'] ?? [] as $item) {
            $transformed[] = [
                'uuid' => $item['template_uuid'],
                'code' => $item['code'] ?? '',
                'name' => $item['name'] ?? '',
                'type' => $item['type'] ?? 'deduction',
                'amount' => $item['amount'] ?? 0,
                'calculated_amount' => $item['amount'] ?? 0,
                'source' => 'company_template',
                'status' => 'active'
            ];
        }

        // Employee deductions
        foreach ($payrollItems['deductions']['employee'] ?? [] as $item) {
            $transformed[] = [
                'uuid' => $item['item_uuid'] ?? '',
                'code' => $item['code'] ?? '',
                'name' => $item['name'] ??  '',
                'type' => $item['type'] ?? 'deduction',
                'amount' => $item['amount'] ?? 0,
                'calculated_amount' => $item['amount'] ?? 0,
                'calculation_method' => $item['calculation_method'],
                'source' => 'employee_specific',
                'status' => 'active'
            ];
        }

        // Garnishments
        foreach ($payrollItems['deductions']['garnishments'] ?? [] as $item) {
            $transformed[] = [
                'uuid' => $item['uuid'] ?? '',
                'code' => $item['name'] ?? '', // Using name as code for garnishments
                'name' => $item['name'] ?? '',
                'type' => 'garnishment',
                'amount' => $item['amount'] ?? 0,
                'calculated_amount' => $item['amount'] ?? 0,
                'priority_order' => $item['priority'],
                'court_order_number' => $item['court_order'],
                'garnishment_authority' => $item['authority'],
                'source' => 'garnishment',
                'status' => $item['status']
            ];
        }
        
        // Employer contributions
        foreach ($payrollItems['employer_contributions']['company'] ?? [] as $item) {
            $transformed[] = [
                'uuid' => $item['template_uuid'] ?? $item['item_uuid'] ?? '',
                'code' => $item['code'] ?? '',
                'name' => $item['name'] ?? '',
                'type' => 'employer_contribution',
                'amount' => $item['amount'] ?? 0,
                'calculated_amount' => $item['amount'] ?? 0,
                'source' => isset($item['template_uuid']) ? 'company_template' : 'employee_specific',
                'status' => 'active'
            ];
        }

        return $transformed;
    }
}