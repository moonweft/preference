<?php

return [
    'title' => 'AI 配置',
    'tabs' => [
        'defaults' => '默认配置',
        'limits' => '使用限制',
    ],
    'sections' => [
        'defaults' => '各项能力的默认服务商',
        'defaults_help' => '对整个系统生效。留空沿用应用默认值，业务中明确指定的服务商或模型优先。',
        'development' => '开发调试',
        'limits' => '账号与会话限额',
        'limits_help' => '对整个系统生效，各账号独立累计。每日额度按系统时区 :timezone 的零点重置。保存后从下一次请求生效，已有用量不会清零。',
    ],
    'placeholders' => [
        'application_default' => '使用应用默认值',
        'inherit_default' => '沿用应用默认值',
        'provider_default' => '沿用应用或服务商默认值',
        'key_saved' => '已保存，留空保留',
        'key_missing' => '未保存，留空使用环境配置',
    ],
    'capabilities' => [
        'text' => '文本生成',
        'image' => '图像生成',
        'audio' => '语音合成',
        'transcription' => '语音转文字',
        'embeddings' => '向量嵌入',
        'reranking' => '搜索重排序',
    ],
    'providers' => [
        'openai_compatible' => 'OpenAI 兼容接口',
    ],
    'fields' => [
        'key' => 'API 密钥',
        'bearer_token' => 'Bearer Token',
        'region' => 'AWS 区域',
        'url' => '接口地址',
        'api_version' => 'API 版本',
        'model' => ':capability模型',
        'deployment' => ':capability部署名称',
        'simple_model' => '简单查询模型（可选）',
        'complex_model' => '分析与业务操作模型（可选）',
        'reasoning_effort' => '推理强度',
        'dimensions' => '向量维度',
        'remove_key' => '移除已保存的密钥',
        'development_unlimited' => '开发阶段不限额',
    ],
    'development_options' => [
        0 => '关闭',
        1 => '开启：请求与执行预算无限',
    ],
    'reasoning_options' => [
        'none' => '关闭深度推理',
        'low' => '低：较低延迟',
        'high' => '高：复杂推理',
        'max' => '最高：更长等待',
    ],
    'help' => [
        'development_unlimited' => '仅在 local / testing 环境生效。开启后不限制请求次数、每日用量、推理轮数、工具调用次数、工具结果容量和执行时间/token 预算；仍记录用量。权限、审批、防重复执行、模型/附件容量、网络超时和任务租约仍生效。下方预算值保留，关闭后恢复。',
        'limit_default' => '留空沿用应用默认值，不支持 0。',
        'reasoning_effort' => '低档优先缩短等待，高档适合复杂分析。DeepSeek V4 Pro 支持 low；其他型号请确认支持的档位。调整后请用实际业务问题验证回答质量。',
        'url' => '填写完整接口根地址；留空使用应用默认地址。',
        'model_profile' => '填写当前服务商实际支持且已评测的模型 ID。留空沿用默认模型，不自动选择 Pro 或 Smartest 别名。',
        'remove_key' => '保存后恢复使用环境配置中的密钥。',
        'bedrock' => '可使用 Bearer Token；AWS 凭据链及角色配置沿用应用环境。',
    ],
    'limits' => [
        'requests_per_minute' => [
            'label' => '每分钟请求次数',
            'unit' => '次',
            'help' => '按自然分钟重置。',
        ],
        'requests_per_day' => [
            'label' => '每日请求次数',
            'unit' => '次',
            'help' => '所有会话和业务入口共用同一账号额度。',
        ],
        'max_tokens_per_day' => [
            'label' => '每日用量预算',
            'unit' => '预估 token',
            'help' => '按压缩后的上下文预估预留，结束后按服务商实际用量结算；未用额度释放，未知用量保留预估，不冒充零费用。',
        ],
        'max_output_tokens' => [
            'label' => '单次回复输出上限',
            'unit' => 'token',
            'help' => '限制每次回复的最大长度，仍受服务商及模型支持范围约束。',
        ],
        'max_conversation_bytes' => [
            'label' => '会话上下文容量',
            'unit' => '字节',
            'help' => '按最近最多 100 条原生消息和本次输入估算，不含密文膨胀、界面表格副本和审计元数据。默认 1048576 字节（1 MiB）；历史记录继续保存，模型自身窗口仍单独限制。',
        ],
        'max_attachment_files' => [
            'label' => '每次附件数量',
            'unit' => '个',
            'help' => '默认 3 个，支持 PDF、JPEG、PNG 和 WebP。',
        ],
        'max_attachment_kb' => [
            'label' => '单个附件大小',
            'unit' => 'KiB',
            'help' => '默认 10240（10 MiB）；还受 PHP 与 Web 服务器上传限制约束。',
        ],
        'max_attachment_context_kb' => [
            'label' => '会话附件容量',
            'unit' => 'KiB',
            'help' => '默认 24576（24 MiB），按加密附件存储量限制内存开销，与文字上下文分开计量。',
        ],
        'max_pdf_pages' => [
            'label' => '单个 PDF 页数',
            'unit' => '页',
            'help' => '默认 10 页；按页转为图片，支持扫描件和图表。超过时拒绝，不静默截取。',
        ],
        'max_attachment_images' => [
            'label' => '会话图片与 PDF 页数',
            'unit' => '张 / 页',
            'help' => '默认 20，累计最近上下文中的图片和 PDF 页面；实际支持量仍取决于模型。',
        ],
        'max_steps' => [
            'label' => '单次模型推理轮数',
            'unit' => '轮',
            'help' => '默认 6 轮，建议保持在 4–6 轮；审批续跑共用预算。',
        ],
        'max_tool_calls' => [
            'label' => '单次工具调用总数',
            'unit' => '次',
            'help' => '默认 12 次，包括同一轮并行提出的工具调用。',
        ],
        'max_tool_calls_per_tool' => [
            'label' => '单工具调用上限',
            'unit' => '次',
            'help' => '默认 3 次；重复参数会提前拦截，业务工具可设置更低上限。',
        ],
        'max_tool_result_bytes' => [
            'label' => '单工具结果容量',
            'unit' => '字节',
            'help' => '默认 65536 字节，超过后停止继续推理，避免大量数据污染上下文。',
        ],
        'max_run_seconds' => [
            'label' => '单次执行时间预算',
            'unit' => '秒',
            'help' => '默认 120 秒，在模型与工具边界检查；不强行中断已开始的业务事务，审批等待不计时。',
        ],
        'max_run_tokens' => [
            'label' => '单次实际用量预算',
            'unit' => 'token',
            'help' => '默认 64000，累计服务商返回的逐轮用量；达到后不再发起下一步，已发出的请求可能超过余额。',
        ],
    ],
    'errors' => [
        'unavailable' => 'AI 配置暂时无法读取',
        'unavailable_help' => '已保存的配置无法解密。请联系运维检查应用加密密钥或恢复配置备份，修复后刷新页面。当前配置未被修改。',
        'stale' => 'AI 配置已被其他管理员修改，请刷新页面后重试。',
        'default_provider' => '作为默认服务商时，必须填写接口地址及对应模型。',
        'database_required' => 'AI 配置必须使用数据库设置存储。',
    ],
];
