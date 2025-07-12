<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PositionDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'min_salary' => $this->min_salary,
            'max_salary' => $this->max_salary,
            'salary_range' => $this->salary_range,
            'formatted_salary_range' => $this->formatted_salary_range,
            'currency' => $this->currency,
            'is_active' => $this->is_active,
            'requirements' => $this->requirements,
            
            // Statistics
            'employees_count' => $this->whenLoaded('employees', function () {
                return $this->employees->count();
            }),
            'active_employees_count' => $this->whenLoaded('employees', function () {
                return $this->employees->where('status', 'active')->count();
            }),
            'average_salary' => $this->whenLoaded('employees', function () {
                $activeEmployees = $this->employees->where('status', 'active');
                return $activeEmployees->count() > 0 ? $activeEmployees->avg('salary') : null;
            }),
            
            // Employees in this position
            'employees' => $this->whenLoaded('employees', function () {
                return $this->employees->map(function ($employee) {
                    return [
                        'uuid' => $employee->uuid,
                        'employee_id' => $employee->employee_id,
                        'name' => $employee->display_name,
                        'email' => $employee->email,
                        'status' => $employee->status,
                        'hire_date' => $employee->hire_date?->format('Y-m-d'),
                        'salary' => $employee->salary,
                        'department' => $employee->department?->name,
                    ];
                });
            }),
            
            // Company relationship
            'company' => $this->whenLoaded('company', function () {
                return [
                    'uuid' => $this->company->uuid,
                    'name' => $this->company->name,
                    'slug' => $this->company->slug,
                ];
            }),
            
            // Timestamps
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}