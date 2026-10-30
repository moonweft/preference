<?php

declare(strict_types=1);

namespace Moonweft\Preference\Filament\Actions;

use Filament\Actions\Action;
use Moonweft\Preference\Filament\Pages\ThemeSettingsPage;

final class ResetTypographyAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'resetTypography';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->label(__('moonweft-preference::theme.reset_typography'))
            ->action(static function (ThemeSettingsPage $livewire): void {
                $livewire->data['font_scale'] = 125;
            });
    }
}
