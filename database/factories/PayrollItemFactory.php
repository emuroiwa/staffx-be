<?php

namespace Database\Factories;

use App\Models\Payroll;
use App\Models\Employee;
use App\Models\PayrollItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class PayrollItemFactory extends Factory
{
    protected $model = PayrollItem::class;

    public function definition(): array
    {
        return [
            'payroll_uuid' => Payroll::factory(),
            'employee_uuid' => Employee::factory(),
            'code' => strtoupper($this->faker->bothify('???##')),
            'name' => $this->faker->words(2, true),
            'type' => $this->faker->randomElement(['allowance', 'deduction', 'income_tax']),
            'amount' => $this->faker->randomFloat(2, 100, 5000),
            'calculation_details' => [
                'method' => 'fixed_amount',
                'rate' => null
            ]
        ];
    }

    public function allowance(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'allowance',
            'code' => 'ALW' . fake()->randomNumber(3),
            'name' => fake()->randomElement(['Transport Allowance', 'Housing Allowance', 'Meal Allowance']),
        ]);
    }

    public function deduction(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'deduction',
            'code' => 'DED' . fake()->randomNumber(3),
            'name' => fake()->randomElement(['Medical Aid', 'Pension Fund', 'Loan Deduction']),
        ]);
    }

    public function statutory(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => fake()->randomElement(['income_tax', 'unemployment_insurance', 'health_insurance']),
            'code' => fake()->randomElement(['PAYE', 'UIF', 'NHIF']),
            'name' => fake()->randomElement(['Pay As You Earn', 'Unemployment Insurance', 'Health Insurance']),
        ]);
    }
}