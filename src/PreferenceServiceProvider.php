<?php

declare(strict_types=1);

namespace Moonweft\Preference;

use Illuminate\Support\ServiceProvider;
use MoonWeft\Ai\Contracts\ExecutionMiddleware;
use MoonWeft\Ai\Services\RequestLimits;
use MoonWeft\Iam\Contracts\PermissionContributor;
use Moonweft\Preference\Ai\AiConfiguration;
use Moonweft\Preference\Ai\AiSettings;
use Moonweft\Preference\Ai\ConfigureAiExecution;
use Moonweft\Preference\Ai\SystemAiLimits;

class PreferenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AiConfiguration::class);
        $this->app->scoped(AiSettings::class);
        $this->app->bind(RequestLimits::class, SystemAiLimits::class);
        $this->app->tag([PreferencePermissionContributor::class], PermissionContributor::class);
        $this->app->tag([ConfigureAiExecution::class], ExecutionMiddleware::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/settings');

        $this->app->booted(fn () => $this->app->make(AiConfiguration::class)->baseline());
    }
}
