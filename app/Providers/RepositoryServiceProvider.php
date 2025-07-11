<?php

namespace App\Providers;

use App\Repositories\CompanyRepository;
use App\Repositories\Auth\AuthRepository;
use App\Services\CompanyService;
use App\Services\Auth\AuthService;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind repositories
        $this->app->bind(CompanyRepository::class, function () {
            return new CompanyRepository();
        });

        $this->app->bind(AuthRepository::class, function () {
            return new AuthRepository();
        });

        // Bind services
        $this->app->bind(CompanyService::class, function ($app) {
            return new CompanyService(
                $app->make(CompanyRepository::class)
            );
        });

        $this->app->bind(AuthService::class, function ($app) {
            return new AuthService(
                $app->make(AuthRepository::class),
                $app->make(CompanyService::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}