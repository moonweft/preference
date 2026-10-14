<?php

declare(strict_types=1);

namespace Moonweft\Preference;

use MoonWeft\Iam\Contracts\PermissionContributor;
use MoonWeft\Iam\PermissionDefinition;
use MoonWeft\Iam\PermissionGuards;

final class PreferencePermissionContributor implements PermissionContributor
{
    public const MANAGE_AI = 'preference.ai.manage';

    public const MANAGE_SYSTEM = 'preference.system.manage';

    public function permissions(): iterable
    {
        yield new PermissionDefinition(
            name: self::MANAGE_SYSTEM,
            label: '管理系统设置',
            guardName: PermissionGuards::Administration,
            moduleKey: 'preference',
            moduleLabel: '系统配置',
            moduleSort: 900,
            groupKey: 'system',
            groupLabel: '系统设置',
            groupSort: 50,
            permissionSort: 100,
        );

        yield new PermissionDefinition(
            name: self::MANAGE_AI,
            label: '管理 AI 配置',
            guardName: PermissionGuards::Administration,
            moduleKey: 'preference',
            moduleLabel: '系统配置',
            moduleSort: 900,
            groupKey: 'ai',
            groupLabel: '人工智能',
            groupSort: 100,
            permissionSort: 100,
        );
    }
}
