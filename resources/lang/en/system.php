<?php

return [
    'title' => 'System settings',
    'navigation_label' => 'System',
    'navigation_group' => 'Preferences',
    'fields' => [
        'theme_mode' => 'Theme',
        'primary_color' => 'Primary colour',
        'gray_color' => 'Neutral colour',
        'timezone' => 'System time zone',
        'date_format' => 'Date format',
        'time_format' => 'Time format',
    ],
    'appearance' => [
        'title' => 'Theme and colours',
        'description' => 'Shared across the admin panel and its login page for all users and organisations. System mode follows each device’s appearance. Saving reloads this page; other open pages apply changes when refreshed.',
        'primary_help' => 'Buttons, links and selected items use this colour. Status colours keep their existing meaning.',
        'gray_help' => 'Controls the neutral tones of backgrounds, borders and text.',
    ],
    'theme_modes' => ['system' => 'System', 'light' => 'Light', 'dark' => 'Dark'],
    'primary_colors' => [
        'blue' => 'Blue', 'indigo' => 'Indigo', 'violet' => 'Violet', 'purple' => 'Purple', 'rose' => 'Rose',
        'orange' => 'Orange', 'amber' => 'Amber', 'emerald' => 'Emerald', 'teal' => 'Teal', 'cyan' => 'Cyan',
    ],
    'gray_colors' => ['slate' => 'Slate', 'gray' => 'Gray', 'zinc' => 'Zinc', 'neutral' => 'Neutral', 'stone' => 'Stone'],
    'datetime' => [
        'title' => 'Date and time',
        'description' => 'Default date and time displays and inputs share these settings across all users and organisations.',
        'timezone_help' => 'Use an IANA time zone, such as Asia/Shanghai. Daylight saving changes follow the selected region.',
    ],
    'date_formats' => [
        'Y-m-d' => '2026-10-14 (year-month-day)',
        'Y/m/d' => '2026/10/14 (year/month/day)',
        'd/m/Y' => '14/10/2026 (day/month/year)',
        'm/d/Y' => '10/14/2026 (month/day/year)',
        'd.m.Y' => '14.10.2026 (day.month.year)',
    ],
    'time_formats' => [
        'H:i:s' => '14:30:00 (24-hour, with seconds)',
        'H:i' => '14:30 (24-hour)',
        'h:i:s A' => '02:30:00 PM (12-hour, with seconds)',
        'h:i A' => '02:30 PM (12-hour)',
    ],
    'scope' => [
        'title' => 'Scope',
        'description' => 'Date and time formats apply to admin tables, details and date/time pickers. Stored instants stay unchanged; calendar dates such as birthdays and daily fixed times are not converted. Business modules define deadlines, reporting periods and schedules.',
    ],
    'errors' => ['stale' => 'Another administrator changed the system settings. Refresh the page and try again.'],
];
