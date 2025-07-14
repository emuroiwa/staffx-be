<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Country;
use App\Models\TaxJurisdiction;
use App\Models\StatutoryDeductionTemplate;
use App\Models\Payroll;
use App\Models\PayrollItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Carbon\Carbon;

class PayrollControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private Company $company;
    private Country $country;

    protected function setUp(): void
    {
        parent::setUp();

        // Create country and statutory setup
        $this->country = Country::factory()->southAfrica()->create();
        $jurisdiction = TaxJurisdiction::factory()->create(['country_uuid' => $this->country->uuid]);
        
        StatutoryDeductionTemplate::factory()->southAfricanPAYE()->create([
            'jurisdiction_uuid' => $jurisdiction->uuid
        ]);

        // Create company and user
        $this->company = Company::factory()->create(['country_uuid' => $this->country->uuid]);
        $this->user = User::factory()->create(['company_uuid' => $this->company->uuid]);
    }

    /** @test */
    public function it_can_list_payrolls()
    {
        // Create some payrolls
        Payroll::factory()->count(3)->create(['company_uuid' => $this->company->uuid]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/payrolls');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'uuid',
                            'company_uuid',
                            'payroll_period_start',
                            'payroll_period_end',
                            'total_employees',
                            'total_gross_salary',
                            'total_net_salary',
                            'status',
                            'created_at'
                        ]
                    ],
                    'current_page',
                    'total'
                ],
                'message'
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertCount(3, $response->json('data.data'));
    }

    /** @test */
    public function it_can_filter_payrolls_by_status()
    {
        Payroll::factory()->create(['company_uuid' => $this->company->uuid, 'status' => 'draft']);
        Payroll::factory()->create(['company_uuid' => $this->company->uuid, 'status' => 'approved']);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/payrolls?status=draft');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('draft', $response->json('data.data.0.status'));
    }

    /** @test */
    public function it_can_create_payroll()
    {
        // Create employees
        $employees = Employee::factory()->count(2)->create([
            'company_uuid' => $this->company->uuid,
            'salary' => 25000
        ]);

        $payload = [
            'payroll_period_start' => '2025-07-01',
            'payroll_period_end' => '2025-07-31',
            'employee_uuids' => $employees->pluck('uuid')->toArray()
        ];

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/payrolls', $payload);

        $response->assertCreated()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'payroll' => [
                        'uuid',
                        'company_uuid',
                        'payroll_period_start',
                        'payroll_period_end',
                        'total_employees',
                        'total_gross_salary',
                        'status'
                    ],
                    'summary' => [
                        'total_employees',
                        'successful_calculations',
                        'failed_calculations',
                        'total_gross_salary'
                    ]
                ],
                'message'
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals(2, $response->json('data.payroll.total_employees'));
        $this->assertEquals('draft', $response->json('data.payroll.status'));

        // Check database
        $this->assertDatabaseHas('payrolls', [
            'company_uuid' => $this->company->uuid,
            'total_employees' => 2,
            'status' => 'draft'
        ]);
    }

    /** @test */
    public function it_validates_payroll_creation_data()
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/payrolls', [
                'payroll_period_start' => 'invalid-date',
                'payroll_period_end' => '2025-06-30', // Before start date
                'employee_uuids' => []
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'payroll_period_start',
                'payroll_period_end',
                'employee_uuids'
            ]);
    }

    /** @test */
    public function it_can_show_payroll_details()
    {
        $payroll = Payroll::factory()->create(['company_uuid' => $this->company->uuid]);
        
        // Create some payroll items
        PayrollItem::factory()->count(3)->create(['payroll_uuid' => $payroll->uuid]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/payrolls/{$payroll->uuid}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'uuid',
                    'company_uuid',
                    'payroll_period_start',
                    'payroll_items' => [
                        '*' => [
                            'uuid',
                            'code',
                            'name',
                            'type',
                            'amount'
                        ]
                    ]
                ],
                'message'
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals($payroll->uuid, $response->json('data.uuid'));
        $this->assertCount(3, $response->json('data.payroll_items'));
    }

    /** @test */
    public function it_returns_404_for_non_existent_payroll()
    {
        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/payrolls/non-existent-uuid');

        $response->assertNotFound();
        $this->assertFalse($response->json('success'));
    }

    /** @test */
    public function it_can_update_draft_payroll()
    {
        $payroll = Payroll::factory()->draft()->create(['company_uuid' => $this->company->uuid]);

        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/payrolls/{$payroll->uuid}", [
                'notes' => 'Updated payroll notes'
            ]);

        $response->assertOk();
        $this->assertTrue($response->json('success'));

        $this->assertDatabaseHas('payrolls', [
            'uuid' => $payroll->uuid,
            'notes' => 'Updated payroll notes'
        ]);
    }

    /** @test */
    public function it_cannot_update_non_draft_payroll()
    {
        $payroll = Payroll::factory()->approved()->create(['company_uuid' => $this->company->uuid]);

        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/payrolls/{$payroll->uuid}", [
                'notes' => 'Should not update'
            ]);

        $response->assertStatus(422);
        $this->assertFalse($response->json('success'));
        $this->assertStringContainsString('Only draft payrolls', $response->json('message'));
    }

    /** @test */
    public function it_can_delete_draft_payroll()
    {
        $payroll = Payroll::factory()->draft()->create(['company_uuid' => $this->company->uuid]);

        $response = $this->actingAs($this->user, 'api')
            ->deleteJson("/api/payrolls/{$payroll->uuid}");

        $response->assertOk();
        $this->assertTrue($response->json('success'));

        $this->assertDatabaseMissing('payrolls', ['uuid' => $payroll->uuid]);
    }

    /** @test */
    public function it_cannot_delete_non_draft_payroll()
    {
        $payroll = Payroll::factory()->approved()->create(['company_uuid' => $this->company->uuid]);

        $response = $this->actingAs($this->user, 'api')
            ->deleteJson("/api/payrolls/{$payroll->uuid}");

        $response->assertStatus(422);
        $this->assertFalse($response->json('success'));

        $this->assertDatabaseHas('payrolls', ['uuid' => $payroll->uuid]);
    }

    /** @test */
    public function it_can_approve_draft_payroll()
    {
        $payroll = Payroll::factory()->draft()->create(['company_uuid' => $this->company->uuid]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/payrolls/{$payroll->uuid}/approve");

        $response->assertOk();
        $this->assertTrue($response->json('success'));
        $this->assertEquals('approved', $response->json('data.status'));

        $this->assertDatabaseHas('payrolls', [
            'uuid' => $payroll->uuid,
            'status' => 'approved',
            'approved_by' => $this->user->id
        ]);
    }

    /** @test */
    public function it_cannot_approve_non_draft_payroll()
    {
        $payroll = Payroll::factory()->approved()->create(['company_uuid' => $this->company->uuid]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/payrolls/{$payroll->uuid}/approve");

        $response->assertStatus(422);
        $this->assertFalse($response->json('success'));
    }

    /** @test */
    public function it_can_process_approved_payroll()
    {
        $payroll = Payroll::factory()->approved()->create(['company_uuid' => $this->company->uuid]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/payrolls/{$payroll->uuid}/process");

        $response->assertOk();
        $this->assertTrue($response->json('success'));
        $this->assertEquals('processed', $response->json('data.status'));

        $this->assertDatabaseHas('payrolls', [
            'uuid' => $payroll->uuid,
            'status' => 'processed'
        ]);
    }

    /** @test */
    public function it_cannot_process_non_approved_payroll()
    {
        $payroll = Payroll::factory()->draft()->create(['company_uuid' => $this->company->uuid]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/payrolls/{$payroll->uuid}/process");

        $response->assertStatus(422);
        $this->assertFalse($response->json('success'));
    }

    /** @test */
    public function it_can_preview_payroll_calculations()
    {
        $employees = Employee::factory()->count(2)->create([
            'company_uuid' => $this->company->uuid,
            'salary' => 30000
        ]);

        $payload = [
            'payroll_period_start' => '2025-07-01',
            'payroll_period_end' => '2025-07-31',
            'employee_uuids' => $employees->pluck('uuid')->toArray()
        ];

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/payrolls/preview', $payload);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'summary' => [
                        'total_employees',
                        'successful_calculations',
                        'total_gross_salary',
                        'total_net_salary'
                    ],
                    'calculations' => [
                        '*' => [
                            'employee_uuid',
                            'basic_salary',
                            'gross_salary',
                            'total_allowances',
                            'total_deductions',
                            'net_salary'
                        ]
                    ]
                ],
                'message'
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals(2, $response->json('data.summary.total_employees'));
    }

    /** @test */
    public function it_can_get_payroll_statistics()
    {
        // Create payrolls with different statuses
        Payroll::factory()->draft()->create(['company_uuid' => $this->company->uuid, 'total_gross_salary' => 50000]);
        Payroll::factory()->approved()->create(['company_uuid' => $this->company->uuid, 'total_gross_salary' => 75000]);
        Payroll::factory()->processed()->create(['company_uuid' => $this->company->uuid, 'total_gross_salary' => 100000]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/payrolls/statistics');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_payrolls',
                    'draft_payrolls',
                    'approved_payrolls',
                    'processed_payrolls',
                    'total_gross_salary',
                    'total_net_salary',
                    'total_deductions'
                ],
                'message'
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals(3, $response->json('data.total_payrolls'));
        $this->assertEquals(1, $response->json('data.draft_payrolls'));
        $this->assertEquals(1, $response->json('data.approved_payrolls'));
        $this->assertEquals(1, $response->json('data.processed_payrolls'));
        $this->assertEquals(225000, $response->json('data.total_gross_salary'));
    }

    /** @test */
    public function it_requires_authentication()
    {
        $response = $this->getJson('/api/payrolls');
        $response->assertUnauthorized();
    }

    /** @test */
    public function it_validates_employee_access_during_creation()
    {
        // Create employee from different company
        $otherCompany = Company::factory()->create();
        $otherEmployee = Employee::factory()->create(['company_uuid' => $otherCompany->uuid]);

        $payload = [
            'payroll_period_start' => '2025-07-01',
            'payroll_period_end' => '2025-07-31',
            'employee_uuids' => [$otherEmployee->uuid]
        ];

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/payrolls', $payload);

        $response->assertNotFound();
        $this->assertFalse($response->json('success'));
        $this->assertStringContainsString('not found or do not belong to your company', $response->json('message'));
    }

    /** @test */
    public function it_handles_user_without_company()
    {
        $userWithoutCompany = User::factory()->create(['company_uuid' => null]);

        $response = $this->actingAs($userWithoutCompany, 'api')
            ->getJson('/api/payrolls');

        $response->assertForbidden();
        $this->assertFalse($response->json('success'));
        $this->assertStringContainsString('User must belong to a company', $response->json('message'));
    }
}