<?php

declare(strict_types=1);

namespace Moonweft\Preference\Filament\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Moonweft\Preference\Sms\SmsSettings;

final class SmsSettingsForm
{
    public static function configure(Schema $schema): Schema
    {
        $tabs = [Tab::make(__('moonweft-preference::sms.tabs.general'))->schema([
            Section::make(__('moonweft-preference::sms.sections.service'))->description(__('moonweft-preference::sms.sections.service_description'))
                ->schema([
                    Toggle::make('enabled')->label(__('moonweft-preference::sms.fields.enabled')),
                    Select::make('default_gateway')->label(__('moonweft-preference::sms.fields.default_gateway'))
                        ->options(array_map(static fn (array $gateway): string => __($gateway['label']), SmsSettings::GATEWAYS))->required(),
                    Select::make('backup_gateway')->label(__('moonweft-preference::sms.fields.backup_gateway'))->placeholder(__('moonweft-preference::sms.no_backup'))
                        ->options(array_map(static fn (array $gateway): string => __($gateway['label']), SmsSettings::GATEWAYS))
                        ->different('default_gateway'),
                    Toggle::make('failover_enabled')->label(__('moonweft-preference::sms.fields.failover_enabled'))->helperText(__('moonweft-preference::sms.help.failover')),
                    TextInput::make('timeout')->label(__('moonweft-preference::sms.fields.timeout'))->numeric()->minValue(1)->maxValue(60)->suffix(__('moonweft-preference::sms.seconds'))->required(),
                ])->columns(2),
        ])];

        foreach (SmsSettings::GATEWAYS as $gateway => $definition) {
            $fields = [];
            foreach ($definition['fields'] as $field => $label) {
                $input = TextInput::make("providers.{$gateway}.{$field}")->label(__($label))->maxLength(4096);
                if (in_array($field, $definition['secrets'], true)) {
                    $input->password()->revealable()->autocomplete('new-password')
                        ->helperText(__('moonweft-preference::sms.help.credentials'));
                }
                $fields[] = $input;
            }
            $fields[] = Toggle::make("providers.{$gateway}.remove_credentials")->label(__('moonweft-preference::sms.fields.remove_credentials'))
                ->helperText(__('moonweft-preference::sms.help.remove_credentials'));
            $tabs[] = Tab::make(__($definition['label']))->schema($fields)->columns(2);
        }

        return $schema->components([
            Tabs::make(__('moonweft-preference::sms.title'))->tabs($tabs)->vertical()->columnSpanFull(),
            Section::make(__('moonweft-preference::sms.sections.delivery'))->description(__('moonweft-preference::sms.sections.delivery_description'))->columnSpanFull(),
        ]);
    }
}
