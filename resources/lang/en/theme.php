<?php

return [
    'title' => 'Theme',
    'navigation_label' => 'Theme',
    'description' => 'Choose the admin interface theme. Saving reloads this page and applies the theme to the admin panel and login page for everyone.',
    'colors_description' => 'Choose a complete colour scheme. Each preview shows its primary colour, background, borders and text. Other open pages apply changes when refreshed.',
    'sections' => ['theme' => 'Interface theme', 'colors' => 'Colour schemes'],
    'fields' => ['panel_theme' => 'Theme', 'color_scheme' => 'Colour scheme', 'primary_color' => 'Primary colour', 'gray_color' => 'Neutral colour'],
    'themes' => ['sharp' => 'Crisp edges, compact layout and clear borders.', 'orbit' => 'Rounded panels, soft shadows and spacious layout.'],
    'selected' => 'Selected',
    'schemes' => [
        'blue' => 'Ocean blue', 'indigo' => 'Midnight indigo', 'violet' => 'Iris violet', 'purple' => 'Amethyst',
        'rose' => 'Rose garden', 'orange' => 'Sunset orange', 'amber' => 'Golden amber', 'emerald' => 'Forest emerald',
        'teal' => 'Lagoon teal', 'cyan' => 'Glacier cyan', 'lime' => 'Spring lime', 'fuchsia' => 'Orchid',
        'red' => 'Coral red', 'yellow' => 'Sunshine yellow', 'green' => 'Meadow green', 'sky' => 'Clear sky',
        'pink' => 'Cherry blossom', 'mauve' => 'Dusty mauve', 'olive' => 'Olive grove', 'taupe' => 'Warm taupe',
        'current' => 'Current colours',
    ],
];
