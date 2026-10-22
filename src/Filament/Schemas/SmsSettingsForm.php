<?php

declare(strict_types=1);

namespace Moonweft\Preference\Filament\Schemas;

use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use MoonWeft\Components\Text\Forms\AdvancedTextInput;
use Moonweft\Preference\Sms\SmsSettings;

final class SmsSettingsForm
{
    public static function configure(Schema $schema): Schema
    {
        $tabs = [];

        foreach (SmsSettings::GATEWAYS as $gateway => $definition) {
            $fields = [Text::make(__('moonweft-preference::sms.help.provider'))->columnSpanFull()];
            foreach ($definition['fields'] as $field => $label) {
                $input = AdvancedTextInput::make("providers.{$gateway}.{$field}")->label(__($label))->maxLength(4096)
                    ->helperText(__('moonweft-preference::sms.provider_fields.'.$gateway.'.'.$field)
                        .(in_array($field, $definition['secrets'], true) ? ' '.__('moonweft-preference::sms.help.credentials') : ''));
                if (in_array($field, $definition['secrets'], true)) {
                    $input->password()->revealable()->autocomplete('new-password');
                }
                $fields[] = $input;
            }
            $fields[] = Toggle::make("providers.{$gateway}.remove_credentials")->label(__('moonweft-preference::sms.fields.remove_credentials'))
                ->helperText(__('moonweft-preference::sms.help.remove_credentials'));
            $tabs[] = Tab::make(__($definition['label']))->schema($fields)->columns(2);
        }

        return $schema->components([
            Section::make(__('moonweft-preference::sms.sections.service'))->description(__('moonweft-preference::sms.sections.service_description'))
                ->schema([
                    Toggle::make('enabled')->label(__('moonweft-preference::sms.fields.enabled')),
                    ToggleButtons::make('default_gateway')->label(__('moonweft-preference::sms.fields.default_gateway'))
                        ->options(array_map(static fn (array $gateway): string => __($gateway['label']), SmsSettings::GATEWAYS))
                        ->inline()->required()->extraAttributes(['class' => 'mw-sms-provider-options'])
                        ->helperText(__('moonweft-preference::sms.help.default_gateway')),
                    ToggleButtons::make('backup_gateway')->label(__('moonweft-preference::sms.fields.backup_gateway'))
                        ->options(array_map(static fn (array $gateway): string => __($gateway['label']), SmsSettings::GATEWAYS))
                        ->inline()->different('default_gateway')->extraAttributes(['class' => 'mw-sms-provider-options'])
                        ->helperText(__('moonweft-preference::sms.help.backup_gateway')),
                    Toggle::make('failover_enabled')->label(__('moonweft-preference::sms.fields.failover_enabled'))->helperText(__('moonweft-preference::sms.help.failover')),
                    AdvancedTextInput::make('timeout')->label(__('moonweft-preference::sms.fields.timeout'))->numeric()->minValue(1)->maxValue(60)
                        ->suffix(__('moonweft-preference::sms.seconds'))->required()->helperText(__('moonweft-preference::sms.help.timeout')),
                ])->columnSpanFull(),
            Tabs::make(__('moonweft-preference::sms.fields.providers'))->tabs($tabs)->columnSpanFull(),
            Section::make(__('moonweft-preference::sms.sections.delivery'))->description(__('moonweft-preference::sms.sections.delivery_description'))->columnSpanFull(),
        ]);
    }
}
