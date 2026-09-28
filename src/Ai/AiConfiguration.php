<?php

declare(strict_types=1);

namespace Moonweft\Preference\Ai;

use Closure;
use Fiber;
use Laravel\Ai\AiManager;
use LogicException;
use Spatie\LaravelSettings\SettingsRepositories\DatabaseSettingsRepository;

final class AiConfiguration
{
    /** @var array<string, mixed>|null */
    private ?array $original = null;

    private ?object $owner = null;

    /** @return array<string, mixed> */
    public function baseline(): array
    {
        return $this->original ??= config('ai', []);
    }

    /** Complete lazy SDK streams inside the callback before returning. */
    public function run(Closure $callback): mixed
    {
        $owner = Fiber::getCurrent() ?? $this;
        if ($this->owner !== null && $this->owner !== $owner) {
            throw new LogicException('System AI configuration cannot be shared by overlapping Fiber executions.');
        }

        $previousOwner = $this->owner;
        $previous = config('ai', []);
        $this->owner = $owner;

        try {
            $this->apply();

            return $callback();
        } finally {
            $this->owner = $previousOwner;
            $this->useConfiguration($previous);
        }
    }

    public function apply(): void
    {
        $config = $this->baseline();
        $settings = new AiSettings;
        $repository = $settings->getRepository();

        if ($repository instanceof DatabaseSettingsRepository) {
            $model = $repository->getBuilder()->getModel();

            if (! $model->getConnection()->getSchemaBuilder()->hasTable($model->getTable())) {
                $this->useConfiguration($config);

                return;
            }
        }

        foreach ($settings->defaults as $capability => $provider) {
            if (isset(ProviderCatalog::CAPABILITIES[$capability])) {
                $config[ProviderCatalog::CAPABILITIES[$capability]['config']] = $provider;
            }
        }

        foreach ($settings->providers as $name => $overrides) {
            if (isset($config['providers'][$name])) {
                $config['providers'][$name] = array_replace_recursive($config['providers'][$name], $overrides);
            }
        }

        $this->useConfiguration($config);
    }

    private function useConfiguration(array $config): void
    {
        $providers = array_unique([...array_keys(config('ai.providers', [])), ...array_keys($config['providers'] ?? [])]);
        config(['ai' => $config]);

        if (app()->resolved(AiManager::class)) {
            app(AiManager::class)->forgetInstance($providers);
        }
    }
}
