<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Position;
use App\Models\Company;

class DefaultPositionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultPositions = [
            [
                'name' => 'Chief Executive Officer',
                'description' => 'Responsible for the overall operations and resources of a company.',
                'min_salary' => 150000.00,
                'max_salary' => 300000.00,
                'requirements' => [
                    'education' => 'Bachelor\'s degree in Business Administration or related field',
                    'experience' => '10+ years of executive leadership experience',
                    'skills' => ['Leadership', 'Strategic Planning', 'Financial Management', 'Communication']
                ]
            ],
            [
                'name' => 'Chief Technology Officer',
                'description' => 'Leads the technology strategy and development initiatives.',
                'min_salary' => 120000.00,
                'max_salary' => 250000.00,
                'requirements' => [
                    'education' => 'Bachelor\'s degree in Computer Science or related field',
                    'experience' => '8+ years of technology leadership experience',
                    'skills' => ['Technical Leadership', 'Software Architecture', 'Team Management', 'Innovation']
                ]
            ],
            [
                'name' => 'Chief Financial Officer',
                'description' => 'Manages the financial actions and strategy of the company.',
                'min_salary' => 110000.00,
                'max_salary' => 220000.00,
                'requirements' => [
                    'education' => 'Bachelor\'s degree in Finance, Accounting, or related field',
                    'experience' => '8+ years of financial management experience',
                    'skills' => ['Financial Analysis', 'Budgeting', 'Risk Management', 'Compliance']
                ]
            ],
            [
                'name' => 'Human Resources Manager',
                'description' => 'Oversees recruitment, employee relations, and HR policies.',
                'min_salary' => 60000.00,
                'max_salary' => 120000.00,
                'requirements' => [
                    'education' => 'Bachelor\'s degree in Human Resources or related field',
                    'experience' => '5+ years of HR experience',
                    'skills' => ['Recruitment', 'Employee Relations', 'Policy Development', 'Conflict Resolution']
                ]
            ],
            [
                'name' => 'Marketing Manager',
                'description' => 'Develops and implements marketing strategies and campaigns.',
                'min_salary' => 55000.00,
                'max_salary' => 110000.00,
                'requirements' => [
                    'education' => 'Bachelor\'s degree in Marketing or related field',
                    'experience' => '3+ years of marketing experience',
                    'skills' => ['Digital Marketing', 'Brand Management', 'Analytics', 'Campaign Management']
                ]
            ],
            [
                'name' => 'Sales Manager',
                'description' => 'Leads sales team and develops sales strategies.',
                'min_salary' => 50000.00,
                'max_salary' => 100000.00,
                'requirements' => [
                    'education' => 'Bachelor\'s degree in Sales, Marketing, or related field',
                    'experience' => '3+ years of sales experience',
                    'skills' => ['Sales Strategy', 'Team Leadership', 'Customer Relations', 'Negotiation']
                ]
            ],
            [
                'name' => 'Software Developer',
                'description' => 'Designs, develops, and maintains software applications.',
                'min_salary' => 45000.00,
                'max_salary' => 90000.00,
                'requirements' => [
                    'education' => 'Bachelor\'s degree in Computer Science or related field',
                    'experience' => '2+ years of software development experience',
                    'skills' => ['Programming', 'Problem Solving', 'Version Control', 'Testing']
                ]
            ],
            [
                'name' => 'Accountant',
                'description' => 'Manages financial records and ensures compliance with regulations.',
                'min_salary' => 40000.00,
                'max_salary' => 75000.00,
                'requirements' => [
                    'education' => 'Bachelor\'s degree in Accounting or Finance',
                    'experience' => '2+ years of accounting experience',
                    'skills' => ['Financial Reporting', 'Tax Preparation', 'Auditing', 'Attention to Detail']
                ]
            ],
            [
                'name' => 'Administrative Assistant',
                'description' => 'Provides administrative support to executives and departments.',
                'min_salary' => 30000.00,
                'max_salary' => 50000.00,
                'requirements' => [
                    'education' => 'High school diploma or equivalent',
                    'experience' => '1+ years of administrative experience',
                    'skills' => ['Organization', 'Communication', 'Microsoft Office', 'Multitasking']
                ]
            ],
            [
                'name' => 'Customer Service Representative',
                'description' => 'Handles customer inquiries and resolves issues.',
                'min_salary' => 28000.00,
                'max_salary' => 45000.00,
                'requirements' => [
                    'education' => 'High school diploma or equivalent',
                    'experience' => '1+ years of customer service experience',
                    'skills' => ['Communication', 'Problem Solving', 'Patience', 'Product Knowledge']
                ]
            ]
        ];

        // Get all companies that don't have setup completed
        $companies = Company::where('setup_wizard_completed', false)->get();

        foreach ($companies as $company) {
            foreach ($defaultPositions as $positionData) {
                Position::create(array_merge($positionData, [
                    'company_uuid' => $company->uuid,
                    'currency' => $company->getSetting('default_currency', 'USD'),
                    'is_active' => true
                ]));
            }
        }
    }
}