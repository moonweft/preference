<?php

declare(strict_types=1);

namespace Moonweft\Preference;

use DomainException;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use MoonWeft\Iam\PermissionGuards;
use MoonWeft\Iam\PrincipalReference;
use MoonWeft\Iam\PrincipalResolver;

final readonly class SettingsAccess
{
    public function __construct(private PrincipalResolver $principals) {}

    public function principal(string $permission): ?PrincipalReference
    {
        $panel = Filament::getCurrentPanel();

        if ($panel === null || $panel->getAuthGuard() !== PermissionGuards::Administration) {
            return null;
        }

        $actor = Auth::guard(PermissionGuards::Administration)->user();

        if ($actor === null) {
            return null;
        }

        try {
            $reference = method_exists($this->principals, 'identify')
                ? $this->principals->identify($actor)
                : $this->principals->reference($actor);
        } catch (DomainException) {
            return null;
        }

        $model = config('auth.providers.'.config('auth.guards.'.PermissionGuards::Administration.'.provider').'.model');
        if (! is_string($model) || ! $actor instanceof $model) {
            return null;
        }

        $actor = $this->principals->resolve($reference->identity());

        if (! $actor instanceof FilamentUser || ! $actor->canAccessPanel($panel)
            || Gate::forUser($actor)->denies($permission)) {
            return null;
        }

        return $reference;
    }
}
