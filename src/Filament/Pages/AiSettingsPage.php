<?php

declare(strict_types=1);

namespace Moonweft\Preference\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Moonweft\Preference\Ai\AiSettings;
use Moonweft\Preference\Ai\AiSettingsAccess;
use Moonweft\Preference\Ai\ProviderCatalog;
use Spatie\LaravelSettings\SettingsRepositories\DatabaseSettingsRepository;
use Spatie\LaravelSettings\Support\Crypto;
use UnitEnum;

final class AiSettingsPage extends SettingsPage
{
    protected static string $settings = AiSettings::class;

    protected static ?string $title = 'AI 配置';

    protected static ?string $navigationLabel = 'AI 配置';

    protected static string|UnitEnum|null $navigationGroup = '系统配置';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $slug = 'system/ai';

    protected static ?int $navigationSort = 100;

    protected ?bool $hasDatabaseTransactions = true;

    #[Locked]
    public string $settingsVersion = '';

    #[Locked]
    public string $editingPrincipal = '';

    #[Locked]
    public bool $configurationUnavailable = false;

    public static function canAccess(): bool
    {
        return app(AiSettingsAccess::class)->principal() !== null;
    }

    public function mount(): void
    {
        $principal = app(AiSettingsAccess::class)->principal();
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
        $principal = app(AiSettingsAccess::class)->principal();

        return ! $this->configurationUnavailable && $principal !== null && hash_equals($this->editingPrincipal, $principal->identity());
    }

    protected function fillForm(): void
    {
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
                Section::make('AI 配置暂时无法读取')
                    ->icon(Heroicon::OutlinedExclamationTriangle)
                    ->description('已保存的配置无法解密。请联系运维检查应用加密密钥或恢复配置备份，修复后刷新页面。当前配置未被修改。'),
            ]);
        }

        $catalog = app(ProviderCatalog::class);
        $defaults = [];

        foreach (ProviderCatalog::CAPABILITIES as $capability => $definition) {
            $defaults[] = Select::make("defaults.{$capability}")
                ->label($definition['label'])
                ->options($catalog->options($capability))
                ->placeholder('使用应用默认值')
                ->searchable();
        }

        $tabs = [
            Tab::make('默认配置')->key('defaults')->schema([
                Section::make('各项能力的默认服务商')
                    ->description('对整个系统生效。留空沿用应用默认值，业务中明确指定的服务商或模型优先。')
                    ->schema($defaults)->columns(2),
            ]),
        ];

        foreach ($catalog->options() as $name => $label) {
            $fields = [];

            foreach ($catalog->fields($name) as $field => $fieldLabel) {
                $input = TextInput::make("providers.{$name}.{$field}")
                    ->label($fieldLabel)
                    ->maxLength(4096)
                    ->placeholder('使用应用默认值');

                if ($field === 'key') {
                    $input->password()->revealable()->autocomplete('new-password')
                        ->placeholder(fn (): string => filled(app(AiSettings::class)->providers[$name]['key'] ?? null) ? '已保存，留空保留' : '未保存，留空使用环境配置');
                } elseif ($field === 'url') {
                    $input->url()->rules(['url:http,https'])->maxLength(2048)
                        ->helperText('填写完整接口根地址；留空使用应用默认地址。');
                } elseif ($field === 'models.embeddings.dimensions') {
                    $input->integer()->minValue(1)->maxValue(65536);
                }

                $fields[] = $input;
            }

            $fields[] = Toggle::make("providers.{$name}.remove_key")
                ->label('移除已保存的密钥')
                ->helperText('保存后恢复使用环境配置中的密钥。');

            $tabs[] = Tab::make($label)->key('provider-'.$name)->schema([
                Section::make($label)
                    ->description($catalog->driver($name) === 'bedrock' ? '可使用 Bearer Token；AWS 凭据链及角色配置沿用应用环境。' : null)
                    ->schema($fields)
                    ->columns(2),
            ]);
        }

        return $schema->components([
            Tabs::make('AI 配置')->tabs($tabs)->vertical()->columnSpanFull(),
        ]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->settingsVersion = $this->fingerprint($data);

        foreach ($data['providers'] as &$provider) {
            unset($provider['key']);
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $catalog = app(ProviderCatalog::class);
        $current = app(AiSettings::class)->providers;
        $providers = [];

        foreach ($catalog->options() as $name => $label) {
            foreach ($catalog->fields($name) as $field => $fieldLabel) {
                $value = Arr::get($data, "providers.{$name}.{$field}");
                $value = is_string($value) ? trim($value) : $value;

                if ($field === 'key') {
                    if (Arr::get($data, "providers.{$name}.remove_key", false)) {
                        continue;
                    }

                    $value = filled($value) ? $value : ($current[$name]['key'] ?? null);
                }

                if (filled($value)) {
                    Arr::set($providers, "{$name}.{$field}", $field === 'models.embeddings.dimensions' ? (int) $value : $value);
                }
            }
        }

        return [
            'defaults' => array_filter(Arr::only($data['defaults'] ?? [], array_keys(ProviderCatalog::CAPABILITIES)), filled(...)),
            'providers' => $providers,
        ];
    }

    protected function beforeSave(): void
    {
        $repository = app(AiSettings::class)->getRepository();

        if (! $repository instanceof DatabaseSettingsRepository) {
            throw new \LogicException('AI settings require a database settings repository.');
        }

        $decoder = config('settings.decoder') ?? static fn (string $payload, bool $associative): mixed => json_decode($payload, $associative, flags: JSON_THROW_ON_ERROR);
        $current = ['defaults' => [], 'providers' => []];

        // Compare the locking read itself; a later select may use an older MySQL snapshot.
        foreach ($repository->getBuilder()->where('group', AiSettings::group())->lockForUpdate()->get(['name', 'payload']) as $row) {
            $name = $row->getAttribute('name');
            $value = $decoder($row->getAttribute('payload'), true);
            $current[$name] = in_array($name, AiSettings::encrypted(), true) ? Crypto::decrypt($value) : $value;
        }

        if (! hash_equals($this->settingsVersion, $this->fingerprint($current))) {
            throw ValidationException::withMessages(['data' => 'AI 配置已被其他管理员修改，请刷新页面后重试。']);
        }
    }

    public function save(): void
    {
        abort_unless($this->canEdit(), 403);

        try {
            parent::save();
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())
                ->mapWithKeys(static fn (array $messages, string $key): array => [str_starts_with($key, 'data') ? $key : 'data.'.$key => $messages])
                ->all());
        }

        $this->fillForm();
        $this->rememberData();
    }

    private function fingerprint(array $data): string
    {
        return hash_hmac('sha256', json_encode($data, JSON_THROW_ON_ERROR), (string) config('app.key'));
    }
}
