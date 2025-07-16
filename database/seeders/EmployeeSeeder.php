<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $defaultCompanyUuid = 'e25d1c19-b8c5-435f-8eef-e0aeba84cf26';
        $defaultDepartmentUuid = 'a22785de-3936-4caa-ba0b-5a4308bd2303';
        $defaultPositionUuid = '9875119f-3004-4db7-aee7-833dba80aecc';

        // Fetch available UUIDs
        $company = DB::table('companies')->inRandomOrder()->first();
        $companyUuid = $company->uuid ?? $defaultCompanyUuid;
        $companyId = $company->id ?? 1;

        $department = DB::table('departments')->where('company_uuid', $companyUuid)->inRandomOrder()->first();
        $departmentUuid = $department->uuid ?? $defaultDepartmentUuid;

        $position = DB::table('positions')->where('company_uuid', $companyUuid)->inRandomOrder()->first();
        $positionUuid = $position->uuid ?? $defaultPositionUuid;

        $employees = [];
        $insertedEmployees = [];

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

            // Choose a manager UUID from already inserted employees (same department)
            $managerUuid = null;
            $possibleManagers = array_filter($insertedEmployees, function ($e) use ($departmentUuid) {
                return $e['department_uuid'] === $departmentUuid;
            });
            if (!empty($possibleManagers)) {
                $manager = fake()->randomElement($possibleManagers);
                $managerUuid = $manager['uuid'];
            }

            $employee = [
                'uuid' => $uuid,
                'company_id' => $companyId,
                'company_uuid' => $companyUuid,
                'department_uuid' => $departmentUuid,
                'position_uuid' => $positionUuid,
                'manager_uuid' => $managerUuid,
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

            $employees[] = $employee;
            $insertedEmployees[] = $employee;
        }

        DB::table('employees')->insert($employees);
    }
}
