<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepartmentRequest extends FormRequest
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

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'name')->where('company_uuid', $companyUuid)
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'cost_center' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
            'budget_info' => ['nullable', 'array'],
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
            'budget_info.allocation.numeric' => 'Budget allocation must be a valid number.',
            'budget_info.allocation.min' => 'Budget allocation cannot be negative.',
            'budget_info.currency.size' => 'Currency code must be exactly 3 characters.',
            'budget_info.fiscal_year.integer' => 'Fiscal year must be a valid year.',
            'budget_info.fiscal_year.min' => 'Fiscal year must be 2000 or later.',
            'budget_info.fiscal_year.max' => 'Fiscal year must be 2100 or earlier.',
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

        // Set default budget currency if not provided
        if ($this->has('budget_info') && !isset($this->budget_info['currency'])) {
            $company = auth()->user()->company;
            $budgetInfo = $this->budget_info;
            $budgetInfo['currency'] = $company ? $company->getSetting('default_currency', 'USD') : 'USD';
            
            $this->merge([
                'budget_info' => $budgetInfo,
            ]);
        }
    }
}