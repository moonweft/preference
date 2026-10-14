<?php

declare(strict_types=1);

namespace Moonweft\Preference\Filament\Schemas;

use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Moonweft\Preference\System\SystemSettings;

final class SystemSettingsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('日期与时间')
                ->description('对整个系统的默认时间显示和日期时间输入生效，所有用户与组织共用。保存后刷新页面生效。')
                ->schema([
                    Select::make('timezone')->label('系统时区')
                        ->options(SystemSettings::timezones())->searchable()->required()
                        ->helperText('使用 IANA 时区，例如 Asia/Shanghai（北京时间）。自动遵循所选地区的夏令时。'),
                    Select::make('date_format')->label('日期格式')
                        ->options(SystemSettings::DATE_FORMATS)->required(),
                    Select::make('time_format')->label('时间格式')
                        ->options(SystemSettings::TIME_FORMATS)->required(),
                ])->columns(3)->columnSpanFull(),
            Section::make('生效范围')
                ->description('统一后台表格、详情和日期时间选择器的默认格式。已保存的时间点保持不变；生日等纯日期和每天的固定时间不随时区换算。业务截止时间、统计周期和定时执行规则由各业务模块决定。')
                ->columnSpanFull(),
        ]);
    }
}
