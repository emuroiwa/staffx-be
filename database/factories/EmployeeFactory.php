<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'company_uuid' => Company::factory(),
            'employee_id' => $this->faker->unique()->bothify('EMP####'),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'salary' => $this->faker->randomFloat(2, 15000, 100000),
            'employment_type' => $this->faker->randomElement(['permanent', 'contract', 'temporary', 'intern']),
            'department_uuid' => null,
            'position_uuid' => null,
            'manager_uuid' => null,
            'status' => 'active',
            'hire_date' => $this->faker->dateTimeBetween('-5 years', 'now'),
            'termination_date' => null
        ];
    }
}
