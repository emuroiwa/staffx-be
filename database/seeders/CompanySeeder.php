<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = DB::table('currencies')->inRandomOrder()->take(2)->get();
        $currency1 = $currencies[0]->uuid ?? '00000000-0000-0000-0000-000000000000';
        $currency2 = $currencies[1]->uuid ?? '11111111-1111-1111-1111-111111111111';

        $now = now();

        $companies = [
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Acme Corporation',
                'slug' => 'acme-corp',
                'domain' => 'acme.example.com',
                'email' => 'info@acme.example.com',
                'phone' => fake()->phoneNumber(),
                'address' => fake()->address(),
                'logo' => null,
                'settings' => json_encode([
                    'timezone' => 'Africa/Johannesburg',
                    'language' => 'en',
                    'date_format' => 'Y-m-d',
                ]),
                'subscription_plan' => 'premium',
                'is_active' => true,
                'setup_wizard_completed' => true,
                'setup_completed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by_uuid' => null,
                'country_uuid' => null,
                'currency_uuid' => $currency1,
                'subscription_expires_at' => $now->addYear(),
            ],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Globex Ltd.',
                'slug' => 'globex',
                'domain' => 'globex.example.com',
                'email' => 'hello@globex.example.com',
                'phone' => fake()->phoneNumber(),
                'address' => fake()->address(),
                'logo' => null,
                'settings' => json_encode([
                    'timezone' => 'Africa/Lusaka',
                    'language' => 'en',
                    'date_format' => 'd/m/Y',
                ]),
                'subscription_plan' => 'basic',
                'is_active' => true,
                'setup_wizard_completed' => false,
                'setup_completed_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by_uuid' => null,
                'country_uuid' => null,
                'currency_uuid' => $currency2,
                'subscription_expires_at' => $now->addMonths(6),
            ]
        ];

        DB::table('companies')->insert($companies);

        $this->command->info('2 companies seeded successfully.');
    }
}
