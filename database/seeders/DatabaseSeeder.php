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
        // Seed currencies first (reference data)
        $this->call([
            CurrenciesSeeder::class,
            CountrySeeder::class,
            Company::class,
            TaxJurisdictionSeeder::class,
            StatutoryDeductionTemplateSeeder::class,
            DefaultDepartmentsSeeder::class,
            DefaultPositionsSeeder::class,
            EmployeeSeeder::class,
            ZimbabweStatutoryDeductionTemplateSeeder::class,
        ]);

        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
