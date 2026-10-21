<?php

return [
    'title' => '主题',
    'navigation_label' => '主题',
    'description' => '选择后台界面主题，所有用户共用。保存后当前页面重新加载，并应用于后台与登录页。',
    'colors_description' => '直接选择一套完整配色，预览展示主色、背景、边框和正文色。其他已打开的页面刷新后生效。',
    'sections' => ['theme' => '界面主题', 'colors' => '配色方案'],
    'fields' => ['panel_theme' => '主题', 'color_scheme' => '配色方案', 'primary_color' => '主色', 'gray_color' => '中性色'],
    'themes' => ['sharp' => '利落直角、紧凑布局与清晰边框。', 'orbit' => '柔和圆角、轻盈阴影与宽松布局。'],
    'selected' => '已选择',
    'schemes' => [
        'blue' => '海洋蓝', 'indigo' => '午夜靛蓝', 'violet' => '鸢尾紫', 'purple' => '紫水晶',
        'rose' => '玫瑰红', 'orange' => '日落橙', 'amber' => '琥珀金', 'emerald' => '森林绿',
        'teal' => '湖水青', 'cyan' => '冰川蓝', 'lime' => '春芽绿', 'fuchsia' => '兰花紫',
        'red' => '珊瑚红', 'yellow' => '日光黄', 'green' => '草原绿', 'sky' => '晴空蓝',
        'pink' => '樱花粉', 'mauve' => '烟霞紫', 'olive' => '橄榄绿', 'taupe' => '暖砂褐',
        'current' => '当前配色',
    ],
];
