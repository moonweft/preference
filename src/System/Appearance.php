<?php

declare(strict_types=1);

namespace Moonweft\Preference\System;

/** Installed admin themes and curated primary/neutral colour combinations. */
final class Appearance
{
    public const THEMES = [
        'sharp' => 'resources/css/filament/admin/sharp.css',
        'orbit' => 'resources/css/filament/admin/orbit.css',
    ];

    public const COLOR_SCHEMES = [
        'blue' => ['primary_color' => 'blue', 'gray_color' => 'slate'],
        'indigo' => ['primary_color' => 'indigo', 'gray_color' => 'zinc'],
        'violet' => ['primary_color' => 'violet', 'gray_color' => 'slate'],
        'purple' => ['primary_color' => 'purple', 'gray_color' => 'neutral'],
        'rose' => ['primary_color' => 'rose', 'gray_color' => 'zinc'],
        'orange' => ['primary_color' => 'orange', 'gray_color' => 'stone'],
        'amber' => ['primary_color' => 'amber', 'gray_color' => 'stone'],
        'emerald' => ['primary_color' => 'emerald', 'gray_color' => 'neutral'],
        'teal' => ['primary_color' => 'teal', 'gray_color' => 'slate'],
        'cyan' => ['primary_color' => 'cyan', 'gray_color' => 'gray'],
        'lime' => ['primary_color' => 'lime', 'gray_color' => 'neutral'],
        'fuchsia' => ['primary_color' => 'fuchsia', 'gray_color' => 'zinc'],
    ];

    /** Resolve a saved combination without silently changing an existing custom pairing. */
    public static function selectedScheme(SystemSettings $settings): string
    {
        return array_search([
            'primary_color' => $settings->primary_color,
            'gray_color' => $settings->gray_color,
        ], self::COLOR_SCHEMES, true) ?: 'current';
    }

    /** @return array<string, array{primary_color: string, gray_color: string}> */
    public static function colorSchemes(): array
    {
        $settings = app(SystemSettings::class);

        return self::COLOR_SCHEMES + (self::selectedScheme($settings) === 'current' ? [
            'current' => ['primary_color' => $settings->primary_color, 'gray_color' => $settings->gray_color],
        ] : []);
    }
}
