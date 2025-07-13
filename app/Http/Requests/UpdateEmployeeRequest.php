<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->canManageEmployees();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $companyUuid = auth()->user()->company_uuid;
        $employee = $this->route('employee');

        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
                Rule::unique('employees', 'email')
                    ->where('company_uuid', $companyUuid)
                    ->ignore($employee->uuid, 'uuid')
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'dob' => ['sometimes', 'nullable', 'date', 'before:today'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            
            // Department and position updates
            'department_uuid' => [
                'sometimes',
                'required',
                'uuid',
                Rule::exists('departments', 'id')->where('company_uuid', $companyUuid)->where('is_active', true)
            ],
            'position_uuid' => [
                'sometimes',
                'required',
                'uuid',
                Rule::exists('positions', 'id')->where('company_uuid', $companyUuid)->where('is_active', true)
            ],
            
            // Manager assignment updates
            'manager_uuid' => [
                'sometimes',
                'nullable',
                'uuid',
                Rule::exists('employees', 'uuid')->where('company_uuid', $companyUuid)->where('status', 'active'),
                'not_in:' . $employee->uuid // Cannot be their own manager
            ],
            
            // User account linkage updates
            'user_uuid' => [
                'sometimes',
                'nullable',
                'uuid',
                Rule::exists('users', 'uuid')->where('company_uuid', $companyUuid)
            ],
            
            'employment_type' => ['sometimes', 'required', Rule::in(['full_time', 'part_time', 'contract', 'intern'])],
            'is_director' => ['sometimes', 'boolean'],
            'is_independent_contractor' => ['sometimes', 'boolean'],
            'is_uif_exempt' => ['sometimes', 'boolean'],
            
            'salary' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'currency_uuid' => [
                'sometimes',
                'nullable',
                'uuid',
                Rule::exists('currencies', 'uuid')->where('is_active', true)
            ],
            'tax_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'pay_frequency' => ['sometimes', 'required', Rule::in(['weekly', 'bi_weekly', 'monthly', 'quarterly', 'annually'])],
            
            'national_id' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                Rule::unique('employees', 'national_id')
                    ->where('company_uuid', $companyUuid)
                    ->ignore($employee->uuid, 'uuid')
            ],
            'passport_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                Rule::unique('employees', 'passport_number')
                    ->where('company_uuid', $companyUuid)
                    ->ignore($employee->uuid, 'uuid')
            ],
            
            'emergency_contact_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            
            'hire_date' => ['sometimes', 'nullable', 'date'],
            'termination_date' => ['sometimes', 'nullable', 'date', 'after:hire_date'],
            'status' => ['sometimes', 'required', Rule::in(['active', 'inactive', 'terminated'])],
            
            'bank_details' => ['sometimes', 'nullable', 'array'],
            'bank_details.bank_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'bank_details.account_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'bank_details.account_type' => ['sometimes', 'nullable', 'string', 'max:50'],
            'bank_details.branch_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            
            'benefits' => ['sometimes', 'nullable', 'array'],
            'documents' => ['sometimes', 'nullable', 'array'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'department_uuid.required' => 'Please select a valid department for the employee.',
            'department_uuid.exists' => 'The selected department does not exist or is not active.',
            'position_uuid.required' => 'Please select a valid position for the employee.',
            'position_uuid.exists' => 'The selected position does not exist or is not active.',
            'manager_uuid.exists' => 'The selected manager does not exist or is not active in your company.',
            'manager_uuid.not_in' => 'An employee cannot be their own manager.',
            'email.unique' => 'This email address is already assigned to another employee in your company.',
            'national_id.unique' => 'This national ID is already assigned to another employee in your company.',
            'passport_number.unique' => 'This passport number is already assigned to another employee in your company.',
            'dob.before' => 'Date of birth must be before today.',
            'termination_date.after' => 'Termination date must be after hire date.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Custom validation for manager assignment to prevent circular reporting
            if ($this->has('manager_uuid') && $this->manager_uuid) {
                $this->validateManagerAssignment($validator);
            }

            // Validate that department and position belong to the same company
            $this->validateCompanyConsistency($validator);

            // Validate termination status and date consistency
            $this->validateTerminationData($validator);
        });
    }

    /**
     * Validate manager assignment for circular reporting.
     */
    private function validateManagerAssignment($validator): void
    {
        $managerUuid = $this->manager_uuid;
        $companyUuid = auth()->user()->company_uuid;
        $employee = $this->route('employee');
        
        // Check if the manager exists and belongs to the same company
        $manager = \App\Models\Employee::withoutGlobalScope('company')
            ->where('uuid', $managerUuid)
            ->where('company_uuid', $companyUuid)
            ->first();
            
        if (!$manager) {
            $validator->errors()->add('manager_uuid', 'The selected manager must belong to your company.');
            return;
        }

        // Check circular reporting
        if ($this->wouldCreateCircularReporting($employee, $manager)) {
            $validator->errors()->add('manager_uuid', 'This manager assignment would create circular reporting.');
        }
    }

    /**
     * Check if assigning this manager would create circular reporting.
     */
    private function wouldCreateCircularReporting($employee, $manager): bool
    {
        $currentManager = $manager;
        $maxDepth = 10; // Prevent infinite loops
        $depth = 0;
        
        while ($currentManager && $depth < $maxDepth) {
            if ($currentManager->uuid === $employee->uuid) {
                return true;
            }
            $currentManager = $currentManager->manager;
            $depth++;
        }
        
        return false;
    }

    /**
     * Validate that department and position belong to the same company.
     */
    private function validateCompanyConsistency($validator): void
    {
        $companyUuid = auth()->user()->company_uuid;
        
        if ($this->has('department_uuid')) {
            $department = \App\Models\Department::withoutGlobalScope('company')
                ->where('id', $this->department_uuid)
                ->where('company_uuid', $companyUuid)
                ->first();
                
            if (!$department) {
                $validator->errors()->add('department_uuid', 'The selected department does not belong to your company.');
            }
        }
        
        if ($this->has('position_uuid')) {
            $position = \App\Models\Position::withoutGlobalScope('company')
                ->where('id', $this->position_uuid)
                ->where('company_uuid', $companyUuid)
                ->first();
                
            if (!$position) {
                $validator->errors()->add('position_uuid', 'The selected position does not belong to your company.');
            }
        }
    }

    /**
     * Validate termination status and date consistency.
     */
    private function validateTerminationData($validator): void
    {
        $employee = $this->route('employee');
        
        // If status is being changed to terminated, ensure termination_date is set
        if ($this->has('status') && $this->status === 'terminated') {
            $terminationDate = $this->termination_date ?? $employee->termination_date;
            
            if (!$terminationDate) {
                $validator->errors()->add('termination_date', 'Termination date is required when employee status is set to terminated.');
            }
        }

        // If termination_date is being set, status should be terminated
        if ($this->has('termination_date') && $this->termination_date) {
            $status = $this->status ?? $employee->status;
            
            if ($status !== 'terminated') {
                $validator->errors()->add('status', 'Employee status must be set to terminated when termination date is provided.');
            }
        }
    }
}