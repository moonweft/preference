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
use MoonWeft\Components\Slider\Forms\Slider;
use MoonWeft\Components\Text\Forms\AdvancedTextInput;
use Moonweft\Preference\Sms\SmsSettings;

final class SmsSettingsForm
{
    public static function configure(Schema $schema): Schema
    {
        $tabs = [];
        $icons = [
            'aliyun' => view('mw-components::icons.providers.aliyun'),
            'qcloud' => view('mw-components::icons.providers.tencent'),
            'qiniu' => view('mw-components::icons.providers.qiniu'),
            'yunpian' => view('mw-components::icons.providers.yunpian'),
        ];

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
            $tabs[] = Tab::make(__($definition['label']))->icon($icons[$gateway])->schema($fields)->columns(2);
        }

        return $schema->components([
            Section::make(__('moonweft-preference::sms.sections.service'))->description(__('moonweft-preference::sms.sections.service_description'))
                ->schema([
                    Toggle::make('enabled')->label(__('moonweft-preference::sms.fields.enabled')),
                    ToggleButtons::make('default_gateway')->label(__('moonweft-preference::sms.fields.default_gateway'))
                        ->icons($icons)
                        ->options(array_map(static fn (array $gateway): string => __($gateway['label']), SmsSettings::GATEWAYS))
                        ->inline()->required()->extraAttributes(['class' => 'mw-sms-provider-options'])
                        ->helperText(__('moonweft-preference::sms.help.default_gateway')),
                    ToggleButtons::make('backup_gateway')->label(__('moonweft-preference::sms.fields.backup_gateway'))
                        ->icons($icons)
                        ->options(array_map(static fn (array $gateway): string => __($gateway['label']), SmsSettings::GATEWAYS))
                        ->inline()->different('default_gateway')->extraAttributes(['class' => 'mw-sms-provider-options'])
                        ->helperText(__('moonweft-preference::sms.help.backup_gateway')),
                    Toggle::make('failover_enabled')->label(__('moonweft-preference::sms.fields.failover_enabled'))->helperText(__('moonweft-preference::sms.help.failover')),
                    Slider::make('timeout')->label(__('moonweft-preference::sms.fields.timeout'))
                        ->range(1, 180)->step(1)->decimalPlaces(0)->default(5)->required()
                        ->formatStateUsing(static fn (mixed $state): mixed => is_numeric($state) ? (int) round((float) $state) : $state)
                        ->currency('')->rulerLabel(false)->accent('var(--primary-500)')
                        ->presets(array_map(static fn (int $seconds): array => [
                            'amount' => $seconds,
                            'label' => $seconds.' '.__('moonweft-preference::sms.seconds'),
                        ], [5, 10, 30, 60, 120, 180]))
                        ->helperText(__('moonweft-preference::sms.help.timeout')),
                ])->columnSpanFull(),
            Tabs::make(__('moonweft-preference::sms.fields.providers'))->tabs($tabs)->columnSpanFull(),
            Section::make(__('moonweft-preference::sms.sections.delivery'))->description(__('moonweft-preference::sms.sections.delivery_description'))->columnSpanFull(),
        ]);
    }
}
