<?php

namespace Database\Factories;

use App\Models\PayrollItemCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class PayrollItemCategoryFactory extends Factory
{
    protected $model = PayrollItemCategory::class;

    public function definition(): array
    {
        $types = ['allowance', 'deduction', 'benefit', 'statutory'];
        $type = $this->faker->randomElement($types);
        
        return [
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'type' => $type,
            'code' => strtoupper($this->faker->unique()->bothify('???##')),
            'is_active' => true
        ];
    }

    public function allowance(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'allowance',
            'name' => 'Transport Allowance',
            'code' => 'ALLOWANCE'
        ]);
    }

    public function deduction(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'deduction',
            'name' => 'Medical Aid Deduction',
            'code' => 'DEDUCTION'
        ]);
    }

    public function benefit(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'benefit',
            'name' => 'Medical Benefits',
            'code' => 'BENEFIT'
        ]);
    }

    public function statutory(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'statutory',
            'name' => 'Statutory Deductions',
            'code' => 'STATUTORY'
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
