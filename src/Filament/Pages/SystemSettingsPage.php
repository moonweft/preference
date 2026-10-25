<?php

declare(strict_types=1);

namespace Moonweft\Preference\Filament\Pages;

use BackedEnum;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Moonweft\Preference\Filament\Schemas\SystemSettingsForm;
use Moonweft\Preference\PreferencePermissionContributor;
use Moonweft\Preference\SettingsAccess;
use Moonweft\Preference\System\SystemSettings;
use Spatie\LaravelSettings\SettingsRepositories\DatabaseSettingsRepository;

class SystemSettingsPage extends SettingsPage
{
    protected static string $settings = SystemSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $slug = 'system/settings';

    protected static ?int $navigationSort = 50;

    protected ?bool $hasDatabaseTransactions = true;

    #[Locked]
    public string $editingPrincipal = '';

    #[Locked]
    public string $settingsVersion = '';

    public function getTitle(): string
    {
        return __('moonweft-preference::system.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('moonweft-preference::system.navigation_label');
    }

    public static function getNavigationGroup(): string
    {
        return 'preferences';
    }

    public function getRedirectUrl(): ?string
    {
        return static::getUrl();
    }

    public static function canAccess(): bool
    {
        return app(SettingsAccess::class)->principal(PreferencePermissionContributor::MANAGE_SYSTEM) !== null;
    }

    public function mount(): void
    {
        $principal = app(SettingsAccess::class)->principal(PreferencePermissionContributor::MANAGE_SYSTEM);
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
        $principal = app(SettingsAccess::class)->principal(PreferencePermissionContributor::MANAGE_SYSTEM);

        return $principal !== null && hash_equals($this->editingPrincipal, $principal->identity());
    }

    public function form(Schema $schema): Schema
    {
        return SystemSettingsForm::configure($schema);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->settingsVersion = $this->fingerprint($data);

        return $data;
    }

    protected function beforeSave(): void
    {
        $repository = app(SystemSettings::class)->getRepository();
        if (! $repository instanceof DatabaseSettingsRepository) {
            throw new \LogicException('System settings require a database settings repository.');
        }

        $decoder = config('settings.decoder') ?? static fn (string $payload): mixed => json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        $current = [];
        foreach ($repository->getBuilder()->where('group', SystemSettings::group())->lockForUpdate()->get(['name', 'payload']) as $row) {
            $current[$row->getAttribute('name')] = $decoder($row->getAttribute('payload'), true);
        }
        if (! hash_equals($this->settingsVersion, $this->fingerprint($current))) {
            throw ValidationException::withMessages(['data' => __('moonweft-preference::system.errors.stale')]);
        }
    }

    public function save(): void
    {
        abort_unless($this->canEdit(), 403);
        parent::save();
        $this->fillForm();
        $this->rememberData();
    }

    private function fingerprint(array $data): string
    {
        ksort($data);

        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }
}
