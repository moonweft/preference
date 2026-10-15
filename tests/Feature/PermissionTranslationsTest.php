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
                'preference.system.manage' => ['系统配置', '系统设置', '管理系统设置', '维护平台统一时区、日期与时间格式。'],
                'preference.ai.manage' => ['系统配置', '人工智能', '管理 AI 配置', '配置 AI 服务商、模型、凭据及执行限额。'],
            ],
            'en' => [
                'preference.system.manage' => ['System configuration', 'System settings', 'Manage system settings', 'Manage the platform time zone and date and time formats.'],
                'preference.ai.manage' => ['System configuration', 'Artificial intelligence', 'Manage AI configuration', 'Configure AI providers, models, credentials and execution limits.'],
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
                ], $identities);
            }
        } finally {
            app()->setLocale($originalLocale);
        }
    }
}
