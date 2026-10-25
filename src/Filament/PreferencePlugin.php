<?php

declare(strict_types=1);

namespace Moonweft\Preference\Filament;

use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\Support\Assets\Theme;
use Illuminate\Foundation\Vite;
use Moonweft\Preference\Filament\Pages\AiSettingsPage;
use Moonweft\Preference\Filament\Pages\SmsSettingsPage;
use Moonweft\Preference\Filament\Pages\SystemSettingsPage;
use Moonweft\Preference\Filament\Pages\ThemeSettingsPage;
use Moonweft\Preference\System\Appearance;
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
        NavigationGroup::configureUsing(static function (NavigationGroup $group): void {
            if ($group->getLabel() === 'preferences') {
                $group->label(static fn (): string => __('moonweft-preference::navigation.group'));
            }
        });

        $panel->pages([SystemSettingsPage::class, ThemeSettingsPage::class, AiSettingsPage::class, SmsSettingsPage::class])
            ->theme(Theme::make('system-appearance')->html(static fn () => app(Vite::class)(
                Appearance::THEMES[app(SystemSettings::class)->panel_theme],
            )))
            ->topbar(static fn (): bool => app(SystemSettings::class)->panel_theme !== 'sharp')
            ->colors(static fn (): array => app(SystemSettings::class)->colors());
    }

    public function boot(Panel $panel): void {}
}
