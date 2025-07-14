<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Country;
use App\Models\TaxJurisdiction;
use App\Models\StatutoryDeductionTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class TaxJurisdictionTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_a_tax_jurisdiction()
    {
        $country = Country::factory()->create();
        
        $jurisdictionData = [
            'country_uuid' => $country->uuid,
            'region_code' => null,
            'name' => 'South Africa National',
            'tax_year_start' => '2025-03-01',
            'tax_year_end' => '2026-02-28',
            'regulatory_authority' => 'SARS',
            'effective_from' => now()->subYear(),
            'effective_to' => null,
            'settings' => ['currency' => 'ZAR'],
            'is_active' => true
        ];

        $jurisdiction = TaxJurisdiction::create($jurisdictionData);

        $this->assertDatabaseHas('tax_jurisdictions', [
            'country_uuid' => $country->uuid,
            'name' => 'South Africa National',
            'regulatory_authority' => 'SARS'
        ]);

        $this->assertEquals($country->uuid, $jurisdiction->country_uuid);
        $this->assertEquals('South Africa National', $jurisdiction->name);
        $this->assertTrue($jurisdiction->is_active);
        $this->assertIsArray($jurisdiction->settings);
    }

    /** @test */
    public function it_belongs_to_a_country()
    {
        $country = Country::factory()->create();
        $jurisdiction = TaxJurisdiction::factory()->create([
            'country_uuid' => $country->uuid
        ]);

        $this->assertEquals($country->uuid, $jurisdiction->country->uuid);
        $this->assertEquals($country->name, $jurisdiction->country->name);
    }

    /** @test */
    public function it_can_have_statutory_deduction_templates()
    {
        $country = Country::factory()->create();
        $jurisdiction = TaxJurisdiction::factory()->create([
            'country_uuid' => $country->uuid
        ]);

        // Create a statutory deduction template
        $template = StatutoryDeductionTemplate::create([
            'jurisdiction_uuid' => $jurisdiction->uuid,
            'deduction_type' => 'income_tax',
            'code' => 'PAYE',
            'name' => 'Pay As You Earn',
            'calculation_method' => 'bracket',
            'rules' => ['brackets' => []],
            'effective_from' => now(),
            'is_active' => true
        ]);

        $this->assertTrue($jurisdiction->statutoryDeductionTemplates->contains($template));
        $this->assertEquals($jurisdiction->uuid, $template->jurisdiction_uuid);
    }

    /** @test */
    public function it_can_scope_active_jurisdictions()
    {
        $activeJurisdiction = TaxJurisdiction::factory()->create(['is_active' => true]);
        $inactiveJurisdiction = TaxJurisdiction::factory()->create(['is_active' => false]);

        $activeJurisdictions = TaxJurisdiction::active()->get();

        $this->assertCount(1, $activeJurisdictions);
        $this->assertTrue($activeJurisdictions->contains($activeJurisdiction));
        $this->assertFalse($activeJurisdictions->contains($inactiveJurisdiction));
    }

    /** @test */
    public function it_can_scope_current_jurisdictions()
    {
        // Past jurisdiction
        TaxJurisdiction::factory()->create([
            'effective_from' => now()->subYear(),
            'effective_to' => now()->subMonth()
        ]);

        // Current jurisdiction
        $currentJurisdiction = TaxJurisdiction::factory()->create([
            'effective_from' => now()->subMonth(),
            'effective_to' => null
        ]);

        // Future jurisdiction
        TaxJurisdiction::factory()->create([
            'effective_from' => now()->addMonth(),
            'effective_to' => null
        ]);

        $currentJurisdictions = TaxJurisdiction::current()->get();

        $this->assertCount(1, $currentJurisdictions);
        $this->assertEquals($currentJurisdiction->uuid, $currentJurisdictions->first()->uuid);
    }

    /** @test */
    public function it_handles_overlapping_effective_periods()
    {
        $country = Country::factory()->create();
        
        // Create jurisdiction that ends this month
        $currentJurisdiction = TaxJurisdiction::factory()->create([
            'country_uuid' => $country->uuid,
            'effective_from' => now()->subYear(),
            'effective_to' => now()->addDays(5) // Still active for a few more days
        ]);

        // Create jurisdiction that starts next month
        TaxJurisdiction::factory()->create([
            'country_uuid' => $country->uuid,
            'effective_from' => now()->addMonth()->startOfMonth(),
            'effective_to' => null
        ]);

        // Current scope should return the jurisdiction that's effective now
        $currentJurisdictions = TaxJurisdiction::current()->get();

        $this->assertCount(1, $currentJurisdictions);
        $this->assertEquals($currentJurisdiction->uuid, $currentJurisdictions->first()->uuid);
    }

    /** @test */
    public function it_validates_tax_year_dates()
    {
        $country = Country::factory()->create();
        
        $jurisdiction = TaxJurisdiction::factory()->create([
            'country_uuid' => $country->uuid,
            'tax_year_start' => '2025-03-01',
            'tax_year_end' => '2026-02-28'
        ]);

        $this->assertEquals('2025-03-01', $jurisdiction->tax_year_start->format('Y-m-d'));
        $this->assertEquals('2026-02-28', $jurisdiction->tax_year_end->format('Y-m-d'));
    }

    /** @test */
    public function it_can_store_jurisdiction_specific_settings()
    {
        $settings = [
            'default_currency' => 'ZAR',
            'working_days_per_week' => 5,
            'public_holidays' => ['2025-01-01', '2025-12-25'],
            'overtime_rules' => [
                'daily_threshold' => 8,
                'weekly_threshold' => 40,
                'rate_multiplier' => 1.5
            ]
        ];

        $jurisdiction = TaxJurisdiction::factory()->create([
            'settings' => $settings
        ]);

        $this->assertEquals($settings, $jurisdiction->settings);
        $this->assertEquals('ZAR', $jurisdiction->settings['default_currency']);
        $this->assertEquals(5, $jurisdiction->settings['working_days_per_week']);
        $this->assertIsArray($jurisdiction->settings['public_holidays']);
    }

    /** @test */
    public function it_auto_generates_uuid_on_creation()
    {
        $country = Country::factory()->create();
        
        $jurisdiction = TaxJurisdiction::create([
            'country_uuid' => $country->uuid,
            'name' => 'Test Jurisdiction',
            'tax_year_start' => '2025-01-01',
            'tax_year_end' => '2025-12-31',
            'regulatory_authority' => 'Test Authority',
            'effective_from' => now()
        ]);

        $this->assertNotEmpty($jurisdiction->uuid);
        $this->assertIsString($jurisdiction->uuid);
        $this->assertEquals(36, strlen($jurisdiction->uuid)); // UUID v4 length
    }

    /** @test */
    public function it_can_be_created_with_region_code_for_state_level_jurisdictions()
    {
        $country = Country::factory()->create(['iso_code' => 'US']);
        
        $stateJurisdiction = TaxJurisdiction::factory()->create([
            'country_uuid' => $country->uuid,
            'region_code' => 'CA',
            'name' => 'California State Tax',
            'regulatory_authority' => 'California Franchise Tax Board'
        ]);

        $this->assertEquals('CA', $stateJurisdiction->region_code);
        $this->assertStringContainsString('California', $stateJurisdiction->name);
    }
}