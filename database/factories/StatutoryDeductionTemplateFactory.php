<?php

namespace Database\Factories;

use App\Models\StatutoryDeductionTemplate;
use App\Models\TaxJurisdiction;
use Illuminate\Database\Eloquent\Factories\Factory;

class StatutoryDeductionTemplateFactory extends Factory
{
    protected $model = StatutoryDeductionTemplate::class;

    public function definition(): array
    {
        return [
            'jurisdiction_uuid' => TaxJurisdiction::factory(),
            'deduction_type' => $this->faker->randomElement(['income_tax', 'social_security', 'health_insurance', 'pension']),
            'code' => strtoupper($this->faker->unique()->bothify('???##')),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'calculation_method' => $this->faker->randomElement(['percentage', 'progressive_bracket', 'salary_bracket', 'flat_amount']),
            'rules' => [
                'default_rate' => 0.05
            ],
            'minimum_salary' => null,
            'maximum_salary' => null,
            'employer_rate' => 0,
            'employee_rate' => 0.05,
            'effective_from' => now()->subMonth(),
            'effective_to' => null,
            'is_mandatory' => true,
            'is_active' => true
        ];
    }

    public function percentage(): static
    {
        return $this->state(fn (array $attributes) => [
            'calculation_method' => 'percentage',
            'employee_rate' => 0.01,
            'employer_rate' => 0.01,
            'rules' => [
                'employee_rate' => 0.01,
                'employer_rate' => 0.01
            ]
        ]);
    }

    public function progressiveBracket(): static
    {
        return $this->state(fn (array $attributes) => [
            'calculation_method' => 'progressive_bracket',
            'rules' => [
                'brackets' => [
                    ['min' => 0, 'max' => 10000, 'rate' => 0.10],
                    ['min' => 10001, 'max' => 20000, 'rate' => 0.20],
                    ['min' => 20001, 'max' => null, 'rate' => 0.30]
                ]
            ]
        ]);
    }

    public function salaryBracket(): static
    {
        return $this->state(fn (array $attributes) => [
            'calculation_method' => 'salary_bracket',
            'rules' => [
                'brackets' => [
                    ['min' => 0, 'max' => 5999, 'amount' => 150],
                    ['min' => 6000, 'max' => 9999, 'amount' => 300],
                    ['min' => 10000, 'max' => null, 'amount' => 500]
                ]
            ]
        ]);
    }

    public function flatAmount(): static
    {
        return $this->state(fn (array $attributes) => [
            'calculation_method' => 'flat_amount',
            'rules' => [
                'amount' => 500
            ]
        ]);
    }

    public function southAfricanPAYE(): static
    {
        return $this->state(fn (array $attributes) => [
            'deduction_type' => 'income_tax',
            'code' => 'PAYE',
            'name' => 'Pay As You Earn',
            'calculation_method' => 'progressive_bracket',
            'rules' => [
                'brackets' => [
                    ['min' => 0, 'max' => 237100, 'rate' => 0.18],
                    ['min' => 237101, 'max' => 370500, 'rate' => 0.26],
                    ['min' => 370501, 'max' => 512800, 'rate' => 0.31],
                    ['min' => 512801, 'max' => 673000, 'rate' => 0.36],
                    ['min' => 673001, 'max' => 857900, 'rate' => 0.39],
                    ['min' => 857901, 'max' => 1817000, 'rate' => 0.41],
                    ['min' => 1817001, 'max' => null, 'rate' => 0.45]
                ],
                'rebates' => [
                    'primary' => 16425,
                    'age_65' => 9000,
                    'age_75' => 2997
                ]
            ]
        ]);
    }

    public function southAfricanUIF(): static
    {
        return $this->state(fn (array $attributes) => [
            'deduction_type' => 'unemployment_insurance',
            'code' => 'UIF',
            'name' => 'Unemployment Insurance Fund',
            'calculation_method' => 'percentage',
            'employee_rate' => 0.01,
            'employer_rate' => 0.01,
            'maximum_salary' => 17712,
            'rules' => [
                'employee_rate' => 0.01,
                'employer_rate' => 0.01,
                'max_salary' => 17712
            ]
        ]);
    }

    public function kenyanNHIF(): static
    {
        return $this->state(fn (array $attributes) => [
            'deduction_type' => 'health_insurance',
            'code' => 'NHIF',
            'name' => 'National Hospital Insurance Fund',
            'calculation_method' => 'salary_bracket',
            'rules' => [
                'brackets' => [
                    ['min' => 0, 'max' => 5999, 'amount' => 150],
                    ['min' => 6000, 'max' => 7999, 'amount' => 300],
                    ['min' => 8000, 'max' => 11999, 'amount' => 400],
                    ['min' => 12000, 'max' => 14999, 'amount' => 500],
                    ['min' => 15000, 'max' => 19999, 'amount' => 600],
                    ['min' => 20000, 'max' => 24999, 'amount' => 750],
                    ['min' => 25000, 'max' => 29999, 'amount' => 850],
                    ['min' => 30000, 'max' => 34999, 'amount' => 900],
                    ['min' => 35000, 'max' => 39999, 'amount' => 950],
                    ['min' => 40000, 'max' => 44999, 'amount' => 1000],
                    ['min' => 45000, 'max' => 49999, 'amount' => 1100],
                    ['min' => 50000, 'max' => 59999, 'amount' => 1200],
                    ['min' => 60000, 'max' => 69999, 'amount' => 1300],
                    ['min' => 70000, 'max' => 79999, 'amount' => 1400],
                    ['min' => 80000, 'max' => 89999, 'amount' => 1500],
                    ['min' => 90000, 'max' => 99999, 'amount' => 1600],
                    ['min' => 100000, 'max' => null, 'amount' => 1700]
                ]
            ]
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function optional(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_mandatory' => false,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'effective_from' => now()->subYear(),
            'effective_to' => now()->subMonth(),
        ]);
    }

    public function future(): static
    {
        return $this->state(fn (array $attributes) => [
            'effective_from' => now()->addMonth(),
            'effective_to' => null,
        ]);
    }
}