<?php

declare(strict_types=1);

namespace Moonweft\Preference\Ai;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use MoonWeft\Ai\Services\RequestLimits;
use Spatie\LaravelSettings\Settings;

final class AiSettings extends Settings
{
    /** @var array<string, string> */
    public array $defaults = [];

    public array $providers = [];

    /** @var array<string, int> */
    public array $limits = [];

    public static function group(): string
    {
        return 'preference_ai';
    }

    public static function encrypted(): array
    {
        return ['providers'];
    }

    public function save(): self
    {
        $catalog = app(ProviderCatalog::class);
        $rules = [
            'defaults' => ['array:'.implode(',', array_keys(ProviderCatalog::CAPABILITIES))],
            'providers' => ['array:'.implode(',', array_keys($catalog->options()))],
            'limits' => ['array:'.implode(',', array_keys(RequestLimits::DEFAULTS))],
        ];

        foreach (array_keys(RequestLimits::DEFAULTS) as $name) {
            $rules['limits.'.$name] = ['sometimes', 'required', 'integer', 'between:1,2147483647'];
        }

        foreach (ProviderCatalog::CAPABILITIES as $capability => $definition) {
            $rules["defaults.{$capability}"] = ['sometimes', 'required', Rule::in(array_keys($catalog->options($capability)))];
        }

        foreach ($this->providers as $name => $values) {
            $fields = $catalog->fields($name);
            $allowed = array_unique(array_map(static fn (string $field): string => explode('.', $field)[0], array_keys($fields)));
            $rules["providers.{$name}"] = ['array:'.implode(',', $allowed)];

            foreach ($fields as $field => $label) {
                $rules["providers.{$name}.{$field}"] = match ($field) {
                    'url' => ['sometimes', 'required', 'string', 'url:http,https', 'max:2048'],
                    'models.embeddings.dimensions' => ['sometimes', 'required', 'integer', 'between:1,65536'],
                    default => ['sometimes', 'required', 'string', 'max:4096'],
                };
            }

            $modelCapabilities = array_keys(array_filter(ProviderCatalog::CAPABILITIES, fn (array $definition, string $capability): bool => $catalog->supports($name, $capability), ARRAY_FILTER_USE_BOTH));
            $rules["providers.{$name}.models"] = ['sometimes', 'array:'.implode(',', $modelCapabilities)];

            foreach ($modelCapabilities as $capability) {
                $rules["providers.{$name}.models.{$capability}"] = ['sometimes', 'array:'.($capability === 'embeddings' ? 'default,dimensions' : 'default')];
            }
        }

        $validator = Validator::make($this->toArray(), $rules);
        $validator->after(function ($validator) use ($catalog): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $baseline = app(AiConfiguration::class)->baseline();
            foreach (ProviderCatalog::CAPABILITIES as $capability => $definition) {
                $name = $this->defaults[$capability] ?? $baseline[$definition['config']] ?? null;
                if (! is_string($name) || $catalog->driver($name) !== 'openai-compatible') {
                    continue;
                }

                $effective = array_replace_recursive($baseline['providers'][$name] ?? [], $this->providers[$name] ?? []);

                foreach (['url', $catalog->modelPath($name, $capability)] as $field) {
                    if (blank(Arr::get($effective, $field))) {
                        $validator->errors()->add("providers.{$name}.{$field}", '作为默认服务商时，必须填写接口地址及对应模型。');
                    }
                }
            }
        });
        $validator->validate();

        return parent::save();
    }
}
