<?php

declare(strict_types=1);

namespace Moonweft\Preference\Filament\Schemas;

use Filament\Forms\Components\Radio;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Moonweft\Preference\Filament\Actions\ResetTypographyAction;
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
                        ->options(['sharp' => 'Sharp', 'orbit' => 'Orbit'])->live()
                        ->view('moonweft-preference::forms.appearance-cards', ['kind' => 'theme'])->required(),
                ])->columnSpanFull(),
            Section::make(__('moonweft-preference::theme.sections.typography'))->key('typography')
                ->description(__('moonweft-preference::theme.typography_description'))
                ->headerActions([ResetTypographyAction::make()])
                ->schema([
                    Radio::make('font_scale')->label(__('moonweft-preference::theme.fields.font_scale'))
                        ->options(array_combine(Appearance::FONT_SCALES, array_map(
                            static fn (int $scale): string => $scale.'%', Appearance::FONT_SCALES,
                        )))
                        ->view('moonweft-preference::forms.appearance-cards', ['kind' => 'typography'])
                        ->viewData(static fn (Get $get): array => ['theme' => $get('panel_theme')])
                        ->required(),
                ])->columnSpanFull(),
            Section::make(__('moonweft-preference::theme.sections.colors'))
                ->description(__('moonweft-preference::theme.colors_description'))
                ->schema([
                    Radio::make('color_scheme')->label(__('moonweft-preference::theme.fields.color_scheme'))
                        ->options(static fn (): array => array_intersect_key(__('moonweft-preference::theme.schemes'), Appearance::colorSchemes()))
                        ->view('moonweft-preference::forms.appearance-cards', ['kind' => 'palette'])->required(),
                ])->columnSpanFull(),
        ]);
    }
}
