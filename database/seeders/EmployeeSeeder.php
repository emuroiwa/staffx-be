<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class EmployeeSeeder extends Seeder
{
    const COMPANY_ID = 1;
    const COMPANY_UUID = 'e25d1c19-b8c5-435f-8eef-e0aeba84cf26';
    const DEPARTMENT_UUID = 'a22785de-3936-4caa-ba0b-5a4308bd2303';
    const POSITION_UUID = '9875119f-3004-4db7-aee7-833dba80aecc';
    const MANAGER_UUID = null;

    public function run(): void
    {
        $employees = [];

        for ($i = 1; $i <= 100; $i++) {
            $uuid = (string) Str::uuid();
            $firstName = fake()->firstName();
            $lastName = fake()->lastName();
            $email = strtolower($firstName . '.' . $lastName . $i . '@example.com');
            $phone = fake()->phoneNumber();
            $dob = fake()->dateTimeBetween('-60 years', '-20 years')->format('Y-m-d');
            $startDate = fake()->dateTimeBetween('-3 years', 'now')->format('Y-m-d');
            $hireDate = $startDate;
            $salary = fake()->numberBetween(15000, 150000);
            $isDirector = fake()->boolean();
            $isIndependent = fake()->boolean();
            $isUifExempt = fake()->boolean();
            $taxNumber = (string) fake()->randomNumber(9, true);
            $nationalId = (string) fake()->randomNumber(8, true);
            $passportNumber = fake()->optional()->bothify('A########');
            $emergencyName = fake()->name();
            $emergencyPhone = fake()->phoneNumber();
            $status = 'active';
            $now = now()->toDateTimeString();

            $employees[] = [
                'uuid' => $uuid,
                'company_id' => self::COMPANY_ID,
                'company_uuid' => self::COMPANY_UUID,
                'department_uuid' => self::DEPARTMENT_UUID,
                'position_uuid' => self::POSITION_UUID,
                'manager_uuid' => self::MANAGER_UUID,
                'user_id' => null,
                'user_uuid' => null,
                'employee_id' => 'EMP' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone,
                'dob' => $dob,
                'start_date' => $startDate,
                'address' => fake()->address(),
                'department' => null,
                'position' => null,
                'employment_type' => fake()->randomElement(['full_time', 'part_time', 'contract']),
                'is_director' => $isDirector,
                'is_independent_contractor' => $isIndependent,
                'is_uif_exempt' => $isUifExempt,
                'salary' => $salary,
                'tax_number' => $taxNumber,
                'bank_details' => json_encode([
                    'bank_name' => fake()->optional()->company(),
                    'account_number' => fake()->bankAccountNumber(),
                    'account_type' => 'checking',
                ]),
                'pay_frequency' => 'monthly',
                'national_id' => $nationalId,
                'passport_number' => $passportNumber,
                'emergency_contact_name' => $emergencyName,
                'emergency_contact_phone' => $emergencyPhone,
                'hire_date' => $hireDate,
                'termination_date' => null,
                'status' => $status,
                'benefits' => json_encode([
                    'health_insurance' => fake()->boolean(),
                    'dental_insurance' => fake()->boolean(),
                    'retirement_plan' => fake()->boolean(),
                ]),
                'documents' => null,
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('employees')->insert($employees);
    }
}
