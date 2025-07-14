<?php

namespace Database\Factories;

use App\Models\CompanyPayrollTemplate;
use App\Models\Company;
use App\Models\PayrollItemCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyPayrollTemplateFactory extends Factory
{
    protected $model = CompanyPayrollTemplate::class;

    public function definition(): array
    {
        return [
            'company_uuid' => Company::factory(),
            'category_uuid' => PayrollItemCategory::factory(),
            'code' => strtoupper($this->faker->unique()->bothify('???###')),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'type' => $this->faker->randomElement(['allowance', 'deduction']),
            'calculation_method' => $this->faker->randomElement([
                'fixed_amount', 
                'percentage_of_salary', 
                'percentage_of_basic', 
                'formula', 
                'manual'
            ]),
            'amount' => $this->faker->randomFloat(2, 500, 5000),
            'default_amount' => null,
            'default_percentage' => null,
            'formula_expression' => null,
            'minimum_amount' => null,
            'maximum_amount' => null,
            'is_taxable' => $this->faker->boolean(70),
            'is_pensionable' => $this->faker->boolean(60),
            'eligibility_rules' => [],
            'is_active' => true,
            'requires_approval' => $this->faker->boolean(30)
        ];
    }

    public function fixedAmount(): static
    {
        return $this->state(fn (array $attributes) => [
            'calculation_method' => 'fixed_amount',
            'default_amount' => $this->faker->randomFloat(2, 100, 5000),
            'default_percentage' => null,
            'formula_expression' => null
        ]);
    }

    public function percentageOfSalary(): static
    {
        return $this->state(fn (array $attributes) => [
            'calculation_method' => 'percentage_of_salary',
            'default_amount' => null,
            'default_percentage' => $this->faker->randomFloat(2, 1, 15),
            'formula_expression' => null
        ]);
    }

    public function percentageOfBasic(): static
    {
        return $this->state(fn (array $attributes) => [
            'calculation_method' => 'percentage_of_basic',
            'default_amount' => null,
            'default_percentage' => $this->faker->randomFloat(2, 5, 25),
            'formula_expression' => null
        ]);
    }

    public function formula(): static
    {
        return $this->state(fn (array $attributes) => [
            'calculation_method' => 'formula',
            'default_amount' => null,
            'default_percentage' => null,
            'formula_expression' => '{basic_salary} * 0.10 + {years_of_service} * 100'
        ]);
    }

    public function manual(): static
    {
        return $this->state(fn (array $attributes) => [
            'calculation_method' => 'manual',
            'default_amount' => null,
            'default_percentage' => null,
            'formula_expression' => null,
            'requires_approval' => true
        ]);
    }

    public function transportAllowance(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'TRANSPORT',
            'name' => 'Transport Allowance',
            'description' => 'Monthly transport allowance for employees',
            'calculation_method' => 'fixed_amount',
            'default_amount' => 3000,
            'is_taxable' => false,
            'is_pensionable' => false
        ]);
    }

    public function housingAllowance(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'HOUSING',
            'name' => 'Housing Allowance',
            'description' => 'Housing allowance based on basic salary',
            'calculation_method' => 'percentage_of_basic',
            'default_percentage' => 25.00,
            'is_taxable' => true,
            'is_pensionable' => true
        ]);
    }

    public function performanceBonus(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'PERF_BONUS',
            'name' => 'Performance Bonus',
            'description' => 'Quarterly performance-based bonus',
            'calculation_method' => 'manual',
            'requires_approval' => true,
            'is_taxable' => true,
            'is_pensionable' => false
        ]);
    }

    public function withEligibilityRules(): static
    {
        return $this->state(fn (array $attributes) => [
            'eligibility_rules' => [
                'departments' => [$this->faker->uuid()],
                'positions' => [$this->faker->uuid()],
                'employment_types' => ['permanent', 'contract'],
                'min_salary' => 15000,
                'max_salary' => 100000
            ]
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function taxExempt(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_taxable' => false,
        ]);
    }

    public function pensionExempt(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_pensionable' => false,
        ]);
    }

    public function allowance(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'allowance',
            'calculation_method' => 'fixed_amount',
            'amount' => $this->faker->randomFloat(2, 1000, 5000),
        ]);
    }

    public function deduction(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'deduction',
            'calculation_method' => 'fixed_amount',
            'amount' => $this->faker->randomFloat(2, 500, 2000),
        ]);
    }
}