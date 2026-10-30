<?php

declare(strict_types=1);

namespace Moonweft\Preference\Filament\Pages;

use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Moonweft\Preference\Filament\Schemas\ThemeSettingsForm;
use Moonweft\Preference\System\Appearance;
use Moonweft\Preference\System\SystemSettings;

final class ThemeSettingsPage extends SystemSettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaintBrush;

    protected static ?string $slug = 'system/theme';

    protected static ?int $navigationSort = 51;

    public function getTitle(): string
    {
        return __('moonweft-preference::theme.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('moonweft-preference::theme.navigation_label');
    }

    public function form(Schema $schema): Schema
    {
        return ThemeSettingsForm::configure($schema);
    }

    public function getRedirectUrl(): ?string
    {
        return self::getUrl();
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [...parent::mutateFormDataBeforeFill($data),
            'color_scheme' => Appearance::selectedScheme(app(SystemSettings::class)),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $colors = Appearance::colorSchemes()[$data['color_scheme']];
        unset($data['color_scheme']);

        return [...$data, ...$colors];
    }
}
