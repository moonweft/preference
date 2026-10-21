# 系统配置

`moonweft/preference` 管理 host 级系统配置，包括统一后台主题、配色、日期时间显示和 Laravel AI SDK 配置。

语音转文字使用 Laravel AI SDK 已支持的服务商，在 AI 配置中选择默认服务商并配置凭据。
业务调用通过 `AiConfiguration::run()` 应用系统配置，继续使用原生 `Transcription` 接口。

## 主题

入口为后台 **偏好配置 → 主题**（`/admin/system/theme`）。主题和配色使用可直接点击的
卡片，界面主题提供 **Sharp**（默认，直角与紧凑布局）及 **Orbit**（圆角与柔和阴影），
并按请求加载对应的已构建样式。切换不会修改共享 Panel 实例中的请求状态。

配色提供 12 套主色与中性色组合：海洋蓝、午夜靛蓝、鸢尾紫、紫水晶、玫瑰红、日落橙、
琥珀金、森林绿、湖水青、冰川蓝、春芽绿和兰花紫。卡片直接预览按钮、背景、边框和正文，
没有颜色下拉框。升级保留已有颜色；原有组合不在预设中时，额外显示「当前配色」卡片。
主色用于按钮、链接和选中状态，业务状态颜色保留原有含义。

主题与配色应用于接入 PreferencePlugin 的后台及登录页，所有管理员共用。
浅色、深色和跟随系统使用 Filament 自带的用户菜单切换器，由各浏览器保存个人选择。
主题页不再配置明暗模式，也不写入或覆盖浏览器的明暗偏好；升级迁移移除旧的系统明暗设置。
保存后当前页面重新加载，其他已打开页面刷新后生效。独立门户不在本次后台主题范围内。
系统页保留日期时间设置，两页复用 `preference.system.manage` 权限、并发保护和审计。
修改其中一页不会覆盖另一页的配置；过期页面仍须刷新后再保存。

主题文案使用 `resources/lang/{locale}/theme.php`，系统文案使用 `system.php`，均支持
`zh_CN` / `en`。后端只接受已安装主题和受支持色板，不接受客户端提供的样式路径或 CSS。
Host 将 `resources/css/filament/admin/{sharp,orbit}.css` 纳入 Vite 构建，两份入口均导入
模块的 `resources/css/appearance.css`。部署时构建资源并执行下方迁移及 worker 重载；
之后切换主题或配色不需要重新构建。

## 系统设置

入口为后台 **偏好配置 → 系统**（`/admin/system/settings`），管理整个 Host 共用的
IANA 时区、日期格式和时间格式。时间格式支持 12 / 24 小时及是否显示秒，日期时间格式由
所选日期与时间格式组合。默认 `UTC`、`Y-m-d`、`H:i:s`，保持现有存储时区；例如选择
`Asia/Shanghai` 后，UTC 的 `2026-10-14 01:30:00` 显示为 `2026-10-14 09:30:00`。

这组设置是明确共享的非敏感系统资源，所有用户、组织和后台入口读取同一份
`preference_system` 配置，不接受客户端提交的组织或租户范围。只有通过当前后台身份、
面板访问权限及 `preference.system.manage` 检查的管理员可以修改。系统设置和 AI 配置
的管理权限独立，复用 IAM 账号授权；没有身份、账号停用、撤权或切换账号后都不能保存。
多人同时编辑时，旧页面拒绝覆盖新配置。成功提交后记录 `preference.system.updated`
结构化日志，包含操作者、Host 范围及变更前后值。

Filament 表格、详情和日期时间输入的默认显示统一接入此设置。选择器使用可配置格式的
非原生输入，保存时仍转换回 UTC；纯日期（如生日）及纯时间（如每天营业时间）保留原值。
显式指定的业务格式和时区继续优先，接口序列化、数据库存储、定时任务、业务日界和
AI 每日额度的应用时区不由显示设置改写。自定义 Blade、通知和导出中手写的格式不会被
自动替换，应在所属模块使用统一格式化入口；它不会修改传入的日期对象：

```php
use Moonweft\Preference\System\SystemSettings;

$display = app(SystemSettings::class)->formatDateTime($record->created_at);
```

设置按请求／任务使用 `scoped` 实例。全局组件回调在执行时从当前容器解析设置，不持有
旧 worker 的实例，也不修改 PHP 或 Laravel 的全局时区。保存后下一次请求或任务读取新值，
已打开的页面刷新后生效。部署新增代码时先执行迁移和权限同步，再重载 HTTP / 队列 worker；
之后仅修改设置值不需要重启：

```sh
php artisan migrate --path=modules/preference/database/settings
php artisan iam:sync
php artisan octane:reload
php artisan queue:restart
```

迁移管理日期时间和外观设置，不修改历史业务时间。验证使用
`modules/preference/tests/Feature/SystemSettingsTest.php` 和
`modules/preference/tests/Feature/SystemSettingsOctaneTest.php`，以及
`modules/preference/tests/Feature/SystemAppearanceOctaneTest.php`（在 Host 根目录运行 PHPUnit），覆盖
实际表单保存、IAM 拒绝、并发覆盖、夏令时、跨日显示、输入 UTC 往返，以及同一 Octane
worker 的 A → B → A 请求、主题色板刷新、缺失写入身份、异常恢复和连续任务。该配置自身没有组织私有
数据；这些验证不代表其他模块的数据隔离已完成审计。

参考：[Filament 日期时间选择器](https://filamentphp.com/docs/5.x/forms/date-time-picker)。

## AI 配置

入口为后台 **偏好配置 → AI**（`/admin/system/ai`）。

- 为文本、图像、语音合成、语音转文字、向量嵌入和搜索重排序分别选择默认服务商。
- 按已安装 SDK 的能力显示服务商选项，支持 OpenAI、Anthropic、Gemini、DeepSeek、Ollama、OpenAI 兼容接口等。
- 默认配置和每个服务商各有独立 Tab；切换 Tab 保留未保存的输入，页面保存时统一提交。
- 保存各服务商的密钥、接口地址、默认模型、简单查询/复杂任务模型 ID 和向量维度；Azure 使用部署名称，Bedrock 提供 Bearer Token 和区域配置，其余 AWS 凭据沿用应用环境。
- 模型标识由管理员填写，留空使用 SDK 或应用配置的默认值。校验应用基线与数据库覆盖合并后的最终配置：默认 OpenAI 兼容服务商必须配置接口地址和相应模型，包括选择“使用应用默认值”的情况。
- 配置优先级：业务调用显式指定的服务商／模型 → 本页面覆盖值 → `config/ai.php` 与环境变量 → SDK 默认值。
- 「使用限制」Tab 管理每分钟请求数、每日请求数、每日用量预算、单次输出上限、会话上下文容量、PDF 与图片附件限制，以及单次执行的轮数、工具次数、单工具次数、结果容量、时间和实际 token 预算。留空沿用 `moonweft-ai.limits` 的应用配置；只接受 1 至 2147483647 的整数，0 不代表无限制。

| 使用限制 | 内核默认值 | 范围 |
| --- | --- | --- |
| 每分钟请求次数 | 10 次 | 同一账号的所有会话和业务入口 |
| 每日请求次数 | 100 次 | 同一账号的所有会话和业务入口 |
| 每日用量预算 | 250000 预估 token | 同一账号的所有会话和业务入口 |
| 单次回复输出上限 | 2048 token | 每次回复 |
| 会话上下文容量 | 1048576 字节（1 MiB） | 最近最多 100 条原生消息的明文载荷与当前输入 |
| 每次附件数量 | 3 个 | 每次发送的 PDF、JPEG、PNG、WebP 文件 |
| 单个附件大小 | 10240 KiB（10 MiB） | 同时受 PHP 与 Web 服务器上传上限约束 |
| 会话附件容量 | 24576 KiB（24 MiB） | 最近上下文及本次附件的加密存储容量 |
| 单个 PDF 页数 | 10 页 | 整份校验，超过拒绝；另有 100 页解析硬上限 |
| 会话图片与 PDF 页数 | 20 张 / 页 | 最近最多 100 条消息及当前输入合计 |
| 单次模型轮数 | 6 | 同一任务及审批续跑 |
| 工具调用总数 / 单工具次数 | 12 / 3 | 同一任务及审批续跑，业务可收紧 |
| 单工具结果容量 | 65536 字节 | 返回后停止超量结果的后续推理 |
| 执行时间预算 | 120 秒 | 模型/工具边界检查，不含审批等待，不强杀事务 |
| 实际 token 预算 | 64000 | 累计逐轮实际用量，达到后不再发起下一步 |

限额统一配置、按账号独立累计，沿用 AI 配置管理的 IAM 权限和并发编辑保护。设置保存在 `preference_ai.limits`，保存后从下一次请求生效，无需重启或清理配置缓存；已有用量不会清零。每日额度按应用时区的零点重置，页面会展示该时区。每日预算按最近上下文明文字节、输入字节和输出上限保守预留，**不是服务商实际 token 用量**；不重复计算密文膨胀、表格展示副本或审计元数据。历史记录仍按原有保留策略保存，模型默认最多带入最近 100 条消息，尚不自动摘要更早的历史。容量超限时聊天窗口保留输入并提示调整配置，模型服务商自身的上下文窗口不会因本配置增加而扩大。

设置使用 Spatie Laravel Settings，保存在共享 `settings` 表的 `preference_ai` 分组中。服务商配置整体加密，密钥不会回填到 Livewire 页面状态。密钥留空保留已保存值；勾选“移除已保存的密钥”后恢复使用环境密钥。其他字段清空后恢复应用默认值。多人编辑时，过期页面会拒绝覆盖新设置。

附件经 AI 内核加密和范围隔离，PDF 在服务器通过 Poppler 按页转为图片，再交给支持视觉的当前文本模型。每张图片/PDF 页的输入预算固定预估为约 2048 token，实际视觉用量以服务商为准；加密附件存储容量另行限制。设置图片生成服务商不等于启用了文档视觉识别。部署需执行 AI 的 `000006` 附件迁移并提供 Poppler，详见 [PDF 与图片输入](../ai/docs/attachments.md)。

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

文本模型档位使用 `providers.<name>.models.text.simple/complex`，空值沿用默认模型。不会自动推断 Pro/Smartest 别名或承诺能力提升；请填写已评测、服务商实际支持的模型 ID。现有服务商设置结构即可保存，无需新增设置迁移；AI 内核的运行 telemetry 需补跑其 `000005` 增量迁移。

开发环境在「AI 配置 → 使用限制」选择「开发阶段不限额」，保存为
`limits.development_unlimited=1`。它只在 local/testing 生效，解除请求频率、每日用量、
模型轮数、工具次数/结果容量和执行预算，保留用量账本。下方预算值不被覆盖，关闭开关后
立即恢复。生产环境、模型/附件容量、网络与队列运行边界及授权/审批不受此开关放宽。
留空时沿用 `MOONWEFT_AI_DEVELOPMENT_UNLIMITED`，默认关闭。


### DeepSeek 推理强度

在「AI 配置 → DeepSeek → 推理强度」选择 `low/high/max/none`，保存为
`providers.<name>.reasoning_effort`。仅接受枚举，不允许透传任意服务商请求字段。
留空继承应用或服务商默认值；`none` 关闭深度推理。当前员工助手通过 Laravel AI
`HasProviderOptions` 在同步与流式请求中读取该设置，其他业务 Agent 需显式接入原生契约。
该设置只用于匹配当前默认服务商 driver 的请求；审批仍沿用原服务商与模型，其 driver
与当前默认服务商不同时不传这项参数。同 driver 的多个别名沿用当前默认服务商的强度。

DeepSeek V4 Pro 支持 `low`。其他型号请核对其支持档位，并用业务问题验证正确性后
调整。本轮开发环境选择 Pro + low，未降低 IAM、数据隔离、业务校验或审批要求。
每次调用读取当前系统配置，正常、异常和后续 Octane 请求均恢复执行前配置；仅保存
字段不需要迁移。首次部署这项代码仍须 reload HTTP 与队列 worker。

参数依据：[DeepSeek 思考模式](https://api-docs.deepseek.com/guides/thinking_mode/)。

## 短信配置

后台 **偏好配置 → 短信**（`/admin/system/sms`）使用
[`overtrue/easy-sms`](https://github.com/overtrue/easy-sms) 3.5，支持阿里云、腾讯云、七牛云和云片。
提供系统开关、主服务商、可选备用服务商、自动切换开关、1–60 秒单次请求超时、签名与凭据。
短信和自动切换均默认关闭。主备不能相同，启用自动切换须选择备用，启用短信时两边凭据
均须完整。模板 ID、短信内容及变量由业务模块提供，主备各自完成签名和模板审核。

短信界面、服务商名称及本模块产生的校验/错误提示支持简体中文（`zh_CN`）和英文（`en`），
文案位于 `resources/lang/{locale}/sms.php`，使用 `moonweft-preference::sms` 命名空间，
按当前请求语言解析，不在静态属性中保存翻译结果。

这是 Host 级共享基础设施配置，所有组织共用；不属于组织业务数据，也不向普通用户公开。
页面和后端写入均需 IAM 平台权限 `preference.sms.manage`，每次保存重新核验当前管理员，
撤权、停用、切换身份及过期页面均拒绝保存。凭据整体加密存储，密钥不回填浏览器，留空
保留，可显式移除；启用中的主、备用服务商必填凭据不完整会阻止保存。审计仅记录操作者、
Host 范围及非敏感配置，不记录密钥。无法解密时拒绝修改，由运维修复加密密钥或恢复备份。

安装或升级后执行：

```bash
composer update moonweft/preference overtrue/easy-sms
php artisan migrate --path=modules/preference/database/settings
php artisan iam:sync
php artisan octane:reload
php artisan queue:restart
```

可信后端业务在已完成收件人授权、范围校验和频率限制的执行点解析
`app(\Overtrue\EasySms\EasySms::class)`，使用 Easy SMS 原生 `send()` 接口。
客户端按次创建并读取当前已保存配置，不在单例、长驻闭包或队列任务载荷中保留实例或凭据。
禁用或配置无效时解析失败。容器提供 `SmsClient`（继承 Easy SMS），保留原生消息接口，
但逐个调用服务商，最多一主一备；不要自行 `new EasySms` 绕过切换策略。业务负责短信
模板审核、重复发送防护、异步任务主体和范围复核、失败处理及送达回执；受理不等于送达。

自动切换采用保守白名单：HTTP 401/403 鉴权拒绝，以及已确认的账户余额/权限错误才可
尝试备用。阿里云支持余额不足、欠费停机、未开通产品、账户不存在、RAM 权限不足；
腾讯云支持套餐余量不足及指定鉴权错误；云片支持余额不足、API Key/API/IP 权限错误和
功能未开启。七牛云暂仅识别 HTTP 鉴权拒绝，其余错误不推测。错误码依据：
[阿里云](https://help.aliyun.com/zh/sms/developer-reference/api-error-codes)、
[腾讯云](https://cloud.tencent.com/document/api/382/55981)、
[云片](https://global.yunpian.com/official/document/sms/zh_CN/returnvalue_common)。

超时、连接异常、HTTP 5xx、服务商内部错误、未知错误码及缺少受理标志的响应均停止，
由业务查询服务商记录后决定下一步；手机号错误、限频、退订/黑名单和内容问题不通过备用
绕过限制。主服务商受理后立即停止；备用失败也不会循环重试。两次请求总耗时可能接近
单次超时的两倍，调用方须预留预算。

每次尝试写入 `preference.sms.attempt` 日志，只记录发送关联 ID、服务商、顺序、结果、
白名单错误码及是否继续备用，不记录手机号、内容、验证码、模板参数或凭据。
返回结果及 `NoGatewayAvailableException::getResults()` 保留各次尝试，额外提供
`send_id` 和 `outcome`（`accepted` / `rejected` / `unknown`）；业务可在自己的授权范围内
关联发送记录。日志不是业务送达台账，含原始响应的结果/异常也不可直接写入普通日志。
未知结果不得让队列盲目重试整个发送操作，应先核验服务商记录。

使用 Easy SMS 的原生回调分别提供主备模板及变量，回调应保持无副作用。例如：

```php
$result = app(\Overtrue\EasySms\EasySms::class)->send($authorisedPhone, [
    'template' => static fn ($gateway): string => $approvedTemplates[$gateway->getName()],
    'data' => static fn ($gateway): array => $templateVariables[$gateway->getName()],
]);
```

`$approvedTemplates` 和 `$templateVariables` 由所属业务模块提供，应覆盖配置中的主备
服务商。原生 `send()` 第三个参数或消息 `getGateways()` 仍可由可信业务显式选择通道，
最多两个受支持通道，仍执行相同拒绝判断；常规调用省略它们以沿用系统主备配置。
本模块只配置发送通道，保存不发送短信、不验证真实凭据，也不提供公共发送接口。
