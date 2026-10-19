<?php

return [
    'engine' => '语音识别引擎',
    'engine_help' => '默认 16k_zh（中文普通话）；英语可选 16k_en，粤语可选 16k_yue。请填写腾讯 ASR 支持的引擎 ID。',
    'description' => '腾讯云语音转文字，支持最长 60 秒、Base64 编码后最大 3 MiB 的短音频。需在腾讯云开通语音识别服务。凭据加密保存，留空保留原值；移除密钥会同时清除 SecretId 和 SecretKey。此处不配置语音合成或图片识别。',
];
