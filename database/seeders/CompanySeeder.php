<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Currency;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        // Get countries with their currencies
        $southAfrica = Country::where('iso_code', 'ZA')->first();
        $unitedStates = Country::where('iso_code', 'US')->first();
        $unitedKingdom = Country::where('iso_code', 'GB')->first();
        $kenya = Country::where('iso_code', 'KE')->first();
        
        // Get currencies for these countries
        $zarCurrency = Currency::where('code', 'ZAR')->first();
        $usdCurrency = Currency::where('code', 'USD')->first();
        $gbpCurrency = Currency::where('code', 'GBP')->first();
        $kesCurrency = Currency::where('code', 'KES')->first();

        $now = now();

        $companies = [
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Acme Corporation (Pty) Ltd',
                'slug' => 'acme-corp',
                'domain' => 'acme.example.com',
                'email' => 'info@acme.example.com',
                'phone' => fake()->phoneNumber(),
                'address' => '123 Business District, Cape Town, Western Cape, South Africa',
                'logo' => null,
                'settings' => json_encode([
                    'timezone' => 'Africa/Johannesburg',
                    'language' => 'en',
                    'date_format' => 'Y-m-d',
                    'payroll_settings' => [
                        'default_pay_frequency' => 'monthly',
                        'tax_year_start' => '03-01'
                    ]
                ]),
                'subscription_plan' => 'premium',
                'is_active' => true,
                'setup_wizard_completed' => true,
                'setup_completed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by_uuid' => null,
                'country_uuid' => $southAfrica?->uuid,
                'currency_uuid' => $zarCurrency?->uuid,
                'subscription_expires_at' => $now->addYear(),
            ],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Globex USA Inc.',
                'slug' => 'globex-usa',
                'domain' => 'globex.example.com',
                'email' => 'hello@globex.example.com',
                'phone' => fake()->phoneNumber(),
                'address' => '456 Corporate Plaza, New York, NY, USA',
                'logo' => null,
                'settings' => json_encode([
                    'timezone' => 'America/New_York',
                    'language' => 'en',
                    'date_format' => 'm/d/Y',
                    'payroll_settings' => [
                        'default_pay_frequency' => 'bi_weekly',
                        'tax_year_start' => '01-01'
                    ]
                ]),
                'subscription_plan' => 'enterprise',
                'is_active' => true,
                'setup_wizard_completed' => true,
                'setup_completed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by_uuid' => null,
                'country_uuid' => $unitedStates?->uuid,
                'currency_uuid' => $usdCurrency?->uuid,
                'subscription_expires_at' => $now->addYear(),
            ],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Tech Innovations Ltd',
                'slug' => 'tech-innovations',
                'domain' => 'techinnovations.example.com',
                'email' => 'contact@techinnovations.example.com',
                'phone' => fake()->phoneNumber(),
                'address' => '789 Tech Park, London, England, UK',
                'logo' => null,
                'settings' => json_encode([
                    'timezone' => 'Europe/London',
                    'language' => 'en',
                    'date_format' => 'd/m/Y',
                    'payroll_settings' => [
                        'default_pay_frequency' => 'monthly',
                        'tax_year_start' => '04-06'
                    ]
                ]),
                'subscription_plan' => 'premium',
                'is_active' => true,
                'setup_wizard_completed' => true,
                'setup_completed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by_uuid' => null,
                'country_uuid' => $unitedKingdom?->uuid,
                'currency_uuid' => $gbpCurrency?->uuid,
                'subscription_expires_at' => $now->addYear(),
            ],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Safari Digital Ltd',
                'slug' => 'safari-digital',
                'domain' => 'safaridigital.example.com',
                'email' => 'info@safaridigital.example.com',
                'phone' => fake()->phoneNumber(),
                'address' => '321 Innovation Hub, Nairobi, Kenya',
                'logo' => null,
                'settings' => json_encode([
                    'timezone' => 'Africa/Nairobi',
                    'language' => 'en',
                    'date_format' => 'd/m/Y',
                    'payroll_settings' => [
                        'default_pay_frequency' => 'monthly',
                        'tax_year_start' => '01-01'
                    ]
                ]),
                'subscription_plan' => 'basic',
                'is_active' => true,
                'setup_wizard_completed' => false,
                'setup_completed_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by_uuid' => null,
                'country_uuid' => $kenya?->uuid,
                'currency_uuid' => $kesCurrency?->uuid,
                'subscription_expires_at' => $now->addMonths(6),
            ]
        ];

        foreach ($companies as $companyData) {
            $company = \App\Models\Company::where('slug', $companyData['slug'])->first();
            
            if ($company) {
                // Update only currency and country links, don't change UUID
                $company->update([
                    'country_uuid' => $companyData['country_uuid'],
                    'currency_uuid' => $companyData['currency_uuid'],
                ]);
            } else {
                // Create new company
                \App\Models\Company::create($companyData);
            }
        }

        $this->command->info('4 companies seeded successfully with proper country and currency linkages.');
        
        // Display the linked data for verification
        $companies = \App\Models\Company::with(['country', 'currency'])->get();
        foreach ($companies as $company) {
            $this->command->info(
                "Company: {$company->name} | Country: {$company->country?->name} | Currency: {$company->currency?->code} ({$company->currency?->symbol})"
            );
        }
    }
}
