<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\User;
use App\Repositories\CompanyRepository;
use App\Services\CompanyService;
use PHPUnit\Framework\TestCase;
use Mockery;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CompanyServiceTest extends TestCase
{
    private CompanyService $companyService;
    private CompanyRepository $mockRepository;
    private User $hcaUser;
    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRepository = Mockery::mock(CompanyRepository::class);
        $this->companyService = new CompanyService($this->mockRepository);

        $this->hcaUser = Mockery::mock(User::class);
        $this->hcaUser->shouldReceive('isHoldingCompanyAdmin')->andReturn(true);
        $this->hcaUser->shouldReceive('hasActiveTrial')->andReturn(true);
        $this->hcaUser->id = 1;
        $this->hcaUser->default_company_id = null;

        $this->regularUser = Mockery::mock(User::class);
        $this->regularUser->shouldReceive('isHoldingCompanyAdmin')->andReturn(false);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_companies_for_user_throws_exception_for_non_hca()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Only Holding Company Admins can access multiple companies');

        $this->companyService->getCompaniesForUser($this->regularUser);
    }

    public function test_get_companies_for_user_returns_paginated_data_for_hca()
    {
        $mockPaginator = Mockery::mock(LengthAwarePaginator::class);
        
        $this->mockRepository
            ->shouldReceive('getPaginatedCompanies')
            ->once()
            ->with($this->hcaUser, [], 15)
            ->andReturn($mockPaginator);

        $result = $this->companyService->getCompaniesForUser($this->hcaUser);

        $this->assertSame($mockPaginator, $result);
    }

    public function test_get_company_by_id_throws_exception_when_not_found()
    {
        $this->mockRepository
            ->shouldReceive('getCompanyById')
            ->once()
            ->with(1, $this->hcaUser)
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Company not found or you do not have permission to access it');

        $this->companyService->getCompanyById(1, $this->hcaUser);
    }

    public function test_get_company_by_id_returns_company_when_found()
    {
        $mockCompany = Mockery::mock(Company::class);
        
        $this->mockRepository
            ->shouldReceive('getCompanyById')
            ->once()
            ->with(1, $this->hcaUser)
            ->andReturn($mockCompany);

        $result = $this->companyService->getCompanyById(1, $this->hcaUser);

        $this->assertSame($mockCompany, $result);
    }

    public function test_create_company_throws_exception_for_non_hca()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Only Holding Company Admins can create companies');

        $this->companyService->createCompany(['name' => 'Test'], $this->regularUser);
    }

    public function test_create_company_throws_exception_for_expired_trial()
    {
        $expiredUser = Mockery::mock(User::class);
        $expiredUser->shouldReceive('isHoldingCompanyAdmin')->andReturn(true);
        $expiredUser->shouldReceive('hasActiveTrial')->andReturn(false);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Your trial has expired. Please upgrade to create new companies.');

        $this->companyService->createCompany(['name' => 'Test'], $expiredUser);
    }

    public function test_create_company_throws_exception_when_limit_reached()
    {
        // Mock the hasReachedCompanyLimit method to return true
        $limitedUser = Mockery::mock(User::class);
        $limitedUser->shouldReceive('isHoldingCompanyAdmin')->andReturn(true);
        $limitedUser->shouldReceive('hasActiveTrial')->andReturn(true);
        $limitedUser->id = 1;
        $limitedUser->default_company_id = null;

        $this->mockRepository
            ->shouldReceive('getActiveCompaniesCount')
            ->once()
            ->with($limitedUser)
            ->andReturn(3);

        // Mock config to return 3 as limit
        if (!function_exists('config')) {
            function config($key, $default = null) {
                if ($key === 'app.trial_company_limit') {
                    return 3;
                }
                return $default;
            }
        }

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Trial users are limited to 3 companies');

        $this->companyService->createCompany(['name' => 'Test'], $limitedUser);
    }

    public function test_search_companies_throws_exception_for_non_hca()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Only Holding Company Admins can search companies');

        $this->companyService->searchCompanies('test', $this->regularUser);
    }

    public function test_search_companies_returns_collection_for_hca()
    {
        $mockCollection = Mockery::mock(Collection::class);
        
        $this->mockRepository
            ->shouldReceive('searchCompanies')
            ->once()
            ->with($this->hcaUser, 'test', [])
            ->andReturn($mockCollection);

        $result = $this->companyService->searchCompanies('test', $this->hcaUser);

        $this->assertSame($mockCollection, $result);
    }

    public function test_get_companies_for_selection_throws_exception_for_non_hca()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Only Holding Company Admins can access multiple companies');

        $this->companyService->getCompaniesForSelection($this->regularUser);
    }

    public function test_get_companies_for_selection_returns_collection_for_hca()
    {
        $mockCollection = Mockery::mock(Collection::class);
        
        $this->mockRepository
            ->shouldReceive('getCompaniesForUser')
            ->once()
            ->with($this->hcaUser)
            ->andReturn($mockCollection);

        $result = $this->companyService->getCompaniesForSelection($this->hcaUser);

        $this->assertSame($mockCollection, $result);
    }

    public function test_bulk_update_company_status_throws_exception_for_non_hca()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Only Holding Company Admins can bulk update companies');

        $this->companyService->bulkUpdateCompanyStatus([1, 2], true, $this->regularUser);
    }

    public function test_bulk_update_company_status_returns_count_for_hca()
    {
        $this->mockRepository
            ->shouldReceive('bulkUpdateStatus')
            ->once()
            ->with([1, 2], $this->hcaUser, true)
            ->andReturn(2);

        $result = $this->companyService->bulkUpdateCompanyStatus([1, 2], true, $this->hcaUser);

        $this->assertEquals(2, $result);
    }

    public function test_has_reached_company_limit_returns_true_when_limit_exceeded()
    {
        $this->mockRepository
            ->shouldReceive('getActiveCompaniesCount')
            ->once()
            ->with($this->hcaUser)
            ->andReturn(5);

        // Mock config to return 3 as limit
        if (!function_exists('config')) {
            function config($key, $default = null) {
                if ($key === 'app.trial_company_limit') {
                    return 3;
                }
                return $default;
            }
        }

        $result = $this->companyService->hasReachedCompanyLimit($this->hcaUser);

        $this->assertTrue($result);
    }

    public function test_has_reached_company_limit_returns_false_when_under_limit()
    {
        $this->mockRepository
            ->shouldReceive('getActiveCompaniesCount')
            ->once()
            ->with($this->hcaUser)
            ->andReturn(2);

        $result = $this->companyService->hasReachedCompanyLimit($this->hcaUser);

        $this->assertFalse($result);
    }

    public function test_get_dashboard_stats_throws_exception_for_non_hca()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Only Holding Company Admins can access dashboard statistics');

        $this->companyService->getDashboardStats($this->regularUser);
    }

    public function test_set_default_company_throws_exception_when_company_not_found()
    {
        $this->mockRepository
            ->shouldReceive('companyExistsForUser')
            ->once()
            ->with(1, $this->hcaUser)
            ->andReturn(false);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Company not found or you do not have permission to set it as default');

        $this->companyService->setDefaultCompany(1, $this->hcaUser);
    }

    public function test_get_company_by_slug_throws_exception_when_not_found()
    {
        $this->mockRepository
            ->shouldReceive('getCompanyBySlug')
            ->once()
            ->with('test-slug', $this->hcaUser)
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Company not found or you do not have permission to access it');

        $this->companyService->getCompanyBySlug('test-slug', $this->hcaUser);
    }

    public function test_get_company_by_slug_returns_company_when_found()
    {
        $mockCompany = Mockery::mock(Company::class);
        
        $this->mockRepository
            ->shouldReceive('getCompanyBySlug')
            ->once()
            ->with('test-slug', $this->hcaUser)
            ->andReturn($mockCompany);

        $result = $this->companyService->getCompanyBySlug('test-slug', $this->hcaUser);

        $this->assertSame($mockCompany, $result);
    }
}