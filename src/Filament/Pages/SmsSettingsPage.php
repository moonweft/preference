<?php

declare(strict_types=1);

namespace Moonweft\Preference\Filament\Pages;

use BackedEnum;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Moonweft\Preference\Filament\Schemas\SmsSettingsForm;
use Moonweft\Preference\PreferencePermissionContributor;
use Moonweft\Preference\SettingsAccess;
use Moonweft\Preference\Sms\SmsSettings;
use Spatie\LaravelSettings\SettingsRepositories\DatabaseSettingsRepository;
use Spatie\LaravelSettings\Support\Crypto;
use UnitEnum;

final class SmsSettingsPage extends SettingsPage
{
    protected static string $settings = SmsSettings::class;

    protected static ?string $title = '短信配置';

    protected static ?string $navigationLabel = '短信';

    protected static string|UnitEnum|null $navigationGroup = '偏好配置';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $slug = 'system/sms';

    protected static ?int $navigationSort = 120;

    protected ?bool $hasDatabaseTransactions = true;

    #[Locked]
    public string $editingPrincipal = '';

    #[Locked]
    public string $settingsVersion = '';

    #[Locked]
    public bool $configurationUnavailable = false;

    public static function canAccess(): bool
    {
        return app(SettingsAccess::class)->principal(PreferencePermissionContributor::MANAGE_SMS) !== null;
    }

    public function mount(): void
    {
        $principal = app(SettingsAccess::class)->principal(PreferencePermissionContributor::MANAGE_SMS);
        abort_if($principal === null, 403);
        $this->editingPrincipal = $principal->identity();
        parent::mount();
    }

    public function hydrate(): void
    {
        abort_unless($this->canEdit(), 403);
    }

    public function canEdit(): bool
    {
        $principal = app(SettingsAccess::class)->principal(PreferencePermissionContributor::MANAGE_SMS);

        return ! $this->configurationUnavailable && $principal !== null && hash_equals($this->editingPrincipal, $principal->identity());
    }

    protected function fillForm(): void
    {
        app()->forgetInstance(SmsSettings::class);
        try {
            parent::fillForm();
        } catch (DecryptException) {
            $this->configurationUnavailable = true;
            $this->data = [];
        }
    }

    public function form(Schema $schema): Schema
    {
        if ($this->configurationUnavailable) {
            return $schema->components([
                Section::make('短信配置暂时无法读取')->description('已保存的凭据无法解密，请联系运维检查应用加密密钥或恢复配置备份，修复后刷新页面。'),
            ]);
        }

        return SmsSettingsForm::configure($schema);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->settingsVersion = $this->fingerprint($data);
        foreach (SmsSettings::GATEWAYS as $gateway => $definition) {
            foreach ($definition['secrets'] as $field) {
                unset($data['providers'][$gateway][$field]);
            }
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $current = (new SmsSettings)->providers;
        $providers = [];
        foreach (SmsSettings::GATEWAYS as $gateway => $definition) {
            foreach ($definition['fields'] as $field => $label) {
                $value = trim((string) ($data['providers'][$gateway][$field] ?? ''));
                if (in_array($field, $definition['secrets'], true)) {
                    if ($data['providers'][$gateway]['remove_credentials'] ?? false) {
                        continue;
                    }
                    $value = filled($value) ? $value : ($current[$gateway][$field] ?? '');
                }
                if (filled($value)) {
                    $providers[$gateway][$field] = $value;
                }
            }
        }

        return [
            'enabled' => (bool) $data['enabled'], 'default_gateway' => $data['default_gateway'],
            'backup_gateway' => filled($data['backup_gateway'] ?? null) ? $data['backup_gateway'] : null,
            'failover_enabled' => (bool) $data['failover_enabled'],
            'timeout' => (float) $data['timeout'], 'providers' => $providers,
        ];
    }

    protected function beforeSave(): void
    {
        $repository = app(SmsSettings::class)->getRepository();
        if (! $repository instanceof DatabaseSettingsRepository) {
            throw new \LogicException('SMS settings require a database settings repository.');
        }
        $decoder = config('settings.decoder') ?? static fn (string $payload): mixed => json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        $current = [];
        foreach ($repository->getBuilder()->where('group', SmsSettings::group())->lockForUpdate()->get(['name', 'payload']) as $row) {
            $value = $decoder($row->getAttribute('payload'), true);
            $current[$row->getAttribute('name')] = $row->getAttribute('name') === 'providers' ? Crypto::decrypt($value) : $value;
        }
        if (! hash_equals($this->settingsVersion, $this->fingerprint($current))) {
            throw ValidationException::withMessages(['data' => '短信配置已被其他管理员修改，请刷新页面后重试。']);
        }
    }

    public function save(): void
    {
        abort_unless($this->canEdit(), 403);
        try {
            parent::save();
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())
                ->mapWithKeys(static fn (array $messages, string $key): array => [str_starts_with($key, 'data') ? $key : 'data.'.$key => $messages])->all());
        }
        $this->fillForm();
        $this->rememberData();
    }

    private function fingerprint(array $data): string
    {
        ksort($data);

        return hash_hmac('sha256', json_encode($data, JSON_THROW_ON_ERROR), (string) config('app.key'));
    }
}
