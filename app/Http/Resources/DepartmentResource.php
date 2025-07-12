<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
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
            
            // Head of department
            'head_of_department' => $this->whenLoaded('headOfDepartment', function () {
                return $this->headOfDepartment ? [
                    'uuid' => $this->headOfDepartment->uuid,
                    'name' => $this->headOfDepartment->display_name,
                    'employee_id' => $this->headOfDepartment->employee_id,
                    'email' => $this->headOfDepartment->email,
                ] : null;
            }),
            
            // Employee counts
            'employees_count' => $this->whenLoaded('employees', function () {
                return $this->employees->count();
            }),
            'active_employees_count' => $this->whenLoaded('employees', function () {
                return $this->employees->where('status', 'active')->count();
            }),
            
            // Budget info summary
            'budget_summary' => $this->budget_info ? [
                'allocation' => data_get($this->budget_info, 'allocation'),
                'currency' => data_get($this->budget_info, 'currency'),
                'fiscal_year' => data_get($this->budget_info, 'fiscal_year'),
            ] : null,
            
            // Company relationship
            'company' => $this->whenLoaded('company', function () {
                return [
                    'uuid' => $this->company->uuid,
                    'name' => $this->company->name,
                ];
            }),
            
            // Timestamps
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}