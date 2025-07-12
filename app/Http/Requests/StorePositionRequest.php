<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePositionRequest extends FormRequest
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
                Rule::unique('positions', 'name')->where('company_uuid', $companyUuid)
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'min_salary' => ['nullable', 'numeric', 'min:0'],
            'max_salary' => ['nullable', 'numeric', 'min:0', 'gte:min_salary'],
            'currency' => ['nullable', 'string', 'size:3'],
            'is_active' => ['boolean'],
            'requirements' => ['nullable', 'array'],
            'requirements.education' => ['nullable', 'string', 'max:500'],
            'requirements.experience' => ['nullable', 'string', 'max:500'],
            'requirements.skills' => ['nullable', 'array'],
            'requirements.skills.*' => ['string', 'max:100'],
            'requirements.certifications' => ['nullable', 'array'],
            'requirements.certifications.*' => ['string', 'max:100'],
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Position name is required.',
            'name.unique' => 'A position with this name already exists in your company.',
            'min_salary.numeric' => 'Minimum salary must be a valid number.',
            'min_salary.min' => 'Minimum salary cannot be negative.',
            'max_salary.numeric' => 'Maximum salary must be a valid number.',
            'max_salary.min' => 'Maximum salary cannot be negative.',
            'max_salary.gte' => 'Maximum salary must be greater than or equal to minimum salary.',
            'currency.size' => 'Currency code must be exactly 3 characters.',
            'requirements.skills.*.string' => 'Each skill must be a text value.',
            'requirements.skills.*.max' => 'Each skill must not exceed 100 characters.',
            'requirements.certifications.*.string' => 'Each certification must be a text value.',
            'requirements.certifications.*.max' => 'Each certification must not exceed 100 characters.',
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

        // Set default currency if not provided
        if (!$this->has('currency')) {
            $company = auth()->user()->company;
            $this->merge([
                'currency' => $company ? $company->getSetting('default_currency', 'USD') : 'USD',
            ]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Additional salary range validation
            $this->validateSalaryRange($validator);
        });
    }

    /**
     * Validate salary range consistency.
     */
    private function validateSalaryRange($validator): void
    {
        $minSalary = $this->min_salary;
        $maxSalary = $this->max_salary;

        if ($minSalary && $maxSalary && $minSalary > $maxSalary) {
            $validator->errors()->add('max_salary', 'Maximum salary must be greater than or equal to minimum salary.');
        }

        // Check for reasonable salary ranges (optional business rule)
        if ($minSalary && $maxSalary) {
            $ratio = $maxSalary / $minSalary;
            if ($ratio > 10) {
                $validator->errors()->add('salary_range', 'The salary range seems unusually wide. Maximum salary should not be more than 10 times the minimum salary.');
            }
        }
    }
}