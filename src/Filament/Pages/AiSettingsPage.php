<?php

declare(strict_types=1);

namespace Moonweft\Preference\Filament\Pages;

use BackedEnum;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use MoonWeft\Ai\Services\RequestLimits;
use Moonweft\Preference\Ai\AiSettings;
use Moonweft\Preference\Ai\ProviderCatalog;
use Moonweft\Preference\Filament\Schemas\AiSettingsForm;
use Moonweft\Preference\PreferencePermissionContributor;
use Moonweft\Preference\SettingsAccess;
use Spatie\LaravelSettings\SettingsRepositories\DatabaseSettingsRepository;
use Spatie\LaravelSettings\Support\Crypto;

final class AiSettingsPage extends SettingsPage
{
    protected static string $settings = AiSettings::class;

    protected static ?string $navigationLabel = 'AI';

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

    public function getTitle(): string
    {
        return __('moonweft-preference::ai.title');
    }

    public static function getNavigationGroup(): string
    {
        return 'preferences';
    }

    public static function canAccess(): bool
    {
        return app(SettingsAccess::class)->principal(PreferencePermissionContributor::MANAGE_AI) !== null;
    }

    public function mount(): void
    {
        $principal = app(SettingsAccess::class)->principal(PreferencePermissionContributor::MANAGE_AI);
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
        $principal = app(SettingsAccess::class)->principal(PreferencePermissionContributor::MANAGE_AI);

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
        return AiSettingsForm::configure($schema, $this->configurationUnavailable);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->settingsVersion = $this->fingerprint($data);

        $data['providers'] = array_intersect_key($data['providers'], app(ProviderCatalog::class)->options());
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
            'limits' => array_map(intval(...), array_filter(Arr::only($data['limits'] ?? [], array_keys(RequestLimits::DEFAULTS)), filled(...))),
        ];
    }

    protected function beforeSave(): void
    {
        $repository = app(AiSettings::class)->getRepository();

        if (! $repository instanceof DatabaseSettingsRepository) {
            throw new \LogicException(__('moonweft-preference::ai.errors.database_required'));
        }

        $decoder = config('settings.decoder') ?? static fn (string $payload, bool $associative): mixed => json_decode($payload, $associative, flags: JSON_THROW_ON_ERROR);
        $current = ['defaults' => [], 'providers' => [], 'limits' => []];

        // Compare the locking read itself; a later select may use an older MySQL snapshot.
        foreach ($repository->getBuilder()->where('group', AiSettings::group())->lockForUpdate()->get(['name', 'payload']) as $row) {
            $name = $row->getAttribute('name');
            $value = $decoder($row->getAttribute('payload'), true);
            $current[$name] = in_array($name, AiSettings::encrypted(), true) ? Crypto::decrypt($value) : $value;
        }

        if (! hash_equals($this->settingsVersion, $this->fingerprint($current))) {
            throw ValidationException::withMessages(['data' => __('moonweft-preference::ai.errors.stale')]);
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
