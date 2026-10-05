<?php

declare(strict_types=1);

namespace Moonweft\Preference\Filament\Pages;

use Archilex\AdvancedTables\AdvancedTables;
use MoonWeft\Iam\Filament\Resources\PermissionResource\Pages\ListPermissions as IamListPermissions;

final class ListPermissions extends IamListPermissions
{
    use AdvancedTables;

    protected function getResourceName(): string
    {
        return 'App\\Filament\\Resources\\PermissionResource';
    }
}
