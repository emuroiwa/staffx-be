<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'employee_id' => $this->employee_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'display_name' => $this->display_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'employment_type' => $this->employment_type,
            'hire_date' => $this->hire_date?->format('Y-m-d'),
            'start_date' => $this->start_date?->format('Y-m-d'),
            'salary' => $this->salary,
            'formatted_salary' => $this->formatted_salary,
            'is_director' => $this->is_director,
            'is_independent_contractor' => $this->is_independent_contractor,
            'is_uif_exempt' => $this->is_uif_exempt,
            'age' => $this->age,
            'years_of_service' => $this->years_of_service,
            'is_manager' => $this->isManager(),
            'is_department_head' => $this->isDepartmentHead(),
            
            // Relationships
            'department' => $this->whenLoaded('department', function () {
                return [
                    'id' => $this->department->id,
                    'name' => $this->department->name,
                    'cost_center' => $this->department->cost_center,
                ];
            }),
            
            'position' => $this->whenLoaded('position', function () {
                return [
                    'id' => $this->position->id,
                    'name' => $this->position->name,
                    'salary_range' => $this->position->salary_range,
                ];
            }),
            
            'manager' => $this->whenLoaded('manager', function () {
                return [
                    'uuid' => $this->manager->uuid,
                    'name' => $this->manager->display_name,
                    'employee_id' => $this->manager->employee_id,
                ];
            }),

            'direct_reports_count' => $this->whenLoaded('directReports', function () {
                return $this->directReports->count();
            }),
            
            // Timestamps
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}