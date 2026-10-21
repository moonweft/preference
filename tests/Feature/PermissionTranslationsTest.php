<?php

declare(strict_types=1);

namespace Moonweft\Preference\Tests\Feature;

use Moonweft\Preference\PreferencePermissionContributor;
use Tests\TestCase;

final class PermissionTranslationsTest extends TestCase
{
    public function test_permission_copy_follows_locale_changes_without_changing_authorization_keys(): void
    {
        $contributor = new PreferencePermissionContributor;
        $expected = [
            'zh_CN' => [
                'preference.system.manage' => ['系统配置', '系统设置', '管理系统设置', '维护平台统一主题、配色、时区、日期与时间格式。'],
                'preference.ai.manage' => ['系统配置', '人工智能', '管理 AI 配置', '配置 AI 服务商、模型、凭据及执行限额。'],
                'preference.sms.manage' => ['系统配置', '短信服务', '管理短信配置', '配置平台短信开关、默认服务商、签名及凭据。'],
            ],
            'en' => [
                'preference.system.manage' => ['System configuration', 'System settings', 'Manage system settings', 'Manage the platform theme, colours, time zone and date and time formats.'],
                'preference.ai.manage' => ['System configuration', 'Artificial intelligence', 'Manage AI configuration', 'Configure AI providers, models, credentials and execution limits.'],
                'preference.sms.manage' => ['System configuration', 'SMS service', 'Manage SMS configuration', 'Configure platform SMS availability, default gateway, signatures and credentials.'],
            ],
        ];
        $originalLocale = app()->getLocale();

        try {
            foreach (['zh_CN', 'en', 'zh_CN'] as $locale) {
                app()->setLocale($locale);
                $copy = [];
                $identities = [];
                foreach ($contributor->permissions() as $permission) {
                    $copy[$permission->name] = [$permission->moduleLabel, $permission->groupLabel, $permission->label, $permission->description];
                    $identities[$permission->name] = [$permission->guardName, $permission->moduleKey, $permission->groupKey, $permission->requiresPlatformAssignment];
                }
                self::assertSame($expected[$locale], $copy);
                self::assertSame([
                    'preference.system.manage' => ['admin', 'preference', 'system', true],
                    'preference.ai.manage' => ['admin', 'preference', 'ai', true],
                    'preference.sms.manage' => ['admin', 'preference', 'sms', true],
                ], $identities);
            }
        } finally {
            app()->setLocale($originalLocale);
        }
    }
}
