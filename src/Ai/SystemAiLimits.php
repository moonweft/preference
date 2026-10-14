<?php

declare(strict_types=1);

namespace Moonweft\Preference\Ai;

use MoonWeft\Ai\Services\RequestLimits;
use Spatie\LaravelSettings\SettingsRepositories\DatabaseSettingsRepository;

final class SystemAiLimits extends RequestLimits
{
    public function all(): array
    {
        $limits = app(AiConfiguration::class)->baselineLimits();
        $repository = (new AiSettings)->getRepository();

        if ($repository instanceof DatabaseSettingsRepository) {
            $model = $repository->getBuilder()->getModel();

            if (! $model->getConnection()->getSchemaBuilder()->hasTable($model->getTable())) {
                return $limits;
            }
        }

        if (! $repository->checkIfPropertyExists(AiSettings::group(), 'limits')) {
            return $limits;
        }

        // Preflight checks need only limits, not decrypted provider credentials.
        return array_replace($limits, (array) $repository->getPropertyPayload(AiSettings::group(), 'limits'));
    }
}
