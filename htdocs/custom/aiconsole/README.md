# AI Console (aiconsole)

> Dolibarr 24 官方 `ai` 模块配置的**只读控制台**：8 个分栏全部在同一个页面内切换，
> 每个分栏详细展示该主题当前的配置状态，修改动作则通过分栏内唯一的按钮交回核心页面。
> 另含一个**默认关闭**的 MCP 日志读取工具（v1.1.0 起）。
> **入口：设置 → 模块 → AI Console → 「设置」按钮**（v1.3.0 起撤下 Tools 主菜单）。
> **不修改 Dolibarr 任何核心文件，不建表，不存储任何业务数据。**

## 这个模块解决什么问题

**第一部分（v1.0.0）：导航缺口。**

v24 的 `ai` 模块自带 5 个配置页面，但上游存在两处入口缺口：

1. **Tab 被功能等级挡住。**
   `ai/lib/ai.lib.php` 的 `aiAdminPrepareHead()` 会构建
   Settings / CustomPrompt / Assistant / MCPServer / ToolAccessControl 五个 tab，
   但后三个包在 `if (getDolGlobalString("MAIN_FEATURES_LEVEL") >= 2)` 里。
   功能等级低于 2 时，`setup.php` 顶部只剩 2 个 tab，MCP 相关配置无从进入。
   *（解决办法：`设置 → 主设置 → MAIN_FEATURES_LEVEL` 设为 `2`。
   该常量是全站功能等级闸门，会连带放开其它模块的 development 级入口，需自行权衡。）*

2. **`log_viewer.php` 完全没有入口。**
   它既没调用 `aiAdminPrepareHead()`，也不在任何菜单中，
   无论功能等级设多高，只能手敲 `/ai/admin/log_viewer.php` 才能访问。

本模块的分栏由自身的 `enabled` 表达式控制，**不受 `MAIN_FEATURES_LEVEL` 约束**，
因此功能等级为 0/1 时依然可达，并顺带兜住了 `log_viewer.php`。

**第二部分（v1.1.0）：AI 排错日志。**

「AI 能不能自己收集 Dolibarr 日志用于排错」——核心里**没有这个功能**。
全仓库无任何代码读取 PHP `error_log`，MCP 的 8 类工具里也没有日志工具。
本模块通过核心预留的 `addMcpTools` 钩子补上，**不改核心**，且**默认关闭**。
详见下文「MCP 日志工具」。

**第三部分（v1.2.0）：分栏留在控制台内。**

v1.0/v1.1 的 tab 与左侧菜单直接指向 `/ai/admin/*.php`，点一下就跳进核心页面框架，
控制台只是个空壳导航条（用户反馈：「这几个设置 tab，居然都是直接跳转到 AI 模块的设置页面，
而不是继续留在 aiconsole 的设置页面框架内」）。
现在**每个 tab 都是本页面自己的一个分栏**，跳转核心页的动作收进分栏内唯一的按钮。

**第四部分（v1.3.0）：撤下 Tools 菜单，回归独立模块形态。**

一个只读展示单个核心模块配置的页面，不该在左侧菜单里和备份、模块管理这类
工具型入口挤在一起。从 v1.3.0 起描述文件不声明任何菜单项，
入口就是 Dolibarr 原生的管理页方式：**设置 → 模块 → AI Console → 「设置」按钮**。
同时把渲染逻辑从页面里抽成 `admin/_console.inc.php`，
让「哪些代码属于页面框架、哪些不属于」在文件边界上就是显式的——
见下文「渲染器与宿主页」。

## 目录结构

```
custom/aiconsole/
├── README.md
├── ChangeLog.md
├── core/modules/
│   └── modaiconsole.class.php   # 描述文件 + 分栏注册表 getSections() + 权限位
├── class/
│   ├── actions_aiconsole.class.php   # addMcpTools 钩子：按开关决定是否注册工具
│   └── AiConsoleLogTool.class.php    # MCP 工具子类（全部安全逻辑在此）
├── admin/
│   ├── _console.inc.php         # 渲染器：全部控制台内容，不含页面框架
│   └── setup.php                # 宿主页：引导 + 权限拦截 + 页面框架 + 调用渲染器
├── dryrun/
│   └── dryrun_aiconsole.php     # 离线自检脚本，56 项断言，无需数据库
└── langs/
    ├── en_US/aiconsole.lang
    └── zh_CN/aiconsole.lang
```

## 依赖

| 依赖 | 说明 |
|---|---|
| Dolibarr | >= 24.0 |
| PHP | >= 7.0（与上游 v24 全部核心模块描述文件一致；运行时硬闸门为 `PHP_VERSION_ID >= 70300`） |
| 核心模块 `ai` | 必需。总览页需要；MCP 日志工具还额外依赖 `ai` 的 MCP 服务器已启用 |
| 数据库表 | 无新增。仅**只读**查询核心表 `{prefix}ai_request_log`、`{prefix}events` |

## 安装

1. 上传 `custom/aiconsole/` 到目标实例的 `custom/` 目录。
2. `设置 → 模块 → 找到 AI Console → 启用`。
   （模块不建表。`overview->read` 默认授予管理员；`mcp_logtool->read` **默认不授予任何人**。）
   **从 1.1.0 升级到 1.2.0、从 1.2.0 升级到 1.3.0 时都必须重新启用一次模块**：
   菜单是启用时写库的，不重新启用则菜单项与 URL 都是旧的。
3. 启用核心 `ai` 模块（若尚未启用）。
4. 启用核心 `ai` 模块（若尚未启用），然后
   **设置 → 模块 → 找到 AI Console → 「设置」**（或直接访问
   `/custom/aiconsole/admin/setup.php`）。

MCP 日志工具**默认关闭**，需要额外 6 步部署，见下文。

卸载 / 停用只移除权限记录，不会触碰 `ai` 模块的任何配置。

## 权限模型

| 层 | 控制 |
|---|---|
| 总览页权限 | `aiconsole->overview->read`，默认授予（`rights[0][3] = 1`，层级 `a`） |
| 日志工具权限 | `aiconsole->mcp_logtool->read`，**默认不授予任何组**（`rights[1][3] = 0`），层级 `a` |
| 页面硬拦截 | `admin/setup.php` 顶部 `if (!$user->admin) accessforbidden();` |
| 渲染器内复核 | `_console.inc.php` 再查一次 `$user->admin && isModEnabled('aiconsole')`（纵深防御，防将来新增宿主页时忘了查） |
| 工具内实检 | `AiConsoleLogTool::checkAccess()` 每次调用重新校验常量 + 权限 + 实体 |

控制台采用与上游 `ai/admin/*.php` **完全一致**的 `$user->admin` 硬拦截（而非仅靠菜单权限掩码），
因此即便有人绕过入口直接敲 URL 也会被拒。入口只是入口，不是防线。
分栏内的「前往核心配置页」按钮跳向的那些页面，各自保留着同样的 `accessforbidden()`。

v1.3.0 起本模块**不声明任何菜单项**，因此上表没有「菜单可见性」一行——
`enabled` / `perms` 这类表达式已经随 `$this->menu = array()` 一起删除。
入口是模块列表的「设置」按钮，与其它纯设置型模块一致。

## 分栏结构与页面分层

**8 个分栏都由 `modaiconsole::getSections()` 一处生成**，渲染器的分栏条、总览页的索引链接、
`switch` 的 `case` 分支三处都读它，不存在「两处各写一份」的漂移风险。

| leftmenu | 分栏名 | 分栏内含有的「去修改」目标 |
|---|---|---|
| `aiconsole_overview` | 概览 | 无（概览只做索引） |
| `aiconsole_provider` | 服务商设置 | `/ai/admin/setup.php` |
| `aiconsole_assistant` | AI 助手 | `/ai/admin/assistant.php` |
| `aiconsole_mcp` | MCP 服务器 | `/ai/admin/server_mcp.php` |
| `aiconsole_tools` | 工具访问控制 | `/ai/admin/configure_tools.php` |
| `aiconsole_prompt` | 自定义提示词 | `/ai/admin/custom_prompt.php` |
| `aiconsole_log` | 请求日志 | `/ai/admin/log_viewer.php` |
| `aiconsole_logtool` | AI 日志工具 | 无（本模块自有，唯一有写操作的分栏） |

### 渲染器与宿主页

全部控制台内容在 `admin/_console.inc.php`。它**不是页面**：不含 `llxHeader()` /
`llxFooter()` / `dol_get_fiche_end()`，也不 include `main.inc.php`。
宿主页 `admin/setup.php` 在 include 之前设好两个契约变量：

```php
$aiconsoleBaseUrl      // 宿主页的绝对 URL，不带查询串
$aiconsoleExtraParams  // 每个链接与表单都要带上的查询参数（如 backtopage）
```

分栏链接由 `aiconsoleSectionUrl()` = `dolBuildUrl($aiconsoleBaseUrl, $extraParams + leftmenu)` 拼出，
所以切分栏永远停在本模块自己的页面上。

include 与调用是**分开的两步**，这一点是刻意的：
`include` 里的 POST 处理和 `setEventMessages()` 必须发生在宿主页 `llxHeader()` **之前**，
因为把队列里的消息刷进页面正是 `llxHeader()` 干的活。

**为什么拆成两个文件**：`setup.php` 因此短到一眼看得完，
「哪些代码属于页面框架、哪些不属于」在文件边界上就是显式的，
而不是靠读代码时心里判断。这也是本模块吃过亏的地方——v1.0/v1.1 的分栏条与左侧菜单
各有一份硬编码清单，两边指向不同的页面，用户点一下就跳出控制台。

## 各分栏展示什么

| 分栏 | 展示内容 | 常量 / 数据来源 |
|---|---|---|
| 概览 | 6 行功能状态 + 「查看详情」跳本页分栏 | 见下表汇总 |
| 服务商设置 | 服务商、Key 存在性、接口地址、逐功能模型覆盖 | `AI_API_SERVICE`、`AI_API_<SVC>_KEY`（仅判存在）、`AI_API_<SVC>_URL`、`AI_API_<SVC>_MODEL_<FUNCTION>`（按 `getListOfAIFeatures()` 的 `function` 去重后逐项列出） |
| AI 助手 | 开关、输入界面、PII 脱敏、确认级别、系统提示词预览 | `AI_ASSISTANT_ENABLED`、`AI_DEFAULT_INPUT_MODE`、`AI_PRIVACY_REDACTION`、`AI_ASK_FOR_CONFIRMATION`、`AI_INTENT_PROMPT` |
| MCP 服务器 | 开关、Key 存在性、服务账号、端点、两个放行清单原始值与计数、系统工具数 | `AI_MCP_ENABLED`、`AI_MCP_API_KEY`、`AI_MCP_USER_ID`、`AI_*_ALLOWED_TOOLS`、`McpHandler::resolveAllowList()` |
| 工具访问控制 | 全工具表：分类分组、读写徽章、助手/MCP 两列放行、系统工具标记 | `McpHandler::getToolsSchemaUnfiltered()` + 与核心同一条读写正则 |
| 自定义提示词 | 功能代码、前置/后置提示词、黑名单词数 | `AI_CONFIGURATIONS_PROMPT`（JSON 解码） |
| 请求日志 | 开关、保留期、记录总数、最近一次请求 + 最近 20 条请求表 | `AI_LOG_REQUESTS`、`AI_LOG_RETENTION`、`{prefix}ai_request_log` |
| AI 日志工具 | 三道闸门状态、开关按钮、调用方预览 | `AICONSOLE_ENABLE_MCP_LOGTOOL`、右 `mcp_logtool->read`、`AI_MCP_ENABLED` |

### 三处「安全信号」在界面上是显式的

不是装饰，是把安全姿态摆在管理员眼前：

- **放行清单为空时标红**（工具访问控制 / MCP 服务器两栏都有黄条警告）。
  `resolveAllowList('')` 返回**全部**工具，核心注释原文就是 `default open`。
- **PII 脱敏关闭时标红**，确认级别为「从不」时标黄——这两项直接决定 AI 能看多少、能写多少。
- **API Key 只显示「已配置 / 未配置」，永不回显内容**（常量为加密存储）。

### 多公司（multicompany）

本页全部原生 SQL（共 2 条：总数统计 + 最近 20 条）均带 `entity` 过滤：

```php
$tablename = $db->prefix().'ai_request_log';
$sql = 'SELECT COUNT(*) AS nb, MAX(t.date_request) AS lastreq'
     .' FROM '.$tablename.' t'
     .' WHERE t.entity = '.(int) $conf->entity;
```

- **表名用 `$db->prefix()` 动态取，不硬编码。** 这是核心表，本地默认安装为 `llx_`，
  但 slyfood 生产库使用 `llxsf_` 前缀——硬编码字面量会在生产上直接报表不存在。
  （v24 中 `$db->prefix` 是**属性**、正确 API 是 `$db->prefix()` **方法**，别写错。）
- 拼接成分只有 `(int)` 整数与 `$db->prefix()` 返回值，无注入面。
- 无循环内查询：工具清单通过 `McpHandler::getToolsSchemaUnfiltered()` **一次性**载入内存，
  计数与分组全走内存数组。

MCP 日志工具的每一条 SQL 同样带 `entity` 过滤，且在**权限闸之外**另加一道实体闸
（服务账号所属实体必须覆盖当前实体），见下文。

---

# MCP 日志工具（v1.1.0）

让 AI 调用方能读到 Dolibarr 内部诊断日志，用于回答「刚才那次 AI 调用到底报了什么错」。

**零核心改动**：通过核心已预留的 `addMcpTools` 钩子注册
（`McpHandler::loadExternalTools()` @ `ai/class/mcp.class.php:244`，context `aimcp`）。

工具名 `aiconsole_get_logs`，**只有一个**——不是一个数据源一个工具。
allow-list 只多一个条目，且模型无法在多个日志读取器之间游走。

## 四道闸门，默认全关

任何一道不通过即拒绝；四道互相独立，关掉任一道不影响其余。

| # | 闸门 | 默认状态 |
|---|---|---|
| ① | 权限位 `aiconsole->mcp_logtool->read` | `rights[1][3] = 0` → **不授予任何组**，管理员须在权限页显式勾选 |
| ② | 常量 `AICONSOLE_ENABLE_MCP_LOGTOOL` | **不存在** → 工具根本不会被实例化 |
| ③ | 工具内 `checkAccess()` 实检 | 每次调用重新校验 ① ② 与实体归属 |
| ④ | 输出范围约束 | 实体过滤 + 列名白名单 + 行数/时间窗/字节数三重硬上限 + 脱敏 |

**① 为什么默认不授予**：`rights[0][3] = 1` 会默认授予管理员组，那样 ① 就等于常开，
失去意义。默认值取 0，管理员必须在权限页**逐组**勾选。

**② 为什么"不存在"而非"存在但为 0"**：开关关闭时 `ActionsAiconsole::addMcpTools()`
直接 `return`，工具类不会被 `new`。它不会出现在 AI 工具列表里，
MCP 端点的 `tools/list` 也**无法声明它**——不是"声明了但调用被拒"，是根本不声明。

**为什么 ④ 不能省**：即便前三道全开，④ 保证一次调用读不完整个库、
也保证即便将来新增了带密钥的列或日志格式变更，密钥也不会原样出网。

## ⚠️ 部署顺序（这个顺序本身就是安全设计）

1. 升级模块 → `设置 → 权限` 为**服务账号单独**勾选 `aiconsole->mcp_logtool->read`
2. 建一个**只读、非 admin** 的服务账号，`AI_MCP_USER_ID` 指向它
   —— 工具以该账号权限执行，账号最小化即爆炸半径最小化
3. `ai/admin/configure_tools.php` 把 `AI_MCP_SERVER_ALLOWED_TOOLS` 设为**显式白名单**
4. 回到本模块页面，点开 `开启 AI 日志读取`
5. 用「预览」确认暴露面符合预期
6. 收紧 `AI_MCP_SERVER_ALLOWED_TOOLS`，**仅保留** `aiconsole_get_logs`

> **第 3 步单独存在意义重大。** `AI_MCP_SERVER_ALLOWED_TOOLS` 在全新安装时**不被种入**
> （`modAi.class.php` 的 `$this->const = array()`），而 `resolveAllowList('')` 返回
> **全部**工具——`configure_tools.php` 源码注释原文就是 `default open`。
> 也就是说框架默认是全开的，这一步是唯一的兜底。**切勿留空。**

## 数据源范围

| source | 数据源 | 暴露内容 | 刻意排除 |
|---|---|---|---|
| `ai_requests` | `{prefix}ai_request_log` | rowid、date_request、tool_name、provider、status、execution_time、error_msg | `query_text`、`raw_request_payload`、`raw_response_payload` 三列（装着完整 prompt 与完整模型响应） |
| `events` | `{prefix}events` | rowid、type、dateevent、fk_user、description | `ip`（个人数据，对排错无增益） |
| `syslog` | `dolibarr.log` 尾部 128KB | **仅含 `[McpHandler]` / `[MCP]` / `[AiConsole]` 标记的行**，每行截断 300 字符 | 其余全部日志行 |
| `ai_debug_file` | `dolibarr_ai.log` | 文件大小、mtime，以及**行首为 `Error: ` 的行** | 正文全部——见下方警告 |

**为什么 `dolibarr_ai.log` 只给元数据 + 错误行**：
`ai/class/ai.class.php:356-364` 在 `AI_DEBUG` 打开时用 `var_export($headers)`
把**完整的 `Authorization: Bearer sk-...` 请求头**明文写进该文件，
毫无遮盖（`substr($apiKey, 0, 5)` 那层遮罩只作用于 `dol_syslog` 那一行）。
直接读它的正文等于把服务商 API Key 交给模型。

**为什么完全排除 PHP `error_log`**：路径由 `php.ini` 决定，
**不在 Dolibarr 的任何权限模型控制范围内**，读它等于绕过上面全部四道闸门。

## 统一脱敏

所有源返回前必经 `scrub()`：遮罩 `Bearer` 头、`api_key/secret/token/password` 键值对、
`sk-`/`pk-`/`ghp-`/`xox*` 前缀密钥、40 字符以上的 base64/hex 长串，最后截断到 300 字符。
这是**纵深防御**：即便将来新增了带密钥的列，密钥也不会原样出网。

> 实现注记：键值对规则的凭据字符集刻意排除引号、花括号、方括号与逗号。
> 用 `\S+` 会把 `{"api_key": "sk-..."}` 的收尾引号和括号一起吃掉，
> 返回一段语法已损坏的 JSON，反而误导读日志的人。

## 离线自检

不连数据库，直接加载工具类断言全部安全属性：

```bash
php custom/aiconsole/dryrun/dryrun_aiconsole.php
```

覆盖：脱敏 8 例、截断上限、行数/时间窗钳制、`isSystem()` 为 false、
工具名与参数枚举、四道闸门的开/关组合、多公司实体闸、
输入校验（路径穿越 / 数组 / null / 缺省）、
生成 SQL 的实体过滤与三列 payload 零命中、四个数据源均不致命错误、
syslog 标记过滤（逐条比对核心真实写入的 7 条消息）。

> 标记过滤这一组是从一次真实缺陷倒推出来的：原先写的是 `[mcp_server]`，
> 而全仓库**没有任何一处**写入这个前缀，同时漏掉了核心真正会写的
> `[MCP] Internal error:`——那恰恰是排错时最该看到的一行。
> 拼错标记的后果是静默返回空，看起来和「一切正常」完全一样。
> 因此该组用例直接内嵌核心的原始消息文本。

## MCP Server 快速启用

本模块的「MCP 服务器」分栏即是入口。三步：

1. 在「MCP 服务器」分栏点右上角「前往核心 AI 配置页修改」（目标 `/ai/admin/server_mcp.php`；
   必须 **admin 账号**登录，普通管理员组会被 `accessforbidden` 拒）
2. 打开 `AI_MCP_ENABLED` → 生成 API Key → 选择 `AI_MCP_USER_ID` 服务账号
3. 客户端侧连接（端点为 HTTP POST 的 JSON-RPC 2.0，无 SSE）

```
POST https://<your-dolibarr>/ai/server/mcp_server.php
Headers: X-API-Key: <AI_MCP_API_KEY>
```

Claude Code 客户端注册示例：

```bash
claude mcp add --transport http dolibarr https://<your-dolibarr>/ai/server/mcp_server.php \
  --header "X-API-Key: <AI_MCP_API_KEY>"
```

**安全须知**

- 必须走 HTTPS，且**务必用 `X-API-Key` 请求头**。服务端另支持
  `Authorization: Bearer` 与 `?api_key=` 查询串兜底；后者会把密钥写进 webserver
  access log 并可能随 Referer 泄漏，源码注释亦明确不推荐。
- `AI_MCP_USER_ID` 决定 MCP 能做什么——工具以该账号权限执行，**不会绕过权限**。
  建议单独建一个只读、限定实体的服务账号，不要直接给 admin。
- 必须在 webserver 层限制该端点的访问来源，并定期轮换 `AI_MCP_API_KEY`。
- `AI_MCP_ENABLED` / `AI_MCP_API_KEY` / `AI_MCP_USER_ID` 均为带 `entity` 的常量，
  多公司下每个实体各自一份。
- 放行工具时**务必显式勾选，不要留空**——留空等于全开，详见「MCP 日志工具」部署顺序第 3 步。

---

## 附：上游 `ai/admin/setup.php` 审核结论（只记录，不修改）

本模块不修复上游问题，仅在此登记备查。

| # | 位置 | 问题 | 可利用性 |
|---|---|---|---|
| 1 | `ai/admin/setup.php:136` | `$action = 'edit';` 无条件覆盖第 47 行 `GETPOST` 读入的值，使第 160 行 `elseif (!empty($formSetup->items))` 成为死代码 | 无（逻辑冗余） |
| 2 | `ai/admin/setup.php:210, 229` | `getDolGlobalString("AI_API_SERVICE")` 未转义直接拼进内联 JS 字符串字面量 | 极低——取值来自固定下拉选项，但属未转义拼接的坏范式 |
| 3 | `ai/admin/setup.php:48` | `$backtopage = GETPOST('backtopage', 'alpha')`；`alpha` 过滤器剔除 `://` 与 `/`，"返回"链接永远无法携带 URL | 无安全影响，功能失效 |
| 4 | `ai/admin/setup.php:152` | `llxHeader()` 未传 `mainmenu` / `leftmenu`，页面脱离菜单上下文 | 无（体验问题） |
| 5 | `ai/class/ai.class.php:356-364` | `AI_DEBUG` 打开时用 `var_export($headers)` 把**完整 `Authorization: Bearer sk-...` 头**明文写入 `dolibarr_ai.log`；`substr($apiKey, 0, 5)` 的遮罩只作用于 `dol_syslog` 那一行 | **高**——任何能读该文件的人拿到服务商 API Key。本模块的 `ai_debug_file` 源因此只给元数据 + `Error:` 行 |

## 维护者备注

v1.0.0 刻意保持**无 class 目录、无 JS、无 CSS、无 SQL 建表脚本**，
因为当时整页纯只读——改动面越小越安全。

v1.1.0 为接入 MCP 日志工具新增了 `class/` 与唯一的写操作（开关常量），
v1.2.0 把导航收进本页面并把状态展示从「一行一功能」细化到「一栏一主题」，
v1.3.0 撤下 Tools 菜单回归独立模块形态，并把控制台内容抽进 `_console.inc.php`。

### 分栏清单只有一处

**分栏条、总览索引、switch 的 case 三处都必须从 `modaiconsole::getSections()` 生成，
不要各写一份。** v1.0/v1.1 两处各有一份硬编码列表，结果是分栏条指向 `/ai/admin/*.php`、
左侧菜单也指向那里，用户一点就跳出去了。改分栏请改 `getSections()` 一个地方。
新增分栏时记得同步 switch 的 case，`dryrun` 会校验 8↔8 一一对应、无孤儿。

### `_console.inc.php` 是 include，不是页面

渲染器由宿主页 `require`，因此它**绝对不能**自己调 `llxHeader()` / `llxFooter()` /
`dol_get_fiche_end()` / `exit`，也不能 include `main.inc.php`。
尤其不要在写操作后 `header('Location: ...'); exit;`——`exit` 会把页面截断在半截上，
页脚和返回链接全丢。开关表单本来就 POST 回当前分栏，不重定向即可。

同一条道理：别在渲染器里另开 `tabBar` div，宿主页已经开了一个。

### 数据收集全部放在 `aiconsoleRenderConsole()` 函数里

分栏之前一次性收集（常量 + 一次 `getToolsSchemaUnfiltered()` + 2 条 SQL），
各 `case` 只负责渲染。新增分栏时把新数据加进函数顶部的收集区，**不要在 case 里发查询**。

整个收集区被放进函数作用域是有意的：`include` 会把渲染器的文件作用域变量带进宿主页，
`$sections` / `$leftmenu` 这类通用名撞上宿主页的同名变量是迟早的事。
全部收进函数后，渲染器与宿主页之间只剩下文件头写明的那两个契约变量。

### 描述文件里不要声明菜单

v1.3.0 起 `$this->menu = array()`。这是个只读展示单个核心模块配置的纯设置型模块，
入口用模块列表的「设置」按钮（`config_page_url`）就够了——
和每一个 setup-only 模块一样。挂到 Tools 那类工具型菜单下面，
等于让一个模块的配置页和备份、模块管理做邻居。
`config_page_url` 保留，模块列表的「设置」按钮照常工作。
**重新启用一次模块会删除 v1.0.0–1.2.0 写下的那 9 行菜单记录**——这是清理，不是刷新。

### `admin/setup.php` 直接 `new modaiconsole($db)` 是刻意的

描述文件只 `include_once DolibarrModules.class.php`、不碰数据库，
构造即读取成员，没有副作用。这样渲染器才能拿到与 `getSections()` **同一份**分栏清单。
代价是这个类必须在 `$conf`/`$langs` 就绪之后 new——本文件在 `main.inc.php` 之后，
满足条件。

### v24 API 事实（都踩过）

- **CSRF**：v24 **不存在** `checkToken()` 这个函数（写了就是致命错误）。
  真正的机制是 `main.inc.php:420-429` 对**任何 POST 自动校验 token**，缺失即 403。
  本模块的做法是：表单内植入 `newToken()`，并在页面顶部 `define('CSRFCHECK_WITH_TOKEN', 1)`
  把 GET 路径也纳入校验。两个表单都用 **POST**——token 出现在查询串里会进
  webserver access log 和 Referer 头。
- **`llx_ai_request_log.status` 是 `varchar(50)`，存的是翻译后的文字**
  （`Success` / `Confirm` / `Error`），**不是数字码**。
  比法要对齐 `ai/admin/log_viewer.php`：
  `$obj->status == $langs->transnoentitiesnoconv("Success")`。
  本模块曾按整数写过一版，恒不相等、状态徽章全灰。
- **模型常量的粒度是 `function` 不是 feature key**：
  `getListOfAIFeatures()` 里 8 个 text* 功能共用 `'function' => 'TEXT'`，
  核心存的是 `AI_API_<SVC>_MODEL_TEXT` 一个。展示时必须按 `function` 去重，
  否则同一行会重复 8 次。
- **`AI_API_<SVC>_URL` / `_MODEL_*` 是明文存储的**：
  `dol_is_secured_key()`（`functions.lib.php:713`）只匹配以 `_KEY` 之类的结尾，
  这些常量不以 `_KEY` 结尾，因此不加密。显示它们没有泄密风险；
  真正不能回显的只有 `_KEY` 类常量。
- **预览走工具自身的 `execute()`**，不存在第二条更宽松的代码路径，
  所以管理员在页面上看到的 JSON 就是 MCP 调用方会拿到的原文。
- **可回滚**：关掉 `AICONSOLE_ENABLE_MCP_LOGTOOL` 即刻生效，下一次调用即被拒，
  且工具不再被声明。停用模块同样能撤掉一切。
- **引导路径**：`admin/setup.php` 逐层向上探测 `main.inc.php`（`../../` 与 `../../../`）。
  **不要**改回 `require '../../main.inc.php'`——那个深度对核心模块的 `<module>/admin/`
  成立，对 `custom/<module>/admin/` 的三层结构不成立，只回到 `custom/`，
  在生产上会直接 Fatal、页面完全打不开。这是 v1.1.0 实际踩到的坑。
- **不要在 class 文件里写 `if (!defined('ISLOADEDBYSTEELSHEET')) die(...)`**：
  该常量在 v24 已被核心彻底弃用（只剩 `theme/*/style.css.php` 这类不加载 Dolibarr
  环境的端点文件还在自定义），业务 class 文件加了就会在每次访问时直接 die、页面全白。
  这也是 v1.1.0 实际踩到的坑，与项目规范「严禁使用已废弃的核心写法」直接冲突。
- **`dol_get_fiche_head()` 的 `$head` 必须是三元素数组，不是字符串**：
  它按 `$links[$i][0]` / `[1]` / `[2]` **逐下标**读 url / label / active。
  写成 `$head[0] = dol_buildpath(...); $head[0] .= '标题';` 会得到一个字符串，
  下标访问拿到的是 URL 的首尾字符——页面顶部渲染出 `d`、`i` 这类孤立字符。
  正确写法与 `ai/lib/ai.lib.php:416-419` 一致：
  `$head[$h][0] = dol_buildpath('/x/y.php', 1); $head[$h][1] = $label; $head[$h][2] = 'activekey';`
- **分栏条的 `$active` 要读 `GETPOST('leftmenu')`**，并且要对白名单兜底：
  渲染器先 `$leftmenu = GETPOST('leftmenu', 'aZ09', 0, 1);`，
  拿不到合法 key 时退回 `aiconsole_overview`，否则分栏条会一个高亮都没有。
- **页面内自己 `echo` 的 href 必须自己拼 `DOL_URL_ROOT`**：用
  `dolBuildUrl(DOL_URL_ROOT.'/ai/admin/xxx.php', …)`。（反过来，描述文件里将来若要写菜单，
  `url` **不要**自己拼——`core/menus/standard/auguria.lib.php:103` 会统一调
  `dol_buildpath($url, 1)` 补上，手工加会变成 `/dolibarr/dolibarr/...`。）
- **`llxHeader()` 的参数位不能数错**：第 10 位是 `$morecssonbody`（body 的 class），
  **第 11 位 `$replacemainareaby` 会用它的字符串替换掉整个主区域**（`main.inc.php:1538`）。
  把 `mod-xxx page-admin` 误放到第 11 位，页面就只剩这一串字。
  正确写法（与 `ai/admin/setup.php:146` 逐位一致）：
  `llxHeader('', $title, $help_url, '', 0, 0, '', '', '', 'mod-xxx page-admin')`。
- **`llxHeader()` 不要传 `mainmenu=`**：全仓库 700+ 处调用**只有本模块**曾往第 9 位
  `$morequerystring` 里塞 `mainmenu=...&leftmenu=...`，核心一律留空，
  由菜单系统自身维持上下文。
- **`backtopage` 不能只靠 `GETPOST()` 过滤器**：`'nohtml'` 只剥标签、不校验 scheme，
  `javascript:` 仍可整条塞进 `href`。核心的 `'alpha'` 更糟——它连 `/` 一起剥，
  「返回」链接必然失效。正确做法是白名单正则（只接受 `/` 开头的本地绝对路径）
  再加输出转义。
- **页面内链接一律走 `dolBuildUrl()`**：它**只拼查询串、不加 `DOL_URL_ROOT`**，
  所以路径必须自己写成 `DOL_URL_ROOT.'/ai/admin/xxx.php'`；
  直接 `'/ai/admin/xxx.php'` 只在站点恰好部署于域名根目录时才对。
- **描述文件不声明菜单，所以本模块没有「改菜单要重刷库」的问题**。
  但 1.2.0 → 1.3.0 升级时**必须重新启用一次模块**，目的相反：
  是为了让模块**删掉** v1.0.0–1.2.0 写进库的那 9 行菜单记录，不重新启用它们会一直留在左侧。
- **`llx_ai_request_log` 的三列 payload 永远不要 select**：
  `query_text` / `raw_request_payload` / `raw_response_payload` 装着完整 prompt 与完整模型响应。
  本页面的「最近 20 条」表与 `AiConsoleLogTool` 共用同一份列名白名单。