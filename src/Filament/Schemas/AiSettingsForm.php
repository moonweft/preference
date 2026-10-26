<?php

declare(strict_types=1);

namespace Moonweft\Preference\Filament\Schemas;

use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use MoonWeft\Ai\Services\RequestLimits;
use MoonWeft\Components\Select\Forms\AdvancedSelect;
use MoonWeft\Components\Text\Forms\AdvancedTextInput;
use Moonweft\Preference\Ai\AiConfiguration;
use Moonweft\Preference\Ai\AiSettings;
use Moonweft\Preference\Ai\ProviderCatalog;

final class AiSettingsForm
{
    public static function configure(Schema $schema, bool $configurationUnavailable): Schema
    {
        if ($configurationUnavailable) {
            return $schema->components([
                Section::make(__('moonweft-preference::ai.errors.unavailable'))
                    ->icon(Heroicon::OutlinedExclamationTriangle)
                    ->description(__('moonweft-preference::ai.errors.unavailable_help')),
            ]);
        }

        $catalog = app(ProviderCatalog::class);
        $defaults = [];

        foreach (array_keys(ProviderCatalog::CAPABILITIES) as $capability) {
            $defaults[] = AdvancedSelect::make("defaults.{$capability}")
                ->label(__('moonweft-preference::ai.capabilities.'.$capability))
                ->options($catalog->options($capability))
                ->placeholder(__('moonweft-preference::ai.placeholders.application_default'))
                ->searchable();
        }

        $tabs = [
            Tab::make(__('moonweft-preference::ai.tabs.defaults'))->key('defaults')->schema([
                Section::make(__('moonweft-preference::ai.sections.defaults'))
                    ->description(__('moonweft-preference::ai.sections.defaults_help'))
                    ->schema($defaults)->columns(2),
            ]),
        ];

        $limits = [];
        foreach (array_keys(RequestLimits::RANGES) as $name) {
            $limits[] = AdvancedTextInput::make('limits.'.$name)
                ->label(__('moonweft-preference::ai.limits.'.$name.'.label'))->integer()->minValue(RequestLimits::RANGES[$name][0])->maxValue(RequestLimits::RANGES[$name][1])
                ->suffix(__('moonweft-preference::ai.limits.'.$name.'.unit'))
                ->placeholder((string) app(AiConfiguration::class)->baselineLimits()[$name])
                ->helperText(__('moonweft-preference::ai.limits.'.$name.'.help').' '.__('moonweft-preference::ai.help.limit_default'));
        }

        $tabs[] = Tab::make(__('moonweft-preference::ai.tabs.limits'))->key('limits')->schema([
            Section::make(__('moonweft-preference::ai.sections.development'))->schema([
                AdvancedSelect::make('limits.development_unlimited')->native()->label(__('moonweft-preference::ai.fields.development_unlimited'))
                    ->options(__('moonweft-preference::ai.development_options'))
                    ->placeholder(__('moonweft-preference::ai.placeholders.inherit_default'))
                    ->helperText(__('moonweft-preference::ai.help.development_unlimited')),
            ]),
            Section::make(__('moonweft-preference::ai.sections.limits'))
                ->description(__('moonweft-preference::ai.sections.limits_help', ['timezone' => config('app.timezone')]))
                ->schema($limits)->columns(2),
        ]);

        foreach ($catalog->options() as $name => $label) {
            $fields = [];

            foreach ($catalog->fields($name) as $field => $fieldLabel) {
                if ($field === 'reasoning_effort') {
                    $fields[] = AdvancedSelect::make("providers.{$name}.{$field}")->native()->label($fieldLabel)
                        ->options(__('moonweft-preference::ai.reasoning_options'))
                        ->placeholder(__('moonweft-preference::ai.placeholders.provider_default'))
                        ->helperText(__('moonweft-preference::ai.help.reasoning_effort'));

                    continue;
                }

                $input = AdvancedTextInput::make("providers.{$name}.{$field}")
                    ->label($fieldLabel)
                    ->maxLength(4096)
                    ->placeholder(__('moonweft-preference::ai.placeholders.application_default'));

                if ($field === 'key') {
                    $input->password()->revealable()->autocomplete('new-password')
                        ->placeholder(fn (): string => filled(app(AiSettings::class)->providers[$name]['key'] ?? null) ? __('moonweft-preference::ai.placeholders.key_saved') : __('moonweft-preference::ai.placeholders.key_missing'));
                } elseif ($field === 'url') {
                    $input->url()->rules(['url:http,https'])->maxLength(2048)
                        ->helperText(__('moonweft-preference::ai.help.url'));
                } elseif (in_array($field, ['models.text.simple', 'models.text.complex'], true)) {
                    $input->helperText(__('moonweft-preference::ai.help.model_profile'));
                } elseif ($field === 'models.embeddings.dimensions') {
                    $input->integer()->minValue(1)->maxValue(65536);
                }

                $fields[] = $input;
            }

            $fields[] = Toggle::make("providers.{$name}.remove_key")
                ->label(__('moonweft-preference::ai.fields.remove_key'))
                ->helperText(__('moonweft-preference::ai.help.remove_key'));

            $tabs[] = Tab::make($label)->key('provider-'.$name)->schema([
                Section::make($label)
                    ->description($catalog->driver($name) === 'bedrock' ? __('moonweft-preference::ai.help.bedrock') : null)
                    ->schema($fields)
                    ->columns(2),
            ]);
        }

        return $schema->components([
            Tabs::make(__('moonweft-preference::ai.title'))->tabs($tabs)->vertical()->columnSpanFull(),
        ]);
    }
}
