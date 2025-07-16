<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Department;
use App\Models\Position;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        // Get all companies with their countries to determine appropriate salaries and pay frequencies
        $companies = Company::with(['country', 'currency'])->get();
        
        if ($companies->isEmpty()) {
            $this->command->warn('No companies found. Please run CompanySeeder first.');
            return;
        }
        
        $allEmployees = [];
        $employeesPerCompany = ceil(50 / $companies->count()); // Distribute ~50 employees across companies

        foreach ($companies as $companyIndex => $company) {
            // Get departments and positions for this company
            $departments = Department::where('company_uuid', $company->uuid)->get();
            $positions = Position::where('company_uuid', $company->uuid)->get();
            
            if ($departments->isEmpty() || $positions->isEmpty()) {
                $this->command->warn("Skipping {$company->name} - no departments or positions found.");
                continue;
            }
            
            // Determine salary range and pay frequency based on country
            $salaryMultiplier = $this->getSalaryMultiplier($company->country?->iso_code);
            $payFrequencies = $this->getPayFrequencies($company->country?->iso_code);
            
            for ($i = 1; $i <= $employeesPerCompany; $i++) {
                $globalEmployeeNumber = ($companyIndex * $employeesPerCompany) + $i;
                $department = $departments->random();
                $position = $positions->random();

                $uuid = (string) Str::uuid();
                $firstName = fake()->firstName();
                $lastName = fake()->lastName();
                $email = strtolower($firstName . '.' . $lastName . $globalEmployeeNumber . '@' . $company->domain);
                $phone = fake()->phoneNumber();
                $dob = fake()->dateTimeBetween('-60 years', '-20 years')->format('Y-m-d');
                $startDate = fake()->dateTimeBetween('-3 years', 'now')->format('Y-m-d');
                $hireDate = $startDate;
                
                // Generate salary based on country and position level
                $baseSalary = fake()->numberBetween(25000, 120000);
                $salary = round($baseSalary * $salaryMultiplier);
                
                $payFrequency = fake()->randomElement($payFrequencies);
                $isDirector = fake()->boolean(10); // 10% chance of being director
                $isIndependent = fake()->boolean(5); // 5% chance of being contractor
                $isUifExempt = $isIndependent || $isDirector; // Directors and contractors often UIF exempt
                $taxNumber = $this->generateTaxNumber($company->country?->iso_code);
                $nationalId = $this->generateNationalId($company->country?->iso_code);
                $passportNumber = fake()->optional()->bothify('A########');
                $emergencyName = fake()->name();
                $emergencyPhone = fake()->phoneNumber();
                $status = fake()->randomElement(['active', 'active', 'active', 'active', 'inactive']); // 80% active
                $now = now()->toDateTimeString();

                // Assign manager (20% chance, and only from same company)
                $managerUuid = null;
                if (fake()->boolean(20)) {
                    $existingCompanyEmployees = array_filter($allEmployees, function($emp) use ($company) {
                        return $emp['company_uuid'] === $company->uuid;
                    });
                    if (!empty($existingCompanyEmployees)) {
                        $manager = fake()->randomElement($existingCompanyEmployees);
                        $managerUuid = $manager['uuid'];
                    }
                }

                $employee = [
                    'uuid' => $uuid,
                    'company_id' => null, // Legacy field, not used
                    'company_uuid' => $company->uuid,
                    'department_uuid' => $department->uuid,
                    'position_uuid' => $position->uuid,
                    'manager_uuid' => $managerUuid,
                    'user_id' => null,
                    'user_uuid' => null,
                    'employee_id' => strtoupper(substr($company->slug, 0, 3)) . str_pad($i, 4, '0', STR_PAD_LEFT),
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'phone' => $phone,
                    'dob' => $dob,
                    'start_date' => $startDate,
                    'address' => fake()->address(),
                    'department' => null, // Legacy field
                    'position' => null,   // Legacy field
                    'employment_type' => fake()->randomElement(['full_time', 'part_time', 'contract', 'intern']),
                    'is_director' => $isDirector,
                    'is_independent_contractor' => $isIndependent,
                    'is_uif_exempt' => $isUifExempt,
                    'salary' => $salary,
                    'tax_number' => $taxNumber,
                    'bank_details' => json_encode([
                        'bank_name' => $this->getBankName($company->country?->iso_code),
                        'account_number' => fake()->bankAccountNumber(),
                        'account_type' => fake()->randomElement(['checking', 'savings']),
                        'routing_number' => fake()->optional()->numerify('########'),
                    ]),
                    'pay_frequency' => $payFrequency,
                    'national_id' => $nationalId,
                    'passport_number' => $passportNumber,
                    'emergency_contact_name' => $emergencyName,
                    'emergency_contact_phone' => $emergencyPhone,
                    'hire_date' => $hireDate,
                    'termination_date' => $status === 'inactive' ? fake()->optional()->dateTimeBetween($startDate, 'now')?->format('Y-m-d') : null,
                    'status' => $status,
                    'benefits' => json_encode([
                        'health_insurance' => fake()->boolean(70),
                        'dental_insurance' => fake()->boolean(50),
                        'retirement_plan' => fake()->boolean(60),
                        'life_insurance' => fake()->boolean(40),
                    ]),
                    'documents' => null,
                    'notes' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $allEmployees[] = $employee;
            }
        }
        
        if (!empty($allEmployees)) {
            DB::table('employees')->insert($allEmployees);
            $this->command->info(count($allEmployees) . ' employees seeded successfully across ' . $companies->count() . ' companies.');
            
            // Display summary by company
            foreach ($companies as $company) {
                $companyEmployeeCount = collect($allEmployees)->where('company_uuid', $company->uuid)->count();
                $this->command->info("  {$company->name}: {$companyEmployeeCount} employees");
            }
        } else {
            $this->command->warn('No employees were created.');
        }
    }
    
    private function getSalaryMultiplier(string $countryCode = null): float
    {
        return match($countryCode) {
            'ZA' => 1.0,      // South Africa - base
            'US' => 3.2,      // USA - higher salaries
            'GB' => 2.8,      // UK - higher salaries
            'KE' => 0.4,      // Kenya - lower cost of living
            'NG' => 0.3,      // Nigeria - lower cost of living
            'GH' => 0.4,      // Ghana - lower cost of living
            'BW' => 0.8,      // Botswana
            'ZM' => 0.5,      // Zambia
            'ZW' => 0.1,      // Zimbabwe - economic challenges
            'MU' => 0.9,      // Mauritius
            default => 1.0,
        };
    }
    
    private function getPayFrequencies(string $countryCode = null): array
    {
        return match($countryCode) {
            'ZA' => ['monthly', 'monthly', 'monthly', 'weekly'], // Mostly monthly in SA
            'US' => ['bi_weekly', 'bi_weekly', 'monthly', 'weekly'], // Mostly bi-weekly in US
            'GB' => ['monthly', 'monthly', 'weekly'], // Monthly and weekly in UK
            'KE' => ['monthly'], // Predominantly monthly in Kenya
            'NG' => ['monthly'], // Predominantly monthly in Nigeria
            default => ['monthly', 'weekly'], // Default mix
        };
    }
    
    private function generateTaxNumber(string $countryCode = null): string
    {
        return match($countryCode) {
            'ZA' => fake()->numerify('##########'), // 10 digits for RSA
            'US' => fake()->ssn(), // SSN format
            'GB' => fake()->bothify('??######?'), // UK NI number format
            'KE' => 'A' . fake()->numerify('##########'), // KRA PIN
            default => fake()->numerify('#########'),
        };
    }
    
    private function generateNationalId(string $countryCode = null): string
    {
        return match($countryCode) {
            'ZA' => fake()->numerify('############'), // 13 digits for SA ID
            'US' => fake()->ssn(),
            'GB' => fake()->bothify('??######?'),
            'KE' => fake()->numerify('########'),
            default => fake()->numerify('########'),
        };
    }
    
    private function getBankName(string $countryCode = null): string
    {
        $banks = match($countryCode) {
            'ZA' => ['Standard Bank', 'FNB', 'ABSA', 'Nedbank', 'Capitec'],
            'US' => ['Chase', 'Bank of America', 'Wells Fargo', 'Citibank'],
            'GB' => ['Barclays', 'HSBC', 'Lloyds', 'Royal Bank of Scotland'],
            'KE' => ['KCB', 'Equity Bank', 'Standard Chartered', 'Barclays Kenya'],
            default => ['Local Bank', 'Community Bank', 'Regional Bank'],
        };
        
        return fake()->randomElement($banks);
    }
}