<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payroll;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PayrollController extends Controller
{
    /**
     * Display a listing of payroll records for the company.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Payroll::with(['employee']);

            // Apply filters
            if ($request->has('employee_id') && $request->employee_id) {
                $query->where('employee_id', $request->employee_id);
            }

            if ($request->has('pay_period_start') && $request->pay_period_start) {
                $query->where('pay_period_start', '>=', $request->pay_period_start);
            }

            if ($request->has('pay_period_end') && $request->pay_period_end) {
                $query->where('pay_period_end', '<=', $request->pay_period_end);
            }

            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }

            $payrolls = $query->orderBy('pay_period_start', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $payrolls,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payroll records',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created payroll record.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'pay_period_start' => 'required|date',
            'pay_period_end' => 'required|date|after:pay_period_start',
            'gross_pay' => 'required|numeric|min:0',
            'net_pay' => 'required|numeric|min:0',
            'tax_deductions' => 'nullable|numeric|min:0',
            'other_deductions' => 'nullable|numeric|min:0',
            'overtime_hours' => 'nullable|numeric|min:0',
            'overtime_pay' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:draft,processed,paid',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            // Verify employee belongs to the same company
            $employee = Employee::findOrFail($request->employee_id);
            if ($employee->company_id !== auth()->user()->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found in your company',
                ], 404);
            }

            $data = $request->validated();
            $data['company_id'] = auth()->user()->company_id;

            $payroll = Payroll::create($data);
            $payroll->load(['employee']);

            return response()->json([
                'success' => true,
                'message' => 'Payroll record created successfully',
                'data' => $payroll,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create payroll record',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified payroll record.
     */
    public function show(Payroll $payroll): JsonResponse
    {
        try {
            $payroll->load(['employee', 'company']);

            return response()->json([
                'success' => true,
                'data' => $payroll,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payroll record',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified payroll record.
     */
    public function update(Request $request, Payroll $payroll): JsonResponse
    {
        $request->validate([
            'pay_period_start' => 'sometimes|required|date',
            'pay_period_end' => 'sometimes|required|date|after:pay_period_start',
            'gross_pay' => 'sometimes|required|numeric|min:0',
            'net_pay' => 'sometimes|required|numeric|min:0',
            'tax_deductions' => 'sometimes|nullable|numeric|min:0',
            'other_deductions' => 'sometimes|nullable|numeric|min:0',
            'overtime_hours' => 'sometimes|nullable|numeric|min:0',
            'overtime_pay' => 'sometimes|nullable|numeric|min:0',
            'status' => 'sometimes|nullable|in:draft,processed,paid',
            'notes' => 'sometimes|nullable|string|max:1000',
        ]);

        try {
            $payroll->update($request->validated());
            $payroll->load(['employee']);

            return response()->json([
                'success' => true,
                'message' => 'Payroll record updated successfully',
                'data' => $payroll,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update payroll record',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified payroll record.
     */
    public function destroy(Payroll $payroll): JsonResponse
    {
        try {
            $payroll->delete();

            return response()->json([
                'success' => true,
                'message' => 'Payroll record deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete payroll record',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get payroll summary for the company.
     */
    public function summary(Request $request): JsonResponse
    {
        try {
            $query = Payroll::query();

            // Filter by date range if provided
            if ($request->has('start_date') && $request->start_date) {
                $query->where('pay_period_start', '>=', $request->start_date);
            }

            if ($request->has('end_date') && $request->end_date) {
                $query->where('pay_period_end', '<=', $request->end_date);
            }

            $summary = [
                'total_records' => $query->count(),
                'total_gross_pay' => $query->sum('gross_pay'),
                'total_net_pay' => $query->sum('net_pay'),
                'total_tax_deductions' => $query->sum('tax_deductions'),
                'total_other_deductions' => $query->sum('other_deductions'),
                'total_overtime_pay' => $query->sum('overtime_pay'),
                'by_status' => $query->groupBy('status')
                    ->selectRaw('status, count(*) as count, sum(gross_pay) as total_gross')
                    ->get()
                    ->keyBy('status'),
            ];

            return response()->json([
                'success' => true,
                'data' => $summary,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payroll summary',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}