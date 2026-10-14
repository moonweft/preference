# Preference module

- 在 MoonWeft 工作区内遵守根目录和 Host 的 `AGENTS.md`，包括业务职责、数据隔离和
  Octane 生命周期约束。
- 按用户持续授权，每次完成代码修改和相关验证后，只提交当前任务负责的改动，并推送到
  本仓库 `origin/main`，无需再次确认。保留其他会话的工作区和暂存内容，不强制覆盖历史。
- 集成测试在 Host 根目录使用 `php vendor/bin/phpunit modules/preference/tests/Feature/`
  运行；Octane 回归需要 Host 已安装并配置 Laravel Octane。
