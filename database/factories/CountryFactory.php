<?php

namespace Database\Factories;

use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

class CountryFactory extends Factory
{
    protected $model = Country::class;

    public function definition(): array
    {
        return [
            'iso_code' => strtoupper($this->faker->unique()->lexify('??')),
            'name' => $this->faker->country(),
            'currency_code' => strtoupper($this->faker->currencyCode()),
            'timezone' => $this->faker->timezone(),
            'regulatory_framework' => [
                'tax_year_start' => '01-01',
                'tax_year_end' => '12-31',
                'mandatory_deductions' => ['Income Tax', 'Social Security']
            ],
            'is_supported_for_payroll' => $this->faker->boolean(70),
            'is_active' => true
        ];
    }

    public function payrollSupported(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_supported_for_payroll' => true,
        ]);
    }

    public function payrollNotSupported(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_supported_for_payroll' => false,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function southAfrica(): static
    {
        return $this->state(fn (array $attributes) => [
            'iso_code' => 'ZA',
            'name' => 'South Africa',
            'currency_code' => 'ZAR',
            'timezone' => 'Africa/Johannesburg',
            'regulatory_framework' => [
                'tax_year_start' => '03-01',
                'tax_year_end' => '02-28',
                'mandatory_deductions' => ['PAYE', 'UIF', 'Skills Development Levy']
            ],
            'is_supported_for_payroll' => true,
        ]);
    }

    public function nigeria(): static
    {
        return $this->state(fn (array $attributes) => [
            'iso_code' => 'NG',
            'name' => 'Nigeria',
            'currency_code' => 'NGN',
            'timezone' => 'Africa/Lagos',
            'regulatory_framework' => [
                'tax_year_start' => '01-01',
                'tax_year_end' => '12-31',
                'mandatory_deductions' => ['PAYE', 'NHIS', 'Pension']
            ],
            'is_supported_for_payroll' => true,
        ]);
    }

    public function kenya(): static
    {
        return $this->state(fn (array $attributes) => [
            'iso_code' => 'KE',
            'name' => 'Kenya',
            'currency_code' => 'KES',
            'timezone' => 'Africa/Nairobi',
            'regulatory_framework' => [
                'tax_year_start' => '01-01',
                'tax_year_end' => '12-31',
                'mandatory_deductions' => ['PAYE', 'NHIF', 'NSSF']
            ],
            'is_supported_for_payroll' => true,
        ]);
    }
}