<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use App\Models\CompanyPayrollTemplate;
use App\Models\EmployeePayrollItem;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class CompanyPayrollTemplateControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['company_uuid' => $this->company->uuid]);
    }

    /** @test */
    public function it_can_list_company_payroll_templates()
    {
        CompanyPayrollTemplate::factory()->count(3)->create(['company_uuid' => $this->company->uuid]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/payroll-templates');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'uuid',
                            'company_uuid',
                            'code',
                            'name',
                            'type',
                            'calculation_method',
                            'is_active'
                        ]
                    ]
                ],
                'message'
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertCount(3, $response->json('data.data'));
    }

    /** @test */
    public function it_can_filter_templates_by_type()
    {
        CompanyPayrollTemplate::factory()->allowance()->create(['company_uuid' => $this->company->uuid]);
        CompanyPayrollTemplate::factory()->deduction()->create(['company_uuid' => $this->company->uuid]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/payroll-templates?type=allowance');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('allowance', $response->json('data.data.0.type'));
    }

    /** @test */
    public function it_can_create_payroll_template()
    {
        $payload = [
            'code' => 'TRANSPORT',
            'name' => 'Transport Allowance',
            'description' => 'Monthly transport allowance',
            'type' => 'allowance',
            'calculation_method' => 'fixed_amount',
            'amount' => 3000,
            'is_taxable' => false,
            'is_pensionable' => false
        ];

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/payroll-templates', $payload);

        $response->assertCreated()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'uuid',
                    'company_uuid',
                    'code',
                    'name',
                    'type',
                    'calculation_method',
                    'amount'
                ],
                'message'
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals('TRANSPORT', $response->json('data.code'));
        $this->assertEquals('allowance', $response->json('data.type'));
        $this->assertEquals(3000, $response->json('data.amount'));

        $this->assertDatabaseHas('company_payroll_templates', [
            'company_uuid' => $this->company->uuid,
            'code' => 'TRANSPORT',
            'type' => 'allowance',
            'amount' => 3000
        ]);
    }

    /** @test */
    public function it_validates_template_creation_data()
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/payroll-templates', [
                'code' => '', // Required
                'name' => '', // Required
                'type' => 'invalid', // Invalid value
                'calculation_method' => 'invalid', // Invalid value
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'code',
                'name',
                'type',
                'calculation_method'
            ]);
    }

    /** @test */
    public function it_prevents_duplicate_codes_within_company()
    {
        CompanyPayrollTemplate::factory()->create([
            'company_uuid' => $this->company->uuid,
            'code' => 'DUPLICATE'
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/payroll-templates', [
                'code' => 'DUPLICATE',
                'name' => 'Another Template',
                'type' => 'allowance',
                'calculation_method' => 'fixed_amount',
                'amount' => 1000
            ]);

        $response->assertStatus(422);
        $this->assertFalse($response->json('success'));
        $this->assertStringContainsString('already exists', $response->json('message'));
    }

    /** @test */
    public function it_can_show_template_details()
    {
        $template = CompanyPayrollTemplate::factory()->create(['company_uuid' => $this->company->uuid]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/payroll-templates/{$template->uuid}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'uuid',
                    'company_uuid',
                    'code',
                    'name',
                    'description',
                    'type',
                    'calculation_method',
                    'amount'
                ],
                'message'
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals($template->uuid, $response->json('data.uuid'));
    }

    /** @test */
    public function it_returns_404_for_non_existent_template()
    {
        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/payroll-templates/non-existent-uuid');

        $response->assertNotFound();
        $this->assertFalse($response->json('success'));
    }

    /** @test */
    public function it_prevents_access_to_other_company_templates()
    {
        $otherCompany = Company::factory()->create();
        $otherTemplate = CompanyPayrollTemplate::factory()->create(['company_uuid' => $otherCompany->uuid]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/payroll-templates/{$otherTemplate->uuid}");

        $response->assertForbidden();
        $this->assertFalse($response->json('success'));
    }

    /** @test */
    public function it_can_update_template()
    {
        $template = CompanyPayrollTemplate::factory()->create(['company_uuid' => $this->company->uuid]);

        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/payroll-templates/{$template->uuid}", [
                'name' => 'Updated Template Name',
                'amount' => 5000,
                'is_taxable' => true
            ]);

        $response->assertOk();
        $this->assertTrue($response->json('success'));

        $this->assertDatabaseHas('company_payroll_templates', [
            'uuid' => $template->uuid,
            'name' => 'Updated Template Name',
            'amount' => 5000,
            'is_taxable' => true
        ]);
    }

    /** @test */
    public function it_can_delete_unused_template()
    {
        $template = CompanyPayrollTemplate::factory()->create(['company_uuid' => $this->company->uuid]);

        $response = $this->actingAs($this->user, 'api')
            ->deleteJson("/api/payroll-templates/{$template->uuid}");

        $response->assertOk();
        $this->assertTrue($response->json('success'));

        $this->assertDatabaseMissing('company_payroll_templates', ['uuid' => $template->uuid]);
    }

    /** @test */
    public function it_cannot_delete_template_in_use()
    {
        $template = CompanyPayrollTemplate::factory()->create(['company_uuid' => $this->company->uuid]);
        
        // Create employee payroll item using this template
        $employee = Employee::factory()->create(['company_uuid' => $this->company->uuid]);
        EmployeePayrollItem::factory()->create([
            'employee_uuid' => $employee->uuid,
            'template_uuid' => $template->uuid
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->deleteJson("/api/payroll-templates/{$template->uuid}");

        $response->assertStatus(422);
        $this->assertFalse($response->json('success'));
        $this->assertStringContainsString('currently used', $response->json('message'));

        $this->assertDatabaseHas('company_payroll_templates', ['uuid' => $template->uuid]);
    }

    /** @test */
    public function it_can_toggle_template_status()
    {
        $template = CompanyPayrollTemplate::factory()->create([
            'company_uuid' => $this->company->uuid,
            'is_active' => true
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/payroll-templates/{$template->uuid}/toggle-status");

        $response->assertOk();
        $this->assertTrue($response->json('success'));
        $this->assertStringContainsString('deactivated', $response->json('message'));

        $this->assertDatabaseHas('company_payroll_templates', [
            'uuid' => $template->uuid,
            'is_active' => false
        ]);

        // Toggle again
        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/payroll-templates/{$template->uuid}/toggle-status");

        $response->assertOk();
        $this->assertStringContainsString('activated', $response->json('message'));

        $this->assertDatabaseHas('company_payroll_templates', [
            'uuid' => $template->uuid,
            'is_active' => true
        ]);
    }

    /** @test */
    public function it_can_test_template_calculation()
    {
        $template = CompanyPayrollTemplate::factory()->fixedAmount()->create([
            'company_uuid' => $this->company->uuid,
            'amount' => 2500
        ]);

        $payload = [
            'employee_basic_salary' => 30000,
            'gross_salary' => 35000
        ];

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/payroll-templates/{$template->uuid}/test-calculation", $payload);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'calculated_amount',
                    'employee_basic_salary',
                    'gross_salary_used',
                    'calculation_method',
                    'template_settings'
                ],
                'message'
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals(2500, $response->json('data.calculated_amount'));
        $this->assertEquals(30000, $response->json('data.employee_basic_salary'));
        $this->assertEquals('fixed_amount', $response->json('data.calculation_method'));
    }

    /** @test */
    public function it_validates_test_calculation_data()
    {
        $template = CompanyPayrollTemplate::factory()->create(['company_uuid' => $this->company->uuid]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/payroll-templates/{$template->uuid}/test-calculation", [
                'employee_basic_salary' => 'invalid'
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['employee_basic_salary']);
    }

    /** @test */
    public function it_requires_authentication()
    {
        $response = $this->getJson('/api/payroll-templates');
        $response->assertUnauthorized();
    }

    /** @test */
    public function it_handles_user_without_company()
    {
        $userWithoutCompany = User::factory()->create(['company_uuid' => null]);

        $response = $this->actingAs($userWithoutCompany, 'api')
            ->getJson('/api/payroll-templates');

        $response->assertForbidden();
        $this->assertFalse($response->json('success'));
        $this->assertStringContainsString('User must belong to a company', $response->json('message'));
    }

    /** @test */
    public function it_can_create_percentage_based_template()
    {
        $payload = [
            'code' => 'HOUSING',
            'name' => 'Housing Allowance',
            'type' => 'allowance',
            'calculation_method' => 'percentage_of_basic',
            'default_percentage' => 25,
            'minimum_amount' => 1000,
            'maximum_amount' => 10000
        ];

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/payroll-templates', $payload);

        $response->assertCreated();
        $this->assertTrue($response->json('success'));
        $this->assertEquals(25, $response->json('data.default_percentage'));
    }

    /** @test */
    public function it_can_create_formula_based_template()
    {
        $payload = [
            'code' => 'FORMULA_BONUS',
            'name' => 'Formula Based Bonus',
            'type' => 'allowance',
            'calculation_method' => 'formula',
            'formula_expression' => '{basic_salary} * 0.1 + {years_of_service} * 100'
        ];

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/payroll-templates', $payload);

        $response->assertCreated();
        $this->assertTrue($response->json('success'));
        $this->assertEquals('{basic_salary} * 0.1 + {years_of_service} * 100', $response->json('data.formula_expression'));
    }
}