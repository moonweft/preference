<?php

declare(strict_types=1);

namespace Moonweft\Preference\Filament\Schemas;

use Filament\Forms\Components\Radio;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Moonweft\Preference\System\Appearance;

final class ThemeSettingsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('moonweft-preference::theme.sections.theme'))
                ->description(__('moonweft-preference::theme.description'))
                ->schema([
                    Radio::make('panel_theme')->label(__('moonweft-preference::theme.fields.panel_theme'))
                        ->options(['sharp' => 'Sharp', 'orbit' => 'Orbit'])
                        ->view('moonweft-preference::forms.appearance-cards', ['kind' => 'theme'])->required(),
                ])->columnSpanFull(),
            Section::make(__('moonweft-preference::theme.sections.colors'))
                ->description(__('moonweft-preference::theme.colors_description'))
                ->schema([
                    Radio::make('color_scheme')->label(__('moonweft-preference::theme.fields.color_scheme'))
                        ->options(static fn (): array => array_intersect_key(__('moonweft-preference::theme.schemes'), Appearance::colorSchemes()))
                        ->view('moonweft-preference::forms.appearance-cards', ['kind' => 'palette'])->required(),
                ])->columnSpanFull(),
            Section::make(__('moonweft-preference::theme.sections.mode'))
                ->schema([
                    ToggleButtons::make('theme_mode')->label(__('moonweft-preference::theme.fields.theme_mode'))
                        ->options(__('moonweft-preference::theme.modes'))
                        ->icons(['system' => Heroicon::OutlinedComputerDesktop, 'light' => Heroicon::OutlinedSun, 'dark' => Heroicon::OutlinedMoon])
                        ->inline()->required(),
                ])->columnSpanFull(),
        ]);
    }
}
