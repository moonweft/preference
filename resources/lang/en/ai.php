<?php

return [
    'title' => 'AI settings',
    'tabs' => [
        'defaults' => 'Default settings',
        'limits' => 'Usage limits',
    ],
    'sections' => [
        'defaults' => 'Default providers by capability',
        'defaults_help' => 'Applies across the system. Leave blank to use application defaults. Providers or models explicitly selected by a business operation take precedence.',
        'development' => 'Development debugging',
        'limits' => 'Account and conversation limits',
        'limits_help' => 'Applies across the system, with usage counted separately for each account. Daily quotas reset at midnight in the application time zone (:timezone). Changes apply from the next request and do not reset existing usage.',
    ],
    'placeholders' => [
        'application_default' => 'Use application default',
        'inherit_default' => 'Inherit application default',
        'provider_default' => 'Use application or provider default',
        'key_saved' => 'Saved; leave blank to retain',
        'key_missing' => 'Not saved; leave blank to use environment configuration',
    ],
    'capabilities' => [
        'text' => 'Text generation',
        'image' => 'Image generation',
        'audio' => 'Speech synthesis',
        'transcription' => 'Transcription',
        'embeddings' => 'Embeddings',
        'reranking' => 'Search reranking',
    ],
    'providers' => [
        'openai_compatible' => 'OpenAI-compatible API',
    ],
    'fields' => [
        'key' => 'API key',
        'bearer_token' => 'Bearer Token',
        'region' => 'AWS region',
        'url' => 'Endpoint URL',
        'api_version' => 'API version',
        'model' => ':capability model',
        'deployment' => ':capability deployment',
        'simple_model' => 'Simple query model (optional)',
        'complex_model' => 'Analysis and business operation model (optional)',
        'reasoning_effort' => 'Reasoning effort',
        'dimensions' => 'Embedding dimensions',
        'remove_key' => 'Remove saved API key',
        'development_unlimited' => 'Unlimited development budgets',
    ],
    'development_options' => [
        0 => 'Disabled',
        1 => 'Enabled: unlimited request and execution budgets',
    ],
    'reasoning_options' => [
        'none' => 'Disable deep reasoning',
        'low' => 'Low: lower latency',
        'high' => 'High: complex reasoning',
        'max' => 'Maximum: longer wait',
    ],
    'help' => [
        'development_unlimited' => 'Only applies in local / testing environments. Removes request, daily usage, reasoning step, tool call, tool result size and execution time/token budgets; usage is still recorded. Permissions, approvals, duplicate execution protection, model/attachment capacity, network timeouts and job leases remain enforced. The budgets below are retained and apply again when this option is disabled.',
        'limit_default' => 'Leave blank to use the application default. Zero is not allowed.',
        'reasoning_effort' => 'Lower effort prioritises shorter waits; higher effort suits complex analysis. DeepSeek V4 Pro supports low; check the supported levels for other models. Validate response quality with actual business questions after changing this setting.',
        'url' => 'Enter the full API base URL. Leave blank to use the application default URL.',
        'model_profile' => 'Enter a model ID supported by this provider and evaluated for your use. Leave blank to use the default model; Pro or Smartest aliases are not selected automatically.',
        'remove_key' => 'After saving, the key from the environment configuration will be used.',
        'bedrock' => 'Bearer Token is supported. AWS credential chains and role settings use the application environment.',
    ],
    'limits' => [
        'requests_per_minute' => [
            'label' => 'Requests per minute',
            'unit' => 'Requests',
            'help' => 'Resets at the start of each clock minute.',
        ],
        'requests_per_day' => [
            'label' => 'Requests per day',
            'unit' => 'Requests',
            'help' => 'All conversations and business entry points share the same account quota.',
        ],
        'max_tokens_per_day' => [
            'label' => 'Daily usage budget',
            'unit' => 'Estimated tokens',
            'help' => 'Usage is reserved from the compressed context estimate and settled against provider-reported usage when execution ends. Unused quota is released; unknown usage retains the estimate and is not treated as free.',
        ],
        'max_output_tokens' => [
            'label' => 'Output limit per response',
            'unit' => 'Tokens',
            'help' => 'Limits the length of each response, within the range supported by the provider and model.',
        ],
        'max_conversation_bytes' => [
            'label' => 'Conversation context capacity',
            'unit' => 'Bytes',
            'help' => 'Estimated from the latest 100 native messages and the current input, excluding encryption overhead, UI table copies and audit metadata. Default: 1048576 bytes (1 MiB). History is retained; the model context window is enforced separately.',
        ],
        'max_attachment_files' => [
            'label' => 'Attachments per request',
            'unit' => 'Files',
            'help' => 'Default: 3. Supports PDF, JPEG, PNG and WebP.',
        ],
        'max_attachment_kb' => [
            'label' => 'Size per attachment',
            'unit' => 'KiB',
            'help' => 'Default: 10240 (10 MiB). PHP and web server upload limits also apply.',
        ],
        'max_attachment_context_kb' => [
            'label' => 'Conversation attachment capacity',
            'unit' => 'KiB',
            'help' => 'Default: 24576 (24 MiB). Limits memory overhead by encrypted attachment storage size, measured separately from text context.',
        ],
        'max_pdf_pages' => [
            'label' => 'Pages per PDF',
            'unit' => 'Pages',
            'help' => 'Default: 10. Pages are converted to images, including scanned documents and charts. Excess pages are rejected, not silently truncated.',
        ],
        'max_attachment_images' => [
            'label' => 'Conversation images and PDF pages',
            'unit' => 'Images / pages',
            'help' => 'Default: 20. Counts images and PDF pages in the recent context; actual support also depends on the model.',
        ],
        'max_steps' => [
            'label' => 'Reasoning steps per execution',
            'unit' => 'Steps',
            'help' => 'Default: 6. Recommended range: 4–6. Resuming after approval shares the same budget.',
        ],
        'max_tool_calls' => [
            'label' => 'Total tool calls per execution',
            'unit' => 'Calls',
            'help' => 'Default: 12, including tool calls proposed in parallel during the same step.',
        ],
        'max_tool_calls_per_tool' => [
            'label' => 'Calls per tool',
            'unit' => 'Calls',
            'help' => 'Default: 3. Repeated arguments are blocked earlier; business tools may set a lower limit.',
        ],
        'max_tool_result_bytes' => [
            'label' => 'Tool result capacity',
            'unit' => 'Bytes',
            'help' => 'Default: 65536 bytes. Further reasoning stops when exceeded to prevent excessive data from filling the context.',
        ],
        'max_run_seconds' => [
            'label' => 'Execution time budget',
            'unit' => 'Seconds',
            'help' => 'Default: 120 seconds. Checked at model and tool boundaries without forcibly interrupting business transactions already in progress. Time spent awaiting approval is excluded.',
        ],
        'max_run_tokens' => [
            'label' => 'Actual usage budget per execution',
            'unit' => 'Tokens',
            'help' => 'Default: 64000. Accumulates provider-reported usage across steps. No further step starts after the budget is reached; requests already sent may exceed the remaining quota.',
        ],
    ],
    'errors' => [
        'unavailable' => 'AI settings are temporarily unavailable',
        'unavailable_help' => 'The saved configuration could not be decrypted. Ask your administrator to check the application encryption key or restore a configuration backup, then refresh the page. The current configuration has not been changed.',
        'stale' => 'AI settings were changed by another administrator. Refresh the page and try again.',
        'default_provider' => 'A default provider requires an endpoint URL and the model for the selected capability.',
        'database_required' => 'AI settings require a database settings repository.',
    ],
];
