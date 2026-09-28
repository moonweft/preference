<?php

declare(strict_types=1);

namespace Moonweft\Preference\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Moonweft\Preference\Filament\Pages\AiSettingsPage;

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
        $panel->pages([AiSettingsPage::class]);
    }

    public function boot(Panel $panel): void {}
}
