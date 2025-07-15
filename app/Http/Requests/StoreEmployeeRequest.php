<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
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

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('employees', 'email')->where('company_uuid', $companyUuid)
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'dob' => ['nullable', 'date', 'before:today'],
            'start_date' => ['nullable', 'date'],
            
            // Required fields for proper setup
            'department_uuid' => [
                'required',
                'uuid',
                Rule::exists('departments', 'id')->where('company_uuid', $companyUuid)->where('is_active', true)
            ],
            'position_uuid' => [
                'required',
                'uuid',
                Rule::exists('positions', 'id')->where('company_uuid', $companyUuid)->where('is_active', true)
            ],
            
            // Optional manager assignment
            'manager_uuid' => [
                'nullable',
                'uuid',
                Rule::exists('employees', 'uuid')->where('company_uuid', $companyUuid)->where('status', 'active'),
                'different:uuid' // Cannot be their own manager
            ],
            
            // Optional user account linkage
            'user_uuid' => [
                'nullable',
                'uuid',
                Rule::exists('users', 'uuid')->where('company_uuid', $companyUuid)
            ],
            
            'employment_type' => ['required', Rule::in(['full_time', 'part_time', 'contract', 'intern'])],
            'is_director' => ['boolean'],
            'is_independent_contractor' => ['boolean'],
            'is_uif_exempt' => ['boolean'],
            
            'salary' => ['nullable', 'numeric', 'min:0'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'pay_frequency' => ['required', Rule::in(['weekly', 'bi_weekly', 'monthly', 'quarterly', 'annually'])],
            
            'national_id' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('employees', 'national_id')->where('company_uuid', $companyUuid)
            ],
            'passport_number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('employees', 'passport_number')->where('company_uuid', $companyUuid)
            ],
            
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            
            'hire_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['active', 'inactive', 'terminated'])],
            
            'bank_details' => ['nullable', 'array'],
            'bank_details.bank_name' => ['nullable', 'string', 'max:255'],
            'bank_details.account_number' => ['nullable', 'string', 'max:50'],
            'bank_details.account_type' => ['nullable', 'string', 'max:50'],
            'bank_details.branch_code' => ['nullable', 'string', 'max:20'],
            
            'benefits' => ['nullable', 'array'],
            'documents' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:1000'],
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
            'manager_uuid.different' => 'An employee cannot be their own manager.',
            'email.unique' => 'This email address is already assigned to another employee in your company.',
            'national_id.unique' => 'This national ID is already assigned to another employee in your company.',
            'passport_number.unique' => 'This passport number is already assigned to another employee in your company.',
            'dob.before' => 'Date of birth must be before today.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set company UUID from authenticated user
        $this->merge([
            'company_uuid' => auth()->user()->company_uuid,
        ]);
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
        });
    }

    /**
     * Validate manager assignment for circular reporting.
     */
    private function validateManagerAssignment($validator): void
    {
        $managerUuid = $this->manager_uuid;
        $companyUuid = auth()->user()->company_uuid;
        
        // Check if the manager exists and belongs to the same company
        $manager = \App\Models\Employee::withoutGlobalScope('company')
            ->where('uuid', $managerUuid)
            ->where('company_uuid', $companyUuid)
            ->first();
            
        if (!$manager) {
            $validator->errors()->add('manager_uuid', 'The selected manager must belong to your company.');
            return;
        }

        // For updates, check circular reporting
        if ($this->route('employee')) {
            $employee = $this->route('employee');
            if ($this->wouldCreateCircularReporting($employee, $manager)) {
                $validator->errors()->add('manager_uuid', 'This manager assignment would create circular reporting.');
            }
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
}