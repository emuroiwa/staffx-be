<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use App\Models\Employee;
use App\Models\Company;
use App\Services\Payroll\PayrollCalculationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
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
}