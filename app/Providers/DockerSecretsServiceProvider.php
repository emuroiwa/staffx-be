<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class DockerSecretsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Load secrets from Docker secret files
        $this->loadDockerSecrets();
    }

    /**
     * Load secrets from Docker secret files into environment
     */
    private function loadDockerSecrets(): void
    {
        $secrets = [
            'DB_PASSWORD' => env('DB_PASSWORD_FILE'),
            'REDIS_PASSWORD' => env('REDIS_PASSWORD_FILE'),
            'JWT_SECRET' => env('JWT_SECRET_FILE'),
        ];

        foreach ($secrets as $envKey => $secretFile) {
            if ($secretFile && file_exists($secretFile)) {
                $secretValue = trim(file_get_contents($secretFile));
                $_ENV[$envKey] = $secretValue;
                $_SERVER[$envKey] = $secretValue;
                putenv("{$envKey}={$secretValue}");
            }
        }
    }
}