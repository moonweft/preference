<?php

declare(strict_types=1);

namespace Moonweft\Preference\Filament\Pages;

use BackedEnum;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use MoonWeft\Iam\SecurityConfirmation;
use Moonweft\Preference\Filament\Schemas\SecuritySettingsForm;
use Moonweft\Preference\PreferencePermissionContributor;
use Moonweft\Preference\Security\SecuritySettings;
use Moonweft\Preference\SettingsAccess;
use Spatie\LaravelSettings\SettingsRepositories\DatabaseSettingsRepository;

class SecuritySettingsPage extends SettingsPage
{
    protected static string $settings = SecuritySettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $slug = 'security/settings';

    protected static ?int $navigationSort = 55;

    protected ?bool $hasDatabaseTransactions = true;

    #[Locked]
    public string $editingPrincipal = '';

    #[Locked]
    public string $settingsVersion = '';

    public function getTitle(): string
    {
        return __('moonweft-preference::security.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('moonweft-preference::security.navigation_label');
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
        return app(SettingsAccess::class)->principal(PreferencePermissionContributor::MANAGE_SECURITY) !== null;
    }

    public function mount(): void
    {
        $principal = app(SettingsAccess::class)->principal(PreferencePermissionContributor::MANAGE_SECURITY);
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
        $principal = app(SettingsAccess::class)->principal(PreferencePermissionContributor::MANAGE_SECURITY);

        return $principal !== null && hash_equals($this->editingPrincipal, $principal->identity());
    }

    public function form(Schema $schema): Schema
    {
        return SecuritySettingsForm::configure($schema);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->settingsVersion = $this->fingerprint($data);

        return $data;
    }

    protected function beforeSave(): void
    {
        try {
            if (filled($this->data['confirmation_password'] ?? null) || filled($this->data['confirmation'] ?? null)) {
                app(SecurityConfirmation::class)->verifyCurrentCredential(
                    $this->data['confirmation_password'] ?? null, $this->data['confirmation'] ?? null,
                );
            }
        } finally {
            $this->data['confirmation_password'] = null;
            $this->data['confirmation'] = null;
        }
        app(SecurityConfirmation::class)->assertRecentlyConfirmed();

        $repository = app(SecuritySettings::class)->getRepository();
        if (! $repository instanceof DatabaseSettingsRepository) {
            throw new \LogicException(__('moonweft-preference::security.errors.database_required'));
        }

        $decoder = config('settings.decoder') ?? static fn (string $payload): mixed => json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        $current = [];
        foreach ($repository->getBuilder()->where('group', SecuritySettings::group())->lockForUpdate()->get(['name', 'payload']) as $row) {
            $current[$row->getAttribute('name')] = $decoder($row->getAttribute('payload'), true);
        }
        if (! hash_equals($this->settingsVersion, $this->fingerprint($current))) {
            throw ValidationException::withMessages(['data' => __('moonweft-preference::security.errors.stale')]);
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
