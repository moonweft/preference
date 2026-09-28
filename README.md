# 系统配置

`moonweft/preference` 管理 host 级系统配置。第一组配置是 Laravel AI SDK，入口为后台 **系统配置 → AI 配置**（`/admin/system/ai`）。

## AI 配置

- 为文本、图像、语音合成、语音转文字、向量嵌入和搜索重排序分别选择默认服务商。
- 按已安装 SDK 的能力显示服务商选项，支持 OpenAI、Anthropic、Gemini、DeepSeek、Ollama、OpenAI 兼容接口等。
- 默认配置和每个服务商各有独立 Tab；切换 Tab 保留未保存的输入，页面保存时统一提交。
- 保存各服务商的密钥、接口地址、默认模型和向量维度；Azure 使用部署名称，Bedrock 提供 Bearer Token 和区域配置，其余 AWS 凭据沿用应用环境。
- 模型标识由管理员填写，留空使用 SDK 或应用配置的默认值。校验应用基线与数据库覆盖合并后的最终配置：默认 OpenAI 兼容服务商必须配置接口地址和相应模型，包括选择“使用应用默认值”的情况。
- 配置优先级：业务调用显式指定的服务商／模型 → 本页面覆盖值 → `config/ai.php` 与环境变量 → SDK 默认值。
- 「使用限制」Tab 管理每分钟请求数、每日请求数、每日用量预算、单次输出上限和会话上下文容量。留空沿用 `moonweft-ai.limits` 的应用配置；只接受 1 至 2147483647 的整数，0 不代表无限制。

| 使用限制 | 内核默认值 | 范围 |
| --- | --- | --- |
| 每分钟请求次数 | 10 次 | 同一账号的所有会话和业务入口 |
| 每日请求次数 | 100 次 | 同一账号的所有会话和业务入口 |
| 每日用量预算 | 250000 预估 token | 同一账号的所有会话和业务入口 |
| 单次回复输出上限 | 2048 token | 每次回复 |
| 会话上下文容量 | 262144 字节（256 KiB） | 历史消息存储量与当前输入 |

限额统一配置、按账号独立累计，沿用 AI 配置管理的 IAM 权限和并发编辑保护。设置保存在 `preference_ai.limits`，保存后从下一次请求生效，无需重启或清理配置缓存；已有用量不会清零。每日额度按应用时区的零点重置，页面会展示该时区。每日预算按历史存储字节、输入字节和输出上限保守预留，**不是服务商实际 token 用量**，长会话会更快消耗预算；本配置不改变当前预留算法。

设置使用 Spatie Laravel Settings，保存在共享 `settings` 表的 `preference_ai` 分组中。服务商配置整体加密，密钥不会回填到 Livewire 页面状态。密钥留空保留已保存值；勾选“移除已保存的密钥”后恢复使用环境密钥。其他字段清空后恢复应用默认值。多人编辑时，过期页面会拒绝覆盖新设置。

配置在 AI 执行前读取。通过 `moonweft/ai` 的 `prompt()` 或 `stream()` 调用时，模块注册的 `ExecutionMiddleware` 会在业务授权后、Agent 创建前加载配置；同步调用或完整流式消费结束后恢复之前的配置并清除缓存的服务商实例。失败时也会恢复配置，AI 内核记录失败并释放运行占用。

限额通过 `SystemAiLimits` 接入 AI 内核的 `RequestLimits`，在创建运行和调用模型之前生效；预检查只读取限额，不解密服务商凭据。执行作用域内同时应用输出上限，成功、异常或嵌套调用结束时均恢复原配置。首次升级需要运行下面的设置迁移以添加 `limits`，迁移只创建空覆盖值，不会重置已有服务商配置或限流计数。

普通网页请求、队列启动、定时任务和 Artisan 命令不再自动读取 AI 凭据。配置无法解密时只阻断 AI 调用，不会静默回退到环境密钥继续发送。获授权管理员仍能打开 AI 配置页查看错误提示；页面暂停编辑，运维修复加密密钥或恢复配置备份后刷新，原数据不会被自动重置。

直接使用 Laravel AI SDK（未经过 `moonweft/ai`）时，显式包裹实际调用：

```php
use Moonweft\Preference\Ai\AiConfiguration;

$response = app(AiConfiguration::class)->run(
    fn () => \Laravel\Ai\agent()->prompt('示例问题'),
);
```

此入口只加载系统配置，不替代业务授权或 AI 模块的会话隔离。原生惰性流必须在回调内完整消费，不能将未执行的流返回到配置作用域外。嵌套调用会恢复外层配置；同一进程交叠执行的不同 Fiber 会被拒绝，避免同时改写共享的 SDK 配置。队列及常驻循环在每次 AI 调用时使用上述入口。构建与配置缓存命令不会因启动而加载数据库密钥。

## 接入与权限

宿主通过 Composer 路径仓库安装本模块，并在 Filament 面板注册 `PreferencePlugin::make()`。模块只加载 `database/settings` 下的设置迁移；现存主题代码尚未接入。

```sh
php artisan migrate --path=modules/preference/database/settings
php artisan iam:sync
```

有效的超级管理员可直接管理配置，其他管理员需要后台访问权限及 `preference.ai.manage` 权限。该权限会出现在现有 IAM 权限目录中。

页面只接受 `admin` guard 对应的 IAM 身份；每次页面交互和保存都会通过 `PrincipalResolver` 重新加载账号，校验启用状态、面板权限和 AI 管理权限。账号停用、删除或撤权后，已打开的页面也会拒绝继续操作。编辑状态绑定带认证提供方的完整 IAM 身份，切换管理员后必须重新打开页面，不能沿用前一个账号的编辑状态。

配置范围固定为整个 host，共用一份系统配置。后台管理员和普通用户的身份空间独立，即使数字 ID 或权限名相同也不能互用权限。写入范围固定为 `preference_ai`，只接收已注册服务商及字段；各服务商密钥分别保留和删除，不影响其他服务商或模块的设置。这里不存储用户对话和业务数据；租户／组织独立的 AI 凭据不属于当前配置模型。

数据库初始化只创建空覆盖值，不写入真实密钥、不调用 AI 服务。使用 `moonweft/ai` 隔离会话时，按 [AI 模块安装说明](../ai/README.md) 显式启用 `moonweft-ai.isolated_conversations` 并执行模块迁移：

```sh
php artisan migrate --path=modules/ai/database/migrations
```

会话使用 `ai_conversations`、`ai_messages`、`ai_runs`，已有安装也需包含 `content_blocks` 增量迁移。SDK 原生 `agent_conversations` 迁移不能替代这些表。启用隔离存储会替换全局 `ConversationStore` 绑定，有状态 Agent 必须通过 AI 模块入口执行；详见上述说明。只有独立使用 SDK 原生会话存储时才安装 SDK 对应迁移。

配置服务商不会自动开启隔离存储、注册业务 Agent 或开放 MCP 工具。当前 Host 保持通用 UI 和未配置入口状态。

## Laravel MCP

模块将 `laravel/mcp ^1.0` 声明为正式运行依赖，生产环境执行 `composer install --no-dev` 时也会安装，避免仅通过开发工具 Laravel Boost 间接依赖。

Laravel MCP 提供两种接入能力：客户端连接外部 MCP 服务，将其工具交给 Laravel AI Agent；服务端将 host 的业务能力作为 MCP 工具、资源和提示词提供给外部客户端。

当前接入范围为运行依赖，尚未创建 MCP 配置页、外部连接或业务 MCP 端点。后续 MCP 配置管理和工具调用需要各自的 IAM 授权；开放业务工具时，必须沿用业务模块的数据范围校验。AI 配置管理权限不代表获得业务工具调用权限。

参考：[Laravel MCP 官方文档](https://laravel.com/docs/13.x/mcp)。

## 验证

在 host 根目录运行：

```sh
php -d memory_limit=1G vendor/bin/phpunit tests/Feature/AiSystemSettingsTest.php
php -d memory_limit=1G vendor/bin/phpunit tests/Feature/AiLimitsSettingsTest.php
```

测试使用内存数据库和模拟 HTTP 响应，覆盖实时 IAM 授权、账号切换、设置分组隔离、密钥加密与隐藏、有效默认配置校验、并发编辑、执行配置恢复、损坏密文的故障边界，以及通过 AI 内核发送的实际 SDK 请求参数。测试不访问真实 AI 服务。

参考：[Laravel AI SDK 官方文档](https://laravel.com/docs/13.x/ai-sdk)。
