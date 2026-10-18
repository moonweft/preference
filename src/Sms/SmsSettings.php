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
            'label' => '阿里云',
            'fields' => ['access_key_id' => 'Access Key ID', 'access_key_secret' => 'Access Key Secret', 'sign_name' => '短信签名'],
            'secrets' => ['access_key_id', 'access_key_secret'],
        ],
        'qcloud' => [
            'label' => '腾讯云',
            'fields' => ['sdk_app_id' => 'SDK App ID', 'secret_id' => 'Secret ID', 'secret_key' => 'Secret Key', 'sign_name' => '短信签名'],
            'secrets' => ['secret_id', 'secret_key'],
        ],
        'qiniu' => [
            'label' => '七牛云',
            'fields' => ['access_key' => 'Access Key', 'secret_key' => 'Secret Key'],
            'secrets' => ['access_key', 'secret_key'],
        ],
        'yunpian' => [
            'label' => '云片',
            'fields' => ['api_key' => 'API Key', 'signature' => '短信签名（选填，包含【】）'],
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
            throw new \LogicException('短信发送尚未启用，请先配置短信服务。');
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
        $attributes = ['backup_gateway' => '备用服务商', 'failover_enabled' => '自动切换'];

        foreach (self::GATEWAYS as $gateway => $definition) {
            $rules['providers.'.$gateway] = ['sometimes', 'array:'.implode(',', array_keys($definition['fields']))];
            foreach ($definition['fields'] as $field => $label) {
                $rules["providers.{$gateway}.{$field}"] = [
                    $this->enabled && ($this->default_gateway === $gateway || ($this->failover_enabled && $this->backup_gateway === $gateway)) && ! in_array($field, $definition['optional'] ?? [], true) ? 'required' : 'sometimes',
                    'string', 'max:4096',
                ];
                $attributes["providers.{$gateway}.{$field}"] = $definition['label'].' '.$label;
            }
        }

        Validator::make($this->toArray(), $rules, attributes: $attributes)->validate();
    }
}
