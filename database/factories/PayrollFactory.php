<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Payroll;
use Illuminate\Database\Eloquent\Factories\Factory;

class PayrollFactory extends Factory
{
    protected $model = Payroll::class;

    public function definition(): array
    {
        return [
            'company_uuid' => Company::factory(),
            'employee_uuid' => Employee::factory(),
            'payroll_period_start' => now()->startOfMonth(),
            'payroll_period_end' => now()->endOfMonth(),
            'total_employees' => 1,
            'total_gross_salary' => 50000,
            'total_net_salary' => 35000,
            'total_deductions' => 15000,
            'total_employer_contributions' => 5000,
            'status' => 'draft',
            'calculated_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'approved_at' => null,
            'approved_by' => null,
            'processed_at' => null,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'approved_at' => now()->subHour(),
            'approved_by' => fake()->uuid(),
            'processed_at' => null,
        ]);
    }

    public function processed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processed',
            'approved_at' => now()->subHours(2),
            'approved_by' => fake()->uuid(),
            'processed_at' => now()->subHour(),
        ]);
    }

    public function multipleEmployees(int $count = 5): static
    {
        return $this->state(fn (array $attributes) => [
            'total_employees' => $count,
            'total_gross_salary' => 50000 * $count,
            'total_net_salary' => 35000 * $count,
            'total_deductions' => 15000 * $count,
            'total_employer_contributions' => 5000 * $count,
        ]);
    }
}