<?php

declare(strict_types=1);

namespace Moonweft\Preference\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Moonweft\Preference\Filament\Pages\AiSettingsPage;
use Moonweft\Preference\Filament\Pages\SmsSettingsPage;
use Moonweft\Preference\Filament\Pages\SystemSettingsPage;
use Moonweft\Preference\System\SystemSettings;

final class PreferencePlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'moonweft-preference';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([SystemSettingsPage::class, AiSettingsPage::class, SmsSettingsPage::class])
            ->colors(static fn (): array => app(SystemSettings::class)->colors())
            ->darkMode(
                static fn (): bool => app(SystemSettings::class)->theme_mode !== 'light',
                isForced: static fn (): bool => app(SystemSettings::class)->theme_mode === 'dark',
            )
            ->themeSwitcher(false)
            ->renderHook(PanelsRenderHook::HEAD_START, static fn () => view('moonweft-preference::appearance', [
                'mode' => app(SystemSettings::class)->theme_mode,
            ]));
    }

    public function boot(Panel $panel): void {}
}
