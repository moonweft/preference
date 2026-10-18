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
        $tabs = [Tab::make('默认配置')->schema([
            Section::make('系统短信服务')->description('整个系统共用，默认关闭。启用前请填写默认服务商的凭据；保存配置不会发送短信，也不代表服务商已验证通过。')
                ->schema([
                    Toggle::make('enabled')->label('启用短信发送'),
                    Select::make('default_gateway')->label('默认服务商')
                        ->options(array_map(static fn (array $gateway): string => $gateway['label'], SmsSettings::GATEWAYS))->required(),
                    TextInput::make('timeout')->label('请求超时')->numeric()->minValue(1)->maxValue(60)->suffix('秒')->required(),
                ])->columns(2),
        ])];

        foreach (SmsSettings::GATEWAYS as $gateway => $definition) {
            $fields = [];
            foreach ($definition['fields'] as $field => $label) {
                $input = TextInput::make("providers.{$gateway}.{$field}")->label($label)->maxLength(4096);
                if (in_array($field, $definition['secrets'], true)) {
                    $input->password()->revealable()->autocomplete('new-password')
                        ->helperText('已保存的凭据不会回显，留空保留原值。');
                }
                $fields[] = $input;
            }
            $fields[] = Toggle::make("providers.{$gateway}.remove_credentials")->label('移除已保存的凭据')
                ->helperText('当前默认服务商启用时不能移除必填凭据，请先关闭短信或切换服务商。');
            $tabs[] = Tab::make($definition['label'])->schema($fields)->columns(2);
        }

        return $schema->components([
            Tabs::make('短信配置')->tabs($tabs)->vertical()->columnSpanFull(),
            Section::make('发送与验证')->description('短信模板与内容由业务模块提供，须先在服务商完成签名和模板审核。业务模块负责收件人权限、发送频率、重复发送防护及送达结果核验。')->columnSpanFull(),
        ]);
    }
}
