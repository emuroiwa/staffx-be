<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeGarnishment;
use App\Models\User;
use App\Http\Requests\GarnishmentRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class GarnishmentController extends Controller
{
    /**
     * Display a listing of garnishments for an employee.
     */
    public function index(Request $request, string $employeeUuid): JsonResponse
    {
        try {
            $employee = Employee::where('uuid', $employeeUuid)->firstOrFail();
            
            // Check if user can view this employee's garnishments
            if (!$this->canManageEmployee($employee)) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $garnishments = EmployeeGarnishment::forEmployee($employeeUuid)
                ->with('employee', 'approvedBy')
                ->orderBy('priority_order')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($garnishment) {
                    return $garnishment->getSummary();
                });

            return response()->json([
                'success' => true,
                'data' => $garnishments,
                'employee' => [
                    'uuid' => $employee->uuid,
                    'name' => $employee->full_name,
                    'employee_id' => $employee->employee_id
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving garnishments: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created garnishment.
     */
    public function store(Request $request, string $employeeUuid): JsonResponse
    {
        try {
            $employee = Employee::where('uuid', $employeeUuid)->firstOrFail();
            
            // Check permissions
            if (!$this->canManageEmployee($employee)) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $validatedData = $this->validateGarnishmentData($request->all());
            $validatedData['employee_uuid'] = $employeeUuid;
            
            DB::beginTransaction();
            
            $garnishment = EmployeeGarnishment::createGarnishment($validatedData);
            
            // Log the creation
            activity()
                ->performedOn($garnishment)
                ->causedBy(Auth::user())
                ->log('Garnishment created');
            
            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $garnishment->getSummary(),
                'message' => 'Garnishment created successfully'
            ], 201);
        } catch (ValidationException $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
                'message' => 'Validation failed'
            ], 422);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Error creating garnishment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified garnishment.
     */
    public function show(string $garnishmentUuid): JsonResponse
    {
        try {
            $garnishment = EmployeeGarnishment::where('uuid', $garnishmentUuid)->firstOrFail();
            
            // Check permissions
            if (!$this->canManageEmployee($garnishment->employee)) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            return response()->json([
                'success' => true,
                'data' => $garnishment->getSummary()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Garnishment not found'
            ], 404);
        }
    }

    /**
     * Update the specified garnishment.
     */
    public function update(Request $request, string $garnishmentUuid): JsonResponse
    {
        try {
            $garnishment = EmployeeGarnishment::where('uuid', $garnishmentUuid)->firstOrFail();
            
            // Check permissions
            if (!$this->canManageEmployee($garnishment->employee)) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $validatedData = $this->validateGarnishmentData($request->all(), $garnishment);
            
            DB::beginTransaction();
            
            $oldValues = $garnishment->toArray();
            $garnishment->update($validatedData);
            
            // Log the update
            activity()
                ->performedOn($garnishment)
                ->causedBy(Auth::user())
                ->withProperties([
                    'old' => $oldValues,
                    'new' => $garnishment->fresh()->toArray()
                ])
                ->log('Garnishment updated');
            
            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $garnishment->getSummary(),
                'message' => 'Garnishment updated successfully'
            ]);
        } catch (ValidationException $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
                'message' => 'Validation failed'
            ], 422);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Error updating garnishment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified garnishment.
     */
    public function destroy(string $garnishmentUuid): JsonResponse
    {
        try {
            $garnishment = EmployeeGarnishment::where('uuid', $garnishmentUuid)->firstOrFail();
            
            // Check permissions
            if (!$this->canManageEmployee($garnishment->employee)) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            DB::beginTransaction();
            
            // Log the deletion
            activity()
                ->performedOn($garnishment)
                ->causedBy(Auth::user())
                ->log('Garnishment deleted');
            
            $garnishment->delete();
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Garnishment deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Error deleting garnishment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get garnishment types and calculation methods.
     */
    public function getOptions(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'garnishment_types' => EmployeeGarnishment::getGarnishmentTypes(),
                'calculation_methods' => EmployeeGarnishment::getCalculationMethods(),
                'statuses' => [
                    'pending_approval' => 'Pending Approval',
                    'active' => 'Active',
                    'suspended' => 'Suspended',
                    'cancelled' => 'Cancelled',
                    'completed' => 'Completed'
                ]
            ]
        ]);
    }

    /**
     * Calculate garnishment preview.
     */
    public function calculatePreview(Request $request, string $employeeUuid): JsonResponse
    {
        try {
            $employee = Employee::where('uuid', $employeeUuid)->firstOrFail();
            
            $data = $request->validate([
                'calculation_method' => 'required|in:fixed_amount,percentage_of_salary,percentage_of_basic,formula,manual',
                'amount' => 'nullable|numeric|min:0',
                'percentage' => 'nullable|numeric|min:0|max:100',
                'formula_expression' => 'nullable|string',
                'garnishment_type' => 'required|in:wage_garnishment,child_support,tax_levy,student_loan,bankruptcy,other',
                'maximum_percentage' => 'nullable|numeric|min:0|max:100'
            ]);

            // Create temporary garnishment for calculation
            $tempGarnishment = new EmployeeGarnishment();
            $tempGarnishment->fill($data);
            $tempGarnishment->employee_uuid = $employeeUuid;
            $tempGarnishment->type = 'garnishment';
            $tempGarnishment->employee = $employee;

            // Use a sample disposable income for preview
            $sampleDisposableIncome = $employee->salary * 0.75; // Assuming 75% of salary as disposable income
            
            $calculatedAmount = $tempGarnishment->calculateGarnishmentAmount($sampleDisposableIncome);
            $maxAllowable = $tempGarnishment->calculateMaxAllowableGarnishment($sampleDisposableIncome);

            return response()->json([
                'success' => true,
                'data' => [
                    'calculated_amount' => $calculatedAmount,
                    'maximum_allowable' => $maxAllowable,
                    'sample_disposable_income' => $sampleDisposableIncome,
                    'percentage_of_disposable' => $sampleDisposableIncome > 0 ? ($calculatedAmount / $sampleDisposableIncome) * 100 : 0
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error calculating preview: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get garnishment history for an employee.
     */
    public function history(string $employeeUuid): JsonResponse
    {
        try {
            $employee = Employee::where('uuid', $employeeUuid)->firstOrFail();
            
            // Check permissions
            if (!$this->canManageEmployee($employee)) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $garnishments = EmployeeGarnishment::forEmployee($employeeUuid)
                ->withTrashed()
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($garnishment) {
                    return $garnishment->getSummary();
                });

            return response()->json([
                'success' => true,
                'data' => $garnishments
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving garnishment history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate garnishment data.
     */
    private function validateGarnishmentData(array $data, EmployeeGarnishment $garnishment = null): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'garnishment_type' => 'required|in:wage_garnishment,child_support,tax_levy,student_loan,bankruptcy,other',
            'calculation_method' => 'required|in:fixed_amount,percentage_of_salary,percentage_of_basic,formula,manual',
            'amount' => 'nullable|numeric|min:0',
            'percentage' => 'nullable|numeric|min:0|max:100',
            'formula_expression' => 'nullable|string',
            'court_order_number' => 'nullable|string|max:255',
            'garnishment_authority' => 'nullable|string|max:255',
            'maximum_percentage' => 'nullable|numeric|min:0|max:100',
            'priority_order' => 'nullable|integer|min:1',
            'contact_information' => 'nullable|array',
            'legal_reference' => 'nullable|string',
            'garnishment_start_date' => 'nullable|date',
            'garnishment_end_date' => 'nullable|date|after_or_equal:garnishment_start_date',
            'total_amount_to_garnish' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string'
        ];

        // Conditional validation based on calculation method
        if (in_array($data['calculation_method'] ?? '', ['fixed_amount', 'manual'])) {
            $rules['amount'] = 'required|numeric|min:0';
        }

        if (in_array($data['calculation_method'] ?? '', ['percentage_of_salary', 'percentage_of_basic'])) {
            $rules['percentage'] = 'required|numeric|min:0|max:100';
        }

        if (($data['calculation_method'] ?? '') === 'formula') {
            $rules['formula_expression'] = 'required|string';
        }

        return request()->validate($rules);
    }

    /**
     * Check if user can manage employee.
     */
    private function canManageEmployee(Employee $employee): bool
    {
        $user = Auth::user();
        
        // Check if user is in the same company
        if ($user->company_uuid !== $employee->company_uuid) {
            return false;
        }

        // Check if user has payroll management permissions
        return $user->hasRole(['admin', 'hr']) || $user->hasPermission('manage_garnishments');
    }
}