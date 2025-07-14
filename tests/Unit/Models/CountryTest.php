<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Country;
use App\Models\TaxJurisdiction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

class CountryTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_a_country_with_valid_data()
    {
        $countryData = [
            'iso_code' => 'ZA',
            'name' => 'South Africa',
            'currency_code' => 'ZAR',
            'timezone' => 'Africa/Johannesburg',
            'is_supported_for_payroll' => true,
            'regulatory_framework' => [
                'tax_year_start' => '03-01',
                'tax_year_end' => '02-28',
                'mandatory_deductions' => ['PAYE', 'UIF', 'Skills Development Levy']
            ]
        ];

        $country = Country::create($countryData);

        $this->assertDatabaseHas('countries', [
            'iso_code' => 'ZA',
            'name' => 'South Africa',
            'currency_code' => 'ZAR',
            'is_supported_for_payroll' => true
        ]);

        $this->assertEquals('ZA', $country->iso_code);
        $this->assertEquals('South Africa', $country->name);
        $this->assertTrue($country->is_supported_for_payroll);
        $this->assertIsArray($country->regulatory_framework);
    }

    /** @test */
    public function it_requires_essential_fields()
    {
        $this->expectException(\Exception::class);
        
        Country::create([]);
    }

    /** @test */
    public function iso_code_must_be_unique()
    {
        Country::create([
            'iso_code' => 'ZA',
            'name' => 'South Africa',
            'currency_code' => 'ZAR',
            'timezone' => 'Africa/Johannesburg'
        ]);

        $this->expectException(\Exception::class);
        
        Country::create([
            'iso_code' => 'ZA', // Duplicate
            'name' => 'Zimbabwe',
            'currency_code' => 'ZWL',
            'timezone' => 'Africa/Harare'
        ]);
    }

    /** @test */
    public function it_can_have_tax_jurisdictions()
    {
        $country = Country::factory()->create(['iso_code' => 'ZA']);
        
        $jurisdiction = TaxJurisdiction::factory()->create([
            'country_uuid' => $country->uuid
        ]);

        $this->assertTrue($country->taxJurisdictions->contains($jurisdiction));
        $this->assertEquals($country->uuid, $jurisdiction->country_uuid);
    }

    /** @test */
    public function it_can_check_if_payroll_is_supported()
    {
        $supportedCountry = Country::factory()->create([
            'is_supported_for_payroll' => true
        ]);
        
        $unsupportedCountry = Country::factory()->create([
            'is_supported_for_payroll' => false
        ]);

        $this->assertTrue($supportedCountry->supportsPayroll());
        $this->assertFalse($unsupportedCountry->supportsPayroll());
    }

    /** @test */
    public function it_can_get_current_tax_jurisdiction()
    {
        $country = Country::factory()->create();
        
        // Create past jurisdiction
        TaxJurisdiction::factory()->create([
            'country_uuid' => $country->uuid,
            'effective_from' => now()->subYear(),
            'effective_to' => now()->subMonth()
        ]);
        
        // Create current jurisdiction
        $currentJurisdiction = TaxJurisdiction::factory()->create([
            'country_uuid' => $country->uuid,
            'effective_from' => now()->subMonth(),
            'effective_to' => null
        ]);
        
        // Create future jurisdiction
        TaxJurisdiction::factory()->create([
            'country_uuid' => $country->uuid,
            'effective_from' => now()->addMonth(),
            'effective_to' => null
        ]);

        $result = $country->getCurrentTaxJurisdiction();
        
        $this->assertEquals($currentJurisdiction->uuid, $result->uuid);
    }

    /** @test */
    public function it_can_get_regulatory_framework_data()
    {
        $framework = [
            'tax_year_start' => '03-01',
            'tax_year_end' => '02-28',
            'mandatory_deductions' => ['PAYE', 'UIF']
        ];
        
        $country = Country::factory()->create([
            'regulatory_framework' => $framework
        ]);

        $this->assertEquals('03-01', $country->getTaxYearStart());
        $this->assertEquals('02-28', $country->getTaxYearEnd());
        $this->assertEquals(['PAYE', 'UIF'], $country->getMandatoryDeductions());
    }

    /** @test */
    public function it_can_scope_by_payroll_support()
    {
        Country::factory()->create(['is_supported_for_payroll' => true]);
        Country::factory()->create(['is_supported_for_payroll' => true]);
        Country::factory()->create(['is_supported_for_payroll' => false]);

        $supportedCountries = Country::payrollSupported()->get();
        $unsupportedCountries = Country::payrollNotSupported()->get();

        $this->assertCount(2, $supportedCountries);
        $this->assertCount(1, $unsupportedCountries);
    }

    /** @test */
    public function it_can_scope_active_countries()
    {
        Country::factory()->create(['is_active' => true]);
        Country::factory()->create(['is_active' => true]);
        Country::factory()->create(['is_active' => false]);

        $activeCountries = Country::active()->get();
        $inactiveCountries = Country::inactive()->get();

        $this->assertCount(2, $activeCountries);
        $this->assertCount(1, $inactiveCountries);
    }
}