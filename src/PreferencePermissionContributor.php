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

    public const MANAGE_SMS = 'preference.sms.manage';

    public function permissions(): iterable
    {
        yield new PermissionDefinition(
            name: self::MANAGE_SYSTEM,
            label: __('moonweft-preference::permissions.system.label'),
            guardName: PermissionGuards::Administration,
            moduleKey: 'preference',
            moduleLabel: __('moonweft-preference::permissions.module'),
            moduleSort: 900,
            groupKey: 'system',
            groupLabel: __('moonweft-preference::permissions.system.group'),
            groupSort: 50,
            permissionSort: 100,
            description: __('moonweft-preference::permissions.system.description'),
        );

        yield new PermissionDefinition(
            name: self::MANAGE_AI,
            label: __('moonweft-preference::permissions.ai.label'),
            guardName: PermissionGuards::Administration,
            moduleKey: 'preference',
            moduleLabel: __('moonweft-preference::permissions.module'),
            moduleSort: 900,
            groupKey: 'ai',
            groupLabel: __('moonweft-preference::permissions.ai.group'),
            groupSort: 100,
            permissionSort: 100,
            description: __('moonweft-preference::permissions.ai.description'),
        );

        yield new PermissionDefinition(
            name: self::MANAGE_SMS,
            label: __('moonweft-preference::permissions.sms.label'),
            guardName: PermissionGuards::Administration,
            moduleKey: 'preference',
            moduleLabel: __('moonweft-preference::permissions.module'),
            moduleSort: 900,
            groupKey: 'sms',
            groupLabel: __('moonweft-preference::permissions.sms.group'),
            groupSort: 120,
            permissionSort: 100,
            description: __('moonweft-preference::permissions.sms.description'),
        );
    }
}
