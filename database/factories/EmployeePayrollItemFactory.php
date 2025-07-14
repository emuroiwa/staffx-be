<?php

namespace Database\Factories;

use App\Models\EmployeePayrollItem;
use App\Models\Employee;
use App\Models\CompanyPayrollTemplate;
use App\Models\StatutoryDeductionTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeePayrollItemFactory extends Factory
{
    protected $model = EmployeePayrollItem::class;

    public function definition(): array
    {
        return [
            'employee_uuid' => Employee::factory(),
            'template_uuid' => null,
            'statutory_template_uuid' => null,
            'code' => strtoupper($this->faker->unique()->bothify('EPI###')),
            'name' => $this->faker->words(3, true),
            'type' => $this->faker->randomElement(['allowance', 'deduction', 'benefit', 'statutory']),
            'calculation_method' => $this->faker->randomElement([
                'fixed_amount', 
                'percentage_of_salary', 
                'percentage_of_basic', 
                'formula', 
                'manual'
            ]),
            'amount' => null,
            'percentage' => null,
            'formula_expression' => null,
            'effective_from' => now()->startOfMonth(),
            'effective_to' => null,
            'is_recurring' => true,
            'status' => 'active',
            'approved_by' => null,
            'approved_at' => null,
            'notes' => null
        ];
    }

    public function fromTemplate(): static
    {
        return $this->state(fn (array $attributes) => [
            'template_uuid' => CompanyPayrollTemplate::factory(),
            'statutory_template_uuid' => null
        ]);
    }

    public function fromStatutoryTemplate(): static
    {
        return $this->state(fn (array $attributes) => [
            'template_uuid' => null,
            'statutory_template_uuid' => StatutoryDeductionTemplate::factory(),
            'type' => 'statutory'
        ]);
    }

    public function fixedAmount(): static
    {
        return $this->state(fn (array $attributes) => [
            'calculation_method' => 'fixed_amount',
            'amount' => $this->faker->randomFloat(2, 100, 5000),
            'percentage' => null,
            'formula_expression' => null
        ]);
    }

    public function percentageOfSalary(): static
    {
        return $this->state(fn (array $attributes) => [
            'calculation_method' => 'percentage_of_salary',
            'amount' => null,
            'percentage' => $this->faker->randomFloat(2, 1, 15),
            'formula_expression' => null
        ]);
    }

    public function percentageOfBasic(): static
    {
        return $this->state(fn (array $attributes) => [
            'calculation_method' => 'percentage_of_basic',
            'amount' => null,
            'percentage' => $this->faker->randomFloat(2, 5, 25),
            'formula_expression' => null
        ]);
    }

    public function formula(): static
    {
        return $this->state(fn (array $attributes) => [
            'calculation_method' => 'formula',
            'amount' => null,
            'percentage' => null,
            'formula_expression' => '{basic_salary} * 0.10'
        ]);
    }

    public function manual(): static
    {
        return $this->state(fn (array $attributes) => [
            'calculation_method' => 'manual',
            'amount' => $this->faker->randomFloat(2, 100, 2000),
            'percentage' => null,
            'formula_expression' => null
        ]);
    }

    public function allowance(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'allowance',
            'code' => 'TRANSPORT',
            'name' => 'Transport Allowance'
        ]);
    }

    public function deduction(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'deduction',
            'code' => 'MEDICAL',
            'name' => 'Medical Aid Deduction'
        ]);
    }

    public function benefit(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'benefit',
            'code' => 'INSURANCE',
            'name' => 'Life Insurance Benefit'
        ]);
    }

    public function statutory(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'statutory',
            'code' => 'PAYE',
            'name' => 'Pay As You Earn Tax'
        ]);
    }

    public function oneTime(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_recurring' => false,
            'effective_from' => now(),
            'effective_to' => now()->addMonth()
        ]);
    }

    public function recurring(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_recurring' => true,
            'effective_from' => now()->startOfMonth(),
            'effective_to' => null
        ]);
    }

    public function pendingApproval(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending_approval',
            'approved_by' => null,
            'approved_at' => null
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'approved_by' => $this->faker->uuid(),
            'approved_at' => now()
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'suspended'
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'effective_to' => now()->subDay()
        ]);
    }

    public function withNotes(): static
    {
        return $this->state(fn (array $attributes) => [
            'notes' => $this->faker->sentence()
        ]);
    }
}