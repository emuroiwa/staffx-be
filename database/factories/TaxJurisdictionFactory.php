<?php

namespace Database\Factories;

use App\Models\TaxJurisdiction;
use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaxJurisdictionFactory extends Factory
{
    protected $model = TaxJurisdiction::class;

    public function definition(): array
    {
        return [
            'country_uuid' => Country::factory(),
            'region_code' => null,
            'name' => $this->faker->country() . ' National',
            'tax_year_start' => '2025-01-01',
            'tax_year_end' => '2025-12-31',
            'regulatory_authority' => $this->faker->company() . ' Revenue Service',
            'effective_from' => now()->subYear(),
            'effective_to' => null,
            'settings' => [],
            'is_active' => true
        ];
    }

    public function current(): static
    {
        return $this->state(fn (array $attributes) => [
            'effective_from' => now()->subMonth(),
            'effective_to' => null,
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