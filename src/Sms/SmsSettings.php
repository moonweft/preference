<?php

declare(strict_types=1);

namespace Moonweft\Preference\Sms;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Moonweft\Preference\PreferencePermissionContributor;
use Moonweft\Preference\SettingsAccess;
use Overtrue\EasySms\Strategies\OrderStrategy;
use Spatie\LaravelSettings\Settings;

/** Platform SMS transport configuration, shared by all organisations; credentials are not public. */
final class SmsSettings extends Settings
{
    public const GATEWAYS = [
        'aliyun' => [
            'label' => 'moonweft-preference::sms.providers.aliyun',
            'fields' => ['access_key_id' => 'moonweft-preference::sms.fields.access_key_id', 'access_key_secret' => 'moonweft-preference::sms.fields.access_key_secret', 'sign_name' => 'moonweft-preference::sms.fields.sign_name'],
            'secrets' => ['access_key_id', 'access_key_secret'],
        ],
        'qcloud' => [
            'label' => 'moonweft-preference::sms.providers.qcloud',
            'fields' => ['sdk_app_id' => 'moonweft-preference::sms.fields.sdk_app_id', 'secret_id' => 'moonweft-preference::sms.fields.secret_id', 'secret_key' => 'moonweft-preference::sms.fields.secret_key', 'sign_name' => 'moonweft-preference::sms.fields.sign_name'],
            'secrets' => ['secret_id', 'secret_key'],
        ],
        'qiniu' => [
            'label' => 'moonweft-preference::sms.providers.qiniu',
            'fields' => ['access_key' => 'moonweft-preference::sms.fields.access_key', 'secret_key' => 'moonweft-preference::sms.fields.secret_key'],
            'secrets' => ['access_key', 'secret_key'],
        ],
        'yunpian' => [
            'label' => 'moonweft-preference::sms.providers.yunpian',
            'fields' => ['api_key' => 'moonweft-preference::sms.fields.api_key', 'signature' => 'moonweft-preference::sms.fields.signature'],
            'secrets' => ['api_key'],
            'optional' => ['signature'],
        ],
    ];

    public bool $enabled = false;

    public string $default_gateway = 'aliyun';

    public ?string $backup_gateway = null;

    public bool $failover_enabled = false;

    public float $timeout = 5.0;

    /** Gateway names mapped to their credential and signature fields. */
    public array $providers = [];

    public static function group(): string
    {
        return 'preference_sms';
    }

    public static function encrypted(): array
    {
        return ['providers'];
    }

    /** Only IAM platform administrators with the SMS permission may change shared credentials. */
    public function save(): self
    {
        $principal = app(SettingsAccess::class)->principal(PreferencePermissionContributor::MANAGE_SMS);
        abort_if($principal === null, 403);
        $this->validateConfiguration();

        return DB::transaction(function () use ($principal): self {
            parent::save();
            $audit = [
                'principal' => $principal->identity(), 'scope' => 'host', 'enabled' => $this->enabled,
                'default_gateway' => $this->default_gateway, 'backup_gateway' => $this->backup_gateway,
                'failover_enabled' => $this->failover_enabled, 'timeout' => $this->timeout,
                'configured_gateways' => array_keys($this->providers),
            ];
            DB::afterCommit(static fn () => Log::info('preference.sms.updated', $audit));

            return $this;
        });
    }

    /** Trusted backend callers own recipient authorisation, templates, rate limits and delivery tracking. */
    public function configuration(): array
    {
        if (! $this->enabled) {
            throw new \LogicException(__('moonweft-preference::sms.errors.disabled'));
        }
        $this->validateConfiguration();

        $gateways = $this->failover_enabled ? [$this->default_gateway, $this->backup_gateway] : [$this->default_gateway];

        return [
            'timeout' => $this->timeout,
            'default' => ['strategy' => OrderStrategy::class, 'gateways' => $gateways],
            'gateways' => array_intersect_key($this->providers, array_flip($gateways)),
        ];
    }

    private function validateConfiguration(): void
    {
        $rules = [
            'enabled' => ['required', 'boolean'],
            'default_gateway' => ['required', Rule::in(array_keys(self::GATEWAYS))],
            'backup_gateway' => [Rule::requiredIf($this->failover_enabled), 'nullable', Rule::in(array_keys(self::GATEWAYS)), 'different:default_gateway'],
            'failover_enabled' => ['required', 'boolean'],
            'timeout' => ['required', 'numeric', 'between:1,60'],
            'providers' => ['array:'.implode(',', array_keys(self::GATEWAYS))],
        ];
        $attributes = [];
        foreach (array_keys($rules) as $field) {
            $attributes[$field] = __('moonweft-preference::sms.fields.'.$field);
        }

        foreach (self::GATEWAYS as $gateway => $definition) {
            $attributes['providers.'.$gateway] = __($definition['label']);
            $rules['providers.'.$gateway] = ['sometimes', 'array:'.implode(',', array_keys($definition['fields']))];
            foreach ($definition['fields'] as $field => $label) {
                $rules["providers.{$gateway}.{$field}"] = [
                    $this->enabled && ($this->default_gateway === $gateway || ($this->failover_enabled && $this->backup_gateway === $gateway)) && ! in_array($field, $definition['optional'] ?? [], true) ? 'required' : 'sometimes',
                    'string', 'max:4096',
                ];
                $attributes["providers.{$gateway}.{$field}"] = __($definition['label']).' '.__($label);
            }
        }

        Validator::make($this->toArray(), $rules, attributes: $attributes)->validate();
    }
}
