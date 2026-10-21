<?php

return [
    'title' => '系统设置',
    'navigation_label' => '系统',
    'navigation_group' => '偏好配置',
    'fields' => [
        'theme_mode' => '主题',
        'primary_color' => '主色',
        'gray_color' => '中性色',
        'timezone' => '系统时区',
        'date_format' => '日期格式',
        'time_format' => '时间格式',
    ],
    'appearance' => [
        'title' => '主题与配色',
        'description' => '统一应用于后台及其登录页，所有用户与组织共用。「跟随系统」使用各设备的外观偏好。保存后当前页面自动重新加载，其他已打开的页面刷新后生效。',
        'primary_help' => '用于按钮、链接和选中状态。成功、警告、错误等状态颜色保持原有含义。',
        'gray_help' => '用于背景、边框和正文的灰色系。',
    ],
    'theme_modes' => ['system' => '跟随系统', 'light' => '浅色', 'dark' => '深色'],
    'primary_colors' => [
        'blue' => '蓝色', 'indigo' => '靛蓝', 'violet' => '紫罗兰', 'purple' => '紫色', 'rose' => '玫红',
        'orange' => '橙色', 'amber' => '琥珀', 'emerald' => '翠绿', 'teal' => '蓝绿', 'cyan' => '青色',
    ],
    'gray_colors' => ['slate' => '蓝灰', 'gray' => '冷灰', 'zinc' => '锌灰', 'neutral' => '中性灰', 'stone' => '暖灰'],
    'datetime' => [
        'title' => '日期与时间',
        'description' => '对整个系统的默认时间显示和日期时间输入生效，所有用户与组织共用。',
        'timezone_help' => '使用 IANA 时区，例如 Asia/Shanghai（北京时间）。自动遵循所选地区的夏令时。',
    ],
    'date_formats' => [
        'Y-m-d' => '2026-10-14（年-月-日）',
        'Y/m/d' => '2026/10/14（年/月/日）',
        'd/m/Y' => '14/10/2026（日/月/年）',
        'm/d/Y' => '10/14/2026（月/日/年）',
        'd.m.Y' => '14.10.2026（日.月.年）',
    ],
    'time_formats' => [
        'H:i:s' => '14:30:00（24 小时，含秒）',
        'H:i' => '14:30（24 小时）',
        'h:i:s A' => '02:30:00 PM（12 小时，含秒）',
        'h:i A' => '02:30 PM（12 小时）',
    ],
    'scope' => [
        'title' => '生效范围',
        'description' => '统一后台表格、详情和日期时间选择器的默认格式。已保存的时间点保持不变；生日等纯日期和每天的固定时间不随时区换算。业务截止时间、统计周期和定时执行规则由各业务模块决定。',
    ],
    'errors' => ['stale' => '系统设置已被其他管理员修改，请刷新页面后重试。'],
];
