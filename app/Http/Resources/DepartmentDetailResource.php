<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentDetailResource extends JsonResource
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
            'cost_center' => $this->cost_center,
            'is_active' => $this->is_active,
            'budget_info' => $this->budget_info,
            
            // Head of department
            'head_of_department' => $this->whenLoaded('headOfDepartment', function () {
                return $this->headOfDepartment ? [
                    'uuid' => $this->headOfDepartment->uuid,
                    'employee_id' => $this->headOfDepartment->employee_id,
                    'name' => $this->headOfDepartment->display_name,
                    'email' => $this->headOfDepartment->email,
                    'phone' => $this->headOfDepartment->phone,
                    'position' => $this->headOfDepartment->position?->name,
                    'hire_date' => $this->headOfDepartment->hire_date?->format('Y-m-d'),
                ] : null;
            }),
            
            // Department statistics
            'statistics' => $this->whenLoaded('employees', function () {
                $employees = $this->employees;
                $activeEmployees = $employees->where('status', 'active');
                
                return [
                    'total_employees' => $employees->count(),
                    'active_employees' => $activeEmployees->count(),
                    'inactive_employees' => $employees->where('status', '!=', 'active')->count(),
                    'average_salary' => $activeEmployees->count() > 0 ? $activeEmployees->avg('salary') : null,
                    'total_payroll' => $activeEmployees->sum('salary'),
                    'directors_count' => $activeEmployees->where('is_director', true)->count(),
                    'contractors_count' => $activeEmployees->where('is_independent_contractor', true)->count(),
                ];
            }),
            
            // Employees in this department
            'employees' => $this->whenLoaded('employees', function () {
                return $this->employees->map(function ($employee) {
                    return [
                        'uuid' => $employee->uuid,
                        'employee_id' => $employee->employee_id,
                        'name' => $employee->display_name,
                        'email' => $employee->email,
                        'phone' => $employee->phone,
                        'status' => $employee->status,
                        'employment_type' => $employee->employment_type,
                        'hire_date' => $employee->hire_date?->format('Y-m-d'),
                        'salary' => $employee->salary,
                        'position' => $employee->position?->name,
                        'is_director' => $employee->is_director,
                        'is_independent_contractor' => $employee->is_independent_contractor,
                        'manager' => $employee->manager ? [
                            'uuid' => $employee->manager->uuid,
                            'name' => $employee->manager->display_name,
                            'employee_id' => $employee->manager->employee_id,
                        ] : null,
                    ];
                });
            }),
            
            // Positions used in this department
            'positions' => $this->whenLoaded('employees.position', function () {
                $positions = $this->employees->pluck('position')->filter()->unique('id');
                return $positions->map(function ($position) {
                    return [
                        'id' => $position->id,
                        'name' => $position->name,
                        'employees_count' => $this->employees->where('position_uuid', $position->id)->count(),
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