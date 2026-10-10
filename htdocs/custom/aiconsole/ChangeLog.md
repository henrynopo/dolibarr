# ChangeLog — AI Console

All notable changes to this module are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.3.0] - 2026-10-10

### Changed
- **撤下「工具」主菜单，模块转为完全独立**。描述文件不再声明任何菜单项
  （`$this->menu = array()`），1.0.0–1.2.0 写入的那 9 行菜单记录
  （1 个父项 + 8 个子项）在模块重新启用时一并清除。
  入口改为 Dolibarr 原生的管理页方式：**设置 → 模块 → AI Console → 「设置」按钮**
  （`config_page_url`，未变），也可直接访问 `/custom/aiconsole/admin/setup.php`。
- **控制台逻辑拆成「宿主页 + 渲染器」两层**：`admin/setup.php` 只负责引导、权限拦截、
  页面框架与事件消息落位，其余全部移入 `admin/_console.inc.php`。拆分是为了让宿主页
  一眼看得完，也让「哪些代码属于页面框架、哪些不属于」在文件边界上就是显式的。

### Removed
- 渲染器里原为「把控制台嵌进别的模块设置页」准备的 `plain` 分栏条模式与
  `$aiconsoleTabMode` 契约变量随之删除——本模块不再被任何其它模块的页面引用，
  留着就是永远走不到的死代码。分栏条固定走 `dol_get_fiche_head()`。

### Security
- 依赖面反而更小：本模块现在**不依赖任何其它自定义模块**。
- 权限闸不变：`admin/setup.php` 顶部的 `if (!$user->admin) accessforbidden();`
  是唯一入口闸，渲染器内部另有一道 `$user->admin && isModEnabled('aiconsole')` 复核。
- 全模块唯一的写操作依旧是「AI 日志工具」开关常量 `AICONSOLE_ENABLE_MCP_LOGTOOL`，
  表单 POST 回当前分栏、带 `newToken()`。
- 密钥类常量仍**只判存在性、永不回显**；请求日志仍只取列名白名单的 7 列，
  `query_text` / `raw_request_payload` / `raw_response_payload` 三列照旧不 select。
- 渲染器内的全部本地 SQL 仍带 `entity` 过滤、表名走 `$db->prefix()`，**循环内零查询**。
  两条日志查询移入 `aiconsoleRenderConsole()` 的函数作用域，不作为全局变量散出去。

### Fixed
- **写操作不再重定向**：原先开关提交后 `header('Location: ...'); exit;`。
  `exit` 会把页面截断在半截上（页脚、返回链接全丢），改为不重定向——表单本来就
  POST 回当前分栏，页面带着新状态原地渲染即可，省一次往返。

### Notes
- `php -l` 全部 PHP 文件通过；`dryrun/dryrun_aiconsole.php` **56/56 通过**。
- aiconsole 语言包 en_US / zh_CN 键集 diff 校验仍完全一致；
  脚本比对渲染器内全部 `trans('AiConsole*')` 与 `getSections()` 的 `label` / `titlelang`，**无未定义键**。
- 脚本校验：`getSections()` 的 8 个 key 与渲染器 `switch` 的 8 个 `case` 一一对应，无孤儿。
- 脚本校验：渲染器内**不含** `llxHeader` / `llxFooter` / `dol_get_fiche_end` / `main.inc.php`，
  只有一次 `dolibarr_set_const`，无 `header(`，无 `exit`。
- `git diff --stat -- ai/ core/ install/` 为空，核心零改动。
- 描述文件版本 1.2.0 → 1.3.0。**上线后需重新启用一次 aiconsole 模块**：
  菜单是启用时写库的，不重新启用的话左侧菜单里那 9 项会一直留着。

## [1.2.0] - 2026-10-09

### Changed
- **所有设置分栏不再跳出控制台（用户反馈的核心问题）**：原先 tab 栏与左侧菜单的 7 个子项
  直接指向 `/ai/admin/setup.php`、`assistant.php`、`server_mcp.php`、`configure_tools.php`、
  `custom_prompt.php`、`log_viewer.php`，点一下就跳进核心 `ai` 模块自己的页面框架，
  本模块只剩一个空壳导航。现在**每一个 tab 都是 `admin/setup.php` 自身的一个分栏**
  （`?leftmenu=aiconsole_xxx`），跳转到核心配置页的动作被收进分栏内部右上角
  **唯一的一个「前往核心 AI 配置页修改」按钮**——导航留在控制台内，写操作仍在核心页，
  不复制任何写逻辑，也不碰任何核心文件。
- tab 栏与左侧菜单改为**同源生成**：新增 `modaiconsole::getSections()` 单一注册表，
  `buildAiChildMenus()` 与 `admin/setup.php` 的 `$head` 都从它生成。此前两处各写了一份
  硬编码列表，这正是本会话里已经踩过一次「两边不一致」的同类隐患。

### Added
- 每栏的具体状态展示，全部为**只读**，数据来自常量与一次载入内存的工具清单：
  - **服务商设置** — `AI_API_SERVICE`、API Key 是否存在（仍永不回显）、
    `AI_API_<SVC>_URL` 接口地址，以及按 `function` 归并的
    `AI_API_<SVC>_MODEL_*` 模型覆盖（逐项标注「已覆盖 / 使用默认」）。
  - **AI 助手** — 开关、默认输入界面、PII 脱敏、写操作确认级别、系统提示词预览。
  - **MCP 服务器** — 开关、API Key 是否生成、服务账号、端点 URL、
    两个放行清单的原始值与解析后计数。**放行清单为空时标红**，
    并明确提示「留空 = 放行全部工具（含写操作）」。
  - **工具访问控制** — 完整工具表：按分类分组，逐个工具显示读写类型徽章
    （沿用核心 `configure_tools.php` 同一条正则启发式）与助手 / MCP 两列放行状态，
    系统工具单独标记（核心在 `executeTool()` 里豁免其放行清单）。
  - **自定义提示词** — 解析 `AI_CONFIGURATIONS_PROMPT` JSON，
    逐条显示功能代码、前置 / 后置提示词、黑名单词数。
  - **请求日志** — 开关、保留期、记录总数、最近一次请求，并内嵌**最近 20 条**请求表。
  - **AI 日志工具** — 原有内容移到独立分栏；此前开关 POST 回来后
    `leftmenu=aiconsole_logtool` 没有任何菜单项承接，左侧高亮会丢。
- 左侧菜单与 tab 栏各增一个「AI 日志工具」条目（`aiconsole_logtool`），共 8 项。

### Security
- **仍然只读**：新增的 6 个分栏不写任何常量、不触发任何写操作，
  唯一的写操作依旧是「AI 日志工具」开关（`AICONSOLE_ENABLE_MCP_LOGTOOL`）。
- 新增的请求日志表沿用 `AiConsoleLogTool` 的**列名白名单**：
  只取 `rowid / date_request / tool_name / provider / status / execution_time / error_msg`，
  **绝不 select** `query_text`、`raw_request_payload`、`raw_response_payload`。
- 密钥类常量（`AI_API_*_KEY`、`AI_MCP_API_KEY`）仍**只判存在性、永不回显**；
  本次新增显示的 `AI_API_<SVC>_URL` 与 `AI_API_<SVC>_MODEL_*` 均不以 `_KEY` 结尾，
  不属于 `dol_is_secured_key()` 的加密范围，且本身不是凭据。
- 3 处新增的原生 SQL 全部带 `entity` 过滤、表名走 `$db->prefix()` 动态取，
  且**循环内零查询**（工具清单经 `getToolsSchemaUnfiltered()` 一次载入内存）。
- `AI_INTENT_PROMPT` 与提示词正文只显示截断预览（200 / 150 字符），
  其余全部经 `dol_escape_htmltag()` 转义。

### Fixed
- **状态列类型判断错误（写代码时自查发现）**：`llx_ai_request_log.status` 是
  `varchar(50)`、存的是翻译后的文字（`Success` / `Confirm` / `Error`），
  不是数字码。改为一比一对齐 `ai/admin/log_viewer.php` 的判定方式。

### Notes
- `php -l` 全部 5 个 PHP 文件通过；`dryrun/dryrun_aiconsole.php` 56/56 通过。
- 语言包 en_US / zh_CN 键集经 diff 校验完全一致；脚本比对
  `setup.php` 中全部 `trans('AiConsole*')` 引用与 `getSections()` 的
  `label` / `titlelang`，**无未定义键**。
- `git diff --stat -- ai/ core/ install/` 为空，核心零改动。
- 描述文件版本号 1.1.0 → 1.2.0，**上线后需重新启用一次模块**以刷新菜单
  （8 个子项的 URL 全部变了）。

## [1.1.0] - 2026-10-09

### Added
- MCP 日志工具 `aiconsole_get_logs`：让 AI 调用方读取 Dolibarr 诊断日志用于排错。
  **核心里原本没有这个功能**——全仓库无代码读取 PHP `error_log`，MCP 的 8 类工具也无日志工具。
- 通过核心已预留的 `addMcpTools` 钩子注册（`ai/class/mcp.class.php:244`，context `aimcp`），
  **零核心改动**。描述文件新增 `'hooks' => array('aimcp')`。
- 4 个数据源（单一工具 + 枚举参数，非 4 个工具）：
  - `ai_requests` → `{prefix}ai_request_log`，取 rowid/date_request/tool_name/provider/
    status/execution_time/error_msg
  - `events` → `{prefix}events`，取 rowid/type/dateevent/fk_user/description
  - `syslog` → `dolibarr.log` 尾部 128KB 中含 `[McpHandler]` / `[MCP]` / `[AiConsole]` 标记的行
  - `ai_debug_file` → `dolibarr_ai.log` 的**元数据 + `Error:` 行**
- 新权限位 `aiconsole->mcp_logtool->read`（层级 `a`）。
- `admin/setup.php` 新增：开关常量 `AICONSOLE_ENABLE_MCP_LOGTOOL` 的按钮、
  三道闸门状态表、以及「预览 AI 将看到什么」审计区。
- `dryrun/dryrun_aiconsole.php`：离线自检脚本，56 项断言，无需数据库。

### Security
- **四道闸门，默认全关**：① 权限位 `rights[1][3] = 0` 不授予任何组；
  ② 常量默认不存在，此时工具**根本不被实例化**，`tools/list` 无法声明它；
  ③ 工具内 `checkAccess()` 每次调用重校验常量 + 权限 + 实体归属；
  ④ 实体过滤 + 列名白名单 + 行数(50)/时间窗(168h)/字节数(128KB) 三重硬上限 + 强制脱敏。
- **不读 `llx_ai_request_log` 的 `query_text` / `raw_request_payload` / `raw_response_payload`
  三列**（装着完整 prompt 与完整模型响应），亦不取 `llx_events.ip`（个人数据）。
- **不读 PHP `error_log`**：路径由 php.ini 决定，不在 Dolibarr 权限模型内，读它等于绕过全部四道闸门。
- **`dolibarr_ai.log` 只给元数据 + `Error:` 行**：`ai/class/ai.class.php:356-364` 用
  `var_export($headers)` 把完整 `Authorization: Bearer sk-...` 明文写入该文件且无遮盖，
  读正文等于把服务商 API Key 交给模型。
- **`isSystem()` 显式返回 `false`**：返回 `true` 会使本工具豁免
  `McpHandler::executeTool()` 的 allow-list 校验。
- 不依赖 `AI_MCP_SERVER_ALLOWED_TOOLS` 作为安全前提——它在全新安装时不被种入，
  `resolveAllowList('')` 返回**全部**工具（核心注释原文 `default open`）。
- CSRF：v24 **不存在** `checkToken()`；真正的机制是 `main.inc.php:420-429`
  对任何 POST 自动校验 token。本页额外 `define('CSRFCHECK_WITH_TOKEN', 1)` 覆盖 GET，
  且两个表单均用 POST（token 进查询串会落入 access log 与 Referer）。
- 预览复用工具自身的 `execute()`，不存在第二条更宽松的代码路径，
  页面上看到的 JSON 即 MCP 调用方收到的原文。

### Fixed
- **`$head` 结构错误导致 tab 栏渲染成乱码**：写成了 `$head[0] = dol_buildpath(...);`
  再 `.=` 拼接标题，得到的是一个**字符串**。而 `dol_get_fiche_head()` 是按
  `$links[$i][0..2]` **逐下标**读取的（`functions.lib.php:3186-3195`），
  字符串被当数组下标访问 → `$head[0][0]` 拿到 URL 首字符 `/`、`$head[0][1]` 拿到 `c`，
  页面顶部于是出现 `d` `i` 这类孤立字符（生产截图确认）。
  已改为核心约定的 `$head[$h][0]=url; [1]=label; [2]=active;` 三元素数组。
- **补齐 tab 栏：原先只有 1 个字符串，页面上没有任何一个指向其它页面的链接**。
  现按左侧菜单同一份 leftmenu key 建 8 个 tab（概览 + 7 个子项），
  `dol_get_fiche_head()` 的 `$active` 改为读 `GETPOST('leftmenu')`，
  tab 与左侧菜单因此始终指向同一页、且高亮一致。
- **`llxHeader()` 参数位错位，页面显示混乱（生产可见）**：原写成
  `llxHeader('', $title, '', '', 0, 0, '', '', 'mainmenu=...&leftmenu=...', '', 'mod-aiconsole page-admin')`。
  实际签名第 10 位是 `$morecssonbody`、**第 11 位是 `$replacemainareaby`**，
  而 `main.inc.php:1538-1539` 对后者执行 `print $replacemainareaby;` ——
  即**用这行字面量替换整个主区域**。页面因此只剩 `mod-aiconsole page-admin` 一串文字。
  已改为 `llxHeader('', $title, $help_url, '', 0, 0, '', '', '', 'mod-aiconsole page-admin')`，
  与 `ai/admin/setup.php:146` 逐位一致。附带删掉 `$morequerystring`：
  **全仓库 700+ 处 `llxHeader` 只有本模块这一处传 `mainmenu=`**，其余一律留空、
  靠菜单系统自身维持上下文。
- **`backtopage` 可注入 `javascript:` URL**：`nohtml` 只剥标签，不校验 scheme，
  而该值直接落进 `href="..."`。已加白名单正则，仅接受以 `/` 开头的本地绝对路径，
  并在输出时 `dol_escape_htmltag()`。核心 `ai/admin/setup.php:48` 用的 `alpha`
  会连 `/` 一起剥掉，导致「返回」链接永远失效（见 README 审核表第 3 条）。
- **`ISLOADEDBYSTEELSHEET` 守卫导致页面空白（生产致命）**：两个类文件头部都有
  `if (!defined('ISLOADEDBYSTEELSHEET')) die('Must be called by dolibarr');`。
  该常量在 **v24 中已彻底不存在**——全仓库只剩 CSS/JS 端点文件（`theme/*/style.css.php`
  等，它们不加载 Dolibarr 环境所以自行定义）还在用它，业务 class 文件早已弃用。
  结果是每次访问页面都直接 die，只输出那句话、其余全白。
  已移除两处守卫，与 v24 核心 class 文件一致（核心根本没有此守卫）。
  **这是照抄旧版本记忆里的 Dolibarr 惯例造成的，与项目规范「严禁使用已废弃的核心写法」冲突。**
- **`admin/setup.php` 引导路径错误（生产致命）**：原写 `require '../../main.inc.php'`。
  该写法对核心模块（`<module>/admin/`，两层）成立，但自定义模块在
  `custom/<module>/admin/` 是**三层**，`../../` 只回到 `custom/`，
  在生产上直接 `Failed to open stream` + Fatal，页面完全打不开。
  已改为逐层向上探测 `__DIR__/../../` 与 `__DIR__/../../../`，
  与 slycustom / sghr / sfrs_reports / odooconnector 的既定写法一致。
  本地实测：两层探测**不存在**，三层探测命中——与生产报错完全一致。
- 脱敏键值对规则的凭据字符集排除引号/花括号/方括号/逗号。原用 `\S+` 会把
  `{"api_key": "sk-..."}` 的收尾引号与括号一并吃掉，返回语法已损坏的 JSON。
  由 `dryrun` 脚本的脱敏用例捕获。
- **syslog 标记拼写错误**：原为 `[McpHandler]` / `[AiConsole]` / `[mcp_server]`，
  但全仓库**无任何一处**写入 `[mcp_server]`，同时漏掉了核心真实写入的
  `[MCP] Internal error:`——排错时最该看到的那一行。后果是静默返回空结果，
  与「一切正常」无法区分。已改为经全仓库核实的 `[McpHandler]` / `[MCP]` / `[AiConsole]`，
  并在 `dryrun` 中内嵌核心 7 条原始消息作为回归用例。

### Changed
- **视图层对齐核心 UI 约定**（用户反馈「页面显示混乱」后重做）：
  - 状态徽章改为调用核心 `dolGetBadge()`（`functions.lib.php:14557`），
    不再手写 `<span class="badge …">`；配色沿用 `ai/admin/log_viewer.php:383-391`
    的 status0 灰 / status3 黄 / status4 绿 / status8 红。
  - 两处表格补 `<thead>` / `<tbody>`，列头统一 `liste_titre`，数据行 `oddeven`，
    对齐类 `center` / `right` / `nowrap` / `tdoverflowmax200`（均已在 eldy 主题中核实存在）。
  - 段落分隔去掉裸 `<br><hr>`，改用核心 `load_fiche_titre()`；
    去掉全部内联 `style=`，改用 `opacitymedium` 等语义 class。
  - 行内跳转链接由字符串拼接改为 `dolBuildUrl(DOL_URL_ROOT.$path, …)`，
    表单 action 改为 `dolBuildUrl($_SERVER['PHP_SELF'], …)`，
    POST 回来后左侧菜单仍停在正确条目上（原先丢上下文，页面看起来像掉出菜单）。
- `syslog 标记`、脱敏规则等安全逻辑本轮**未改动**，dryrun 断言数保持 56。
- `aiconsoleBadge()` 去掉未使用的 `$langs` 形参（`php -l` 不报，IDE/phan 会报）。

### Notes
- `php -l` 全部 5 个 PHP 文件通过；`dryrun/dryrun_aiconsole.php` 56/56 通过。
- 额外实测：两个类文件在**未定义任何 Dolibarr 常量**的环境下可正常加载
  （即生产上失败的那个条件），已确认不再 die。
- `git diff --stat -- ai/ core/ install/` 为空，核心零改动。
- `custom/.gitignore` 白名单已复查含 `!/aiconsole/`。

## [1.0.0] - 2026-10-09

### Added
- `custom/aiconsole` 模块：Dolibarr 24 核心 `ai` 模块配置页面的只读导航总览。
- `工具 → AI 控制台` 菜单（父项 + 7 个子项），`enabled` 由
  `$conf->aiconsole->enabled && isModEnabled("ai")` 控制，
  **不受 `MAIN_FEATURES_LEVEL` 约束**。
- 兜住上游唯一无任何入口的页面 `ai/admin/log_viewer.php`（请求日志）。
- `admin/setup.php` 状态总览页：6 行功能状态一览，工具放行数经核心
  `McpHandler::getToolsSchemaUnfiltered()` / `McpHandler::resolveAllowList()`
  解析，与 `ai/admin/configure_tools.php` 显示口径一致。
- 密钥类常量（`AI_API_*_KEY`、`AI_MCP_API_KEY`）**只判存在性、永不回显**。

### Security
- 页面沿用上游 `ai/admin/*.php` 一致的 `$user->admin` 硬拦截，
  菜单 `perms` 另加一层；双重防线，绕过菜单直接访问 URL 同样被拒。
- 唯一一条原生 SQL 带 `entity` 过滤（multicompany），
  表名用 `$db->prefix()` 动态取（`llx_` / `llxsf_` 双前缀环境均可用），
  无循环内查询，无注入面。
- 模块**不建任何表、不存任何数据**：停用模块不影响 `ai` 模块配置。
  （1.0.0 时整页纯只读、不写常量；1.1.0 起新增唯一的写操作——由管理员手动切换
  `AICONSOLE_ENABLE_MCP_LOGTOOL`，且该常量不触碰 `ai` 模块的任何配置。）

### Notes
- `phpmin = array(7, 0)`，与上游 v24 全部核心模块描述文件逐字一致，未私自抬高。
- `MAIN_FEATURES_LEVEL >= 2` 可恢复上游自带的那 5 个 tab，本模块与之并存不冲突。
- 已在 `custom/.gitignore` 白名单补入 `!/aiconsole/`。该文件是「全量忽略 + 反向白名单」制，
  漏加此行会导致模块 6 个文件全部不纳入版本管理、不随部署上线。

[Unreleased]: https://example.com
[1.3.0]: https://example.com
[1.2.0]: https://example.com
[1.1.0]: https://example.com
[1.0.0]: https://example.com