<?php

return [
    'title' => 'System settings',
    'navigation_label' => 'System',
    'navigation_group' => 'Preferences',
    'fields' => [
        'timezone' => 'System time zone',
        'date_format' => 'Date format',
        'time_format' => 'Time format',
    ],
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
