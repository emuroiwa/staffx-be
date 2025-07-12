<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
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
     */
    public function rules(): array
    {
        $companyUuid = auth()->user()->company_uuid;
        $department = $this->route('department');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'name')
                    ->where('company_uuid', $companyUuid)
                    ->ignore($department->id)
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'cost_center' => ['sometimes', 'nullable', 'string', 'max:50'],
            'head_of_department_id' => [
                'sometimes',
                'nullable',
                'uuid',
                Rule::exists('employees', 'uuid')->where('company_uuid', $companyUuid)->where('status', 'active')
            ],
            'is_active' => ['sometimes', 'boolean'],
            'budget_info' => ['sometimes', 'nullable', 'array'],
            'budget_info.allocation' => ['nullable', 'numeric', 'min:0'],
            'budget_info.currency' => ['nullable', 'string', 'size:3'],
            'budget_info.fiscal_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Department name is required.',
            'name.unique' => 'A department with this name already exists in your company.',
            'head_of_department_id.exists' => 'The selected department head must be an active employee in your company.',
            'budget_info.allocation.numeric' => 'Budget allocation must be a valid number.',
            'budget_info.allocation.min' => 'Budget allocation cannot be negative.',
            'budget_info.currency.size' => 'Currency code must be exactly 3 characters.',
            'budget_info.fiscal_year.integer' => 'Fiscal year must be a valid year.',
            'budget_info.fiscal_year.min' => 'Fiscal year must be 2000 or later.',
            'budget_info.fiscal_year.max' => 'Fiscal year must be 2100 or earlier.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate department head assignment
            if ($this->has('head_of_department_id') && $this->head_of_department_id) {
                $this->validateHeadAssignment($validator);
            }

            // Validate deactivation if changing status
            if ($this->has('is_active') && !$this->is_active) {
                $this->validateDeactivation($validator);
            }
        });
    }

    /**
     * Validate department head assignment.
     */
    private function validateHeadAssignment($validator): void
    {
        $headEmployeeUuid = $this->head_of_department_id;
        $companyUuid = auth()->user()->company_uuid;
        $department = $this->route('department');

        $employee = \App\Models\Employee::where('uuid', $headEmployeeUuid)
            ->where('company_uuid', $companyUuid)
            ->where('status', 'active')
            ->first();

        if (!$employee) {
            $validator->errors()->add('head_of_department_id', 'Selected employee must be an active employee in your company.');
            return;
        }

        // Check if employee is assigned to this department
        if ($employee->department_uuid !== $department->id) {
            $validator->errors()->add('head_of_department_id', 'Department head must be assigned to this department.');
        }
    }

    /**
     * Validate department deactivation.
     */
    private function validateDeactivation($validator): void
    {
        $department = $this->route('department');
        
        $activeEmployeesCount = $department->employees()->where('status', 'active')->count();
        
        if ($activeEmployeesCount > 0) {
            $validator->errors()->add('is_active', "Cannot deactivate department because it has {$activeEmployeesCount} active employee(s). Please reassign these employees first.");
        }
    }
}