<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PositionResource extends JsonResource
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
            'currency' => $this->currency,
            'is_active' => $this->is_active,
            'employees_count' => $this->whenLoaded('employees', function () {
                return $this->employees->count();
            }),
            'active_employees_count' => $this->whenLoaded('employees', function () {
                return $this->employees->where('status', 'active')->count();
            }),
            
            // Basic requirements preview
            'requirements_summary' => $this->requirements ? [
                'education' => data_get($this->requirements, 'education'),
                'experience' => data_get($this->requirements, 'experience'),
                'skills_count' => is_array(data_get($this->requirements, 'skills')) ? count(data_get($this->requirements, 'skills')) : 0,
                'certifications_count' => is_array(data_get($this->requirements, 'certifications')) ? count(data_get($this->requirements, 'certifications')) : 0,
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