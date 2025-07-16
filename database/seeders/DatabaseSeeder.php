<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use NunoMaduro\Collision\Adapters\Phpunit\Printers\DefaultPrinter;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed reference data in proper order
        $this->call([
            CurrenciesSeeder::class,           // 1. Currencies first
            CountrySeeder::class,              // 2. Countries (links to currencies)
            CompanySeeder::class,              // 3. Companies (links to countries & currencies)
            TaxJurisdictionSeeder::class,      // 4. Tax jurisdictions
            StatutoryDeductionTemplateSeeder::class, // 5. Statutory deductions
            DefaultDepartmentsSeeder::class,   // 6. Departments
            DefaultPositionsSeeder::class,     // 7. Positions
            EmployeeSeeder::class,             // 8. Employees (links to companies)
            ZimbabweStatutoryDeductionTemplateSeeder::class, // 9. Additional country-specific data
        ]);

        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
