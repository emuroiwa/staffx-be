<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Department;
use App\Models\Company;

class DefaultDepartmentsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultDepartments = [
            [
                'name' => 'Executive',
                'description' => 'Executive leadership and strategic planning department.',
                'cost_center' => 'EXE-001',
                'budget_info' => [
                    'allocation' => 500000.00,
                    'currency' => 'ZAR',
                    'fiscal_year' => date('Y')
                ]
            ],
            [
                'name' => 'Human Resources',
                'description' => 'Manages employee relations, recruitment, and HR policies.',
                'cost_center' => 'HR-001',
                'budget_info' => [
                    'allocation' => 200000.00,
                    'currency' => 'ZAR',
                    'fiscal_year' => date('Y')
                ]
            ],
            [
                'name' => 'Finance & Accounting',
                'description' => 'Handles financial planning, accounting, and budget management.',
                'cost_center' => 'FIN-001',
                'budget_info' => [
                    'allocation' => 300000.00,
                    'currency' => 'ZAR',
                    'fiscal_year' => date('Y')
                ]
            ],
            [
                'name' => 'Information Technology',
                'description' => 'Manages technology infrastructure and software development.',
                'cost_center' => 'IT-001',
                'budget_info' => [
                    'allocation' => 800000.00,
                    'currency' => 'ZAR',
                    'fiscal_year' => date('Y')
                ]
            ],
            [
                'name' => 'Sales',
                'description' => 'Drives revenue generation and customer acquisition.',
                'cost_center' => 'SAL-001',
                'budget_info' => [
                    'allocation' => 600000.00,
                    'currency' => 'ZAR',
                    'fiscal_year' => date('Y')
                ]
            ],
            [
                'name' => 'Marketing',
                'description' => 'Develops brand awareness and marketing campaigns.',
                'cost_center' => 'MKT-001',
                'budget_info' => [
                    'allocation' => 400000.00,
                    'currency' => 'ZAR',
                    'fiscal_year' => date('Y')
                ]
            ],
            [
                'name' => 'Operations',
                'description' => 'Manages day-to-day business operations and processes.',
                'cost_center' => 'OPS-001',
                'budget_info' => [
                    'allocation' => 350000.00,
                    'currency' => 'ZAR',
                    'fiscal_year' => date('Y')
                ]
            ],
            [
                'name' => 'Customer Service',
                'description' => 'Provides customer support and manages customer relationships.',
                'cost_center' => 'CS-001',
                'budget_info' => [
                    'allocation' => 250000.00,
                    'currency' => 'ZAR',
                    'fiscal_year' => date('Y')
                ]
            ],
            [
                'name' => 'Research & Development',
                'description' => 'Focuses on innovation and product development.',
                'cost_center' => 'RD-001',
                'budget_info' => [
                    'allocation' => 700000.00,
                    'currency' => 'ZAR',
                    'fiscal_year' => date('Y')
                ]
            ],
            [
                'name' => 'Administration',
                'description' => 'Handles administrative functions and general office management.',
                'cost_center' => 'ADM-001',
                'budget_info' => [
                    'allocation' => 150000.00,
                    'currency' => 'ZAR',
                    'fiscal_year' => date('Y')
                ]
            ]
        ];

        // Get all companies that don't have setup completed
        $companies = Company::where('setup_wizard_completed', false)->get();

        foreach ($companies as $company) {
            foreach ($defaultDepartments as $departmentData) {
                // Adjust budget currency based on company settings
                $departmentData['budget_info']['currency'] = $company->getSetting('default_currency', 'ZAR');
                
                Department::create(array_merge($departmentData, [
                    'company_uuid' => $company->uuid,
                    'is_active' => true
                ]));
            }
        }
    }
}