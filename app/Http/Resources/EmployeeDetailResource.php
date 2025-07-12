<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeDetailResource extends JsonResource
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
            'address' => $this->address,
            'dob' => $this->dob?->format('Y-m-d'),
            'start_date' => $this->start_date?->format('Y-m-d'),
            'hire_date' => $this->hire_date?->format('Y-m-d'),
            'termination_date' => $this->termination_date?->format('Y-m-d'),
            'status' => $this->status,
            'employment_type' => $this->employment_type,
            'salary' => $this->salary,
            'formatted_salary' => $this->formatted_salary,
            'currency' => $this->currency,
            'tax_number' => $this->tax_number,
            'pay_frequency' => $this->pay_frequency,
            'national_id' => $this->national_id,
            'passport_number' => $this->passport_number,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'is_director' => $this->is_director,
            'is_independent_contractor' => $this->is_independent_contractor,
            'is_uif_exempt' => $this->is_uif_exempt,
            'age' => $this->age,
            'years_of_service' => $this->years_of_service,
            'is_manager' => $this->isManager(),
            'is_department_head' => $this->isDepartmentHead(),
            'bank_details' => $this->bank_details,
            'benefits' => $this->benefits,
            'documents' => $this->documents,
            'notes' => $this->notes,
            
            // Relationships
            'department' => $this->whenLoaded('department', function () {
                return [
                    'id' => $this->department->id,
                    'name' => $this->department->name,
                    'description' => $this->department->description,
                    'cost_center' => $this->department->cost_center,
                    'is_active' => $this->department->is_active,
                ];
            }),
            
            'position' => $this->whenLoaded('position', function () {
                return [
                    'id' => $this->position->id,
                    'name' => $this->position->name,
                    'description' => $this->position->description,
                    'min_salary' => $this->position->min_salary,
                    'max_salary' => $this->position->max_salary,
                    'salary_range' => $this->position->salary_range,
                    'currency' => $this->position->currency,
                    'requirements' => $this->position->requirements,
                    'is_active' => $this->position->is_active,
                ];
            }),
            
            'manager' => $this->whenLoaded('manager', function () {
                return $this->manager ? [
                    'uuid' => $this->manager->uuid,
                    'name' => $this->manager->display_name,
                    'employee_id' => $this->manager->employee_id,
                    'email' => $this->manager->email,
                    'position' => $this->manager->position?->name,
                ] : null;
            }),

            'direct_reports' => $this->whenLoaded('directReports', function () {
                return $this->directReports->map(function ($report) {
                    return [
                        'uuid' => $report->uuid,
                        'name' => $report->display_name,
                        'employee_id' => $report->employee_id,
                        'position' => $report->position?->name,
                        'email' => $report->email,
                        'status' => $report->status,
                    ];
                });
            }),

            'user' => $this->whenLoaded('user', function () {
                return $this->user ? [
                    'uuid' => $this->user->uuid,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'role' => $this->user->role,
                ] : null;
            }),

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