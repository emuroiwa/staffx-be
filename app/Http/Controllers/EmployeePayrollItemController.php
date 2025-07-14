<?php

namespace App\Http\Controllers;

use App\Models\EmployeePayrollItem;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class EmployeePayrollItemController extends Controller
{
    /**
     * Display a listing of employee payroll items.
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'employee_uuid' => 'sometimes|exists:employees,uuid',
            'status' => 'sometimes|in:active,pending_approval,suspended,cancelled',
            'type' => 'sometimes|in:allowance,deduction',
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

            $query = EmployeePayrollItem::with(['employee', 'template', 'statutoryTemplate'])
                ->whereHas('employee', function($q) use ($company) {
                    $q->where('company_uuid', $company->uuid);
                })
                ->orderBy('created_at', 'desc');

            // Filter by employee
            if ($request->filled('employee_uuid')) {
                $query->where('employee_uuid', $request->employee_uuid);
            }

            // Filter by status
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            // Filter by type
            if ($request->filled('type')) {
                $query->where('type', $request->type);
            }

            $perPage = $request->get('per_page', 15);
            $items = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $items,
                'message' => 'Employee payroll items retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve employee payroll items: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created employee payroll item.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'employee_uuid' => 'required|exists:employees,uuid',
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'type' => 'required|in:allowance,deduction',
            'calculation_method' => 'required|in:fixed_amount,percentage_of_salary,percentage_of_basic,formula,manual',
            'amount' => 'required_if:calculation_method,fixed_amount,manual|nullable|numeric|min:0',
            'percentage' => 'required_if:calculation_method,percentage_of_salary,percentage_of_basic|nullable|numeric|min:0|max:100',
            'formula_expression' => 'required_if:calculation_method,formula|nullable|string',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after:effective_from',
            'is_recurring' => 'sometimes|boolean',
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

            // Verify employee belongs to company
            $employee = Employee::where('uuid', $request->employee_uuid)
                ->where('company_uuid', $company->uuid)
                ->first();

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found or does not belong to your company'
                ], 404);
            }

            // Check for duplicate code for this employee
            $existingItem = EmployeePayrollItem::where('employee_uuid', $request->employee_uuid)
                ->where('code', $request->code)
                ->whereIn('status', ['active', 'pending_approval'])
                ->first();

            if ($existingItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'An active payroll item with this code already exists for this employee'
                ], 422);
            }

            $status = $request->get('requires_approval', false) ? 'pending_approval' : 'active';

            $item = EmployeePayrollItem::create([
                'employee_uuid' => $request->employee_uuid,
                'code' => $request->code,
                'name' => $request->name,
                'type' => $request->type,
                'calculation_method' => $request->calculation_method,
                'amount' => $request->amount,
                'percentage' => $request->percentage,
                'formula_expression' => $request->formula_expression,
                'effective_from' => Carbon::parse($request->effective_from),
                'effective_to' => $request->effective_to ? Carbon::parse($request->effective_to) : null,
                'is_recurring' => $request->get('is_recurring', true),
                'status' => $status,
                'created_by' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'data' => $item->load(['employee', 'template', 'statutoryTemplate']),
                'message' => 'Employee payroll item created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create employee payroll item: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified employee payroll item.
     */
    public function show(string $uuid): JsonResponse
    {
        $item = EmployeePayrollItem::with(['employee', 'template', 'statutoryTemplate'])
            ->where('uuid', $uuid)
            ->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Employee payroll item not found'
            ], 404);
        }

        // Verify access through company
        $company = Auth::user()->company;
        if (!$company || $item->employee->company_uuid !== $company->uuid) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $item,
            'message' => 'Employee payroll item retrieved successfully'
        ]);
    }

    /**
     * Update the specified employee payroll item.
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $item = EmployeePayrollItem::where('uuid', $uuid)->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Employee payroll item not found'
            ], 404);
        }

        // Verify access through company
        $company = Auth::user()->company;
        if (!$company || $item->employee->company_uuid !== $company->uuid) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        if (!in_array($item->status, ['active', 'pending_approval'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only active or pending approval items can be updated'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'calculation_method' => 'sometimes|in:fixed_amount,percentage_of_salary,percentage_of_basic,formula,manual',
            'amount' => 'sometimes|nullable|numeric|min:0',
            'percentage' => 'sometimes|nullable|numeric|min:0|max:100',
            'formula_expression' => 'sometimes|nullable|string',
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
            $item->update($request->only([
                'name', 'calculation_method', 'amount', 'percentage', 
                'formula_expression', 'effective_to'
            ]));

            return response()->json([
                'success' => true,
                'data' => $item->fresh(['employee', 'template', 'statutoryTemplate']),
                'message' => 'Employee payroll item updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update employee payroll item: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified employee payroll item.
     */
    public function destroy(string $uuid): JsonResponse
    {
        $item = EmployeePayrollItem::where('uuid', $uuid)->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Employee payroll item not found'
            ], 404);
        }

        // Verify access through company
        $company = Auth::user()->company;
        if (!$company || $item->employee->company_uuid !== $company->uuid) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        try {
            $item->delete();

            return response()->json([
                'success' => true,
                'message' => 'Employee payroll item deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete employee payroll item: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve a pending employee payroll item.
     */
    public function approve(Request $request, string $uuid): JsonResponse
    {
        $item = EmployeePayrollItem::where('uuid', $uuid)->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Employee payroll item not found'
            ], 404);
        }

        // Verify access through company
        $company = Auth::user()->company;
        if (!$company || $item->employee->company_uuid !== $company->uuid) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'approval_notes' => 'sometimes|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $approved = $item->approve(Auth::user(), $request->get('approval_notes'));

            if (!$approved) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item cannot be approved. Only pending approval items can be approved.'
                ], 422);
            }

            return response()->json([
                'success' => true,
                'data' => $item->fresh(['employee', 'template', 'statutoryTemplate']),
                'message' => 'Employee payroll item approved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve employee payroll item: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Suspend an active employee payroll item.
     */
    public function suspend(Request $request, string $uuid): JsonResponse
    {
        $item = EmployeePayrollItem::where('uuid', $uuid)->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Employee payroll item not found'
            ], 404);
        }

        // Verify access through company
        $company = Auth::user()->company;
        if (!$company || $item->employee->company_uuid !== $company->uuid) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'suspension_reason' => 'required|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $suspended = $item->suspend(Auth::user(), $request->suspension_reason);

            if (!$suspended) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item cannot be suspended. Only active items can be suspended.'
                ], 422);
            }

            return response()->json([
                'success' => true,
                'data' => $item->fresh(['employee', 'template', 'statutoryTemplate']),
                'message' => 'Employee payroll item suspended successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to suspend employee payroll item: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate amount for employee payroll item preview.
     */
    public function calculatePreview(Request $request, string $uuid): JsonResponse
    {
        $item = EmployeePayrollItem::where('uuid', $uuid)->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Employee payroll item not found'
            ], 404);
        }

        // Verify access through company
        $company = Auth::user()->company;
        if (!$company || $item->employee->company_uuid !== $company->uuid) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'gross_salary' => 'sometimes|numeric|min:0',
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
            $grossSalary = $request->get('gross_salary', $item->employee->salary);
            $calculationDate = $request->filled('calculation_date') 
                ? Carbon::parse($request->calculation_date)
                : now();

            $amount = $item->calculateAmount($grossSalary, $calculationDate);

            return response()->json([
                'success' => true,
                'data' => [
                    'calculated_amount' => $amount,
                    'gross_salary_used' => $grossSalary,
                    'calculation_date' => $calculationDate,
                    'calculation_method' => $item->calculation_method,
                    'is_effective' => $item->isEffectiveForDate($calculationDate)
                ],
                'message' => 'Amount calculated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate amount: ' . $e->getMessage()
            ], 500);
        }
    }
}