<?php

declare(strict_types=1);

namespace Moonweft\Preference\Ai;

use DomainException;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use MoonWeft\Iam\PermissionGuards;
use MoonWeft\Iam\PrincipalReference;
use MoonWeft\Iam\PrincipalResolver;
use Moonweft\Preference\PreferencePermissionContributor;

final readonly class AiSettingsAccess
{
    public function __construct(private PrincipalResolver $principals) {}

    public function principal(): ?PrincipalReference
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
            $reference = $this->principals->reference($actor);
        } catch (DomainException) {
            return null;
        }

        if ($reference->provider !== config('auth.guards.'.PermissionGuards::Administration.'.provider')) {
            return null;
        }

        $actor = $this->principals->resolve($reference->identity());

        if (! $actor instanceof FilamentUser || ! $actor->canAccessPanel($panel)
            || Gate::forUser($actor)->denies(PreferencePermissionContributor::MANAGE_AI)) {
            return null;
        }

        return $this->principals->reference($actor);
    }
}
