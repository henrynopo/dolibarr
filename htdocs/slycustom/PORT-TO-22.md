# 将 CORE-CUSTOMIZATIONS-FULL-LIST 全部功能移植到 Dolibarr 22.0.4

本文档说明如何把 **CORE-CUSTOMIZATIONS-FULL-LIST.md** 中列出的全部核心定制，以补丁或等效修改的形式移植到 **官方 22.0.4**。

---

## 一、目标与前提

- **目标**：在 22.0.4 上恢复与 SLY14.0 相同的业务功能（多币种、ShipsGo、列表列/合计、发票/付款/订单/采购/报价等）。
- **前提**：
  - 已有 **SLY14.0** 与 **官方 14.0** 的差异（`patches/sly14.0-*.patch` 及 `CORE-CUSTOMIZATIONS-FULL-LIST.md`）。
  - 当前 22.0 分支（如 SLY22.0）基于 **官方 22.0.4**，且已包含 **slycustom 模块**。
- **重要**：14.0 的 `.patch` 不能直接在 22.0 上 `git apply` 成功，因 14→22 代码结构、行号、部分 API/类名/钩子均有变化，需「按逻辑移植」或「apply 后逐冲突手工合并」。

---

## 二、为何不能直接打 14.0 补丁

| 原因 | 说明 |
|------|------|
| **行号与上下文** | patch 基于 14.0 行号，22.0 同一文件行号、函数顺序可能不同，`git apply` 常报 offset/context 不匹配。 |
| **代码结构** | 22.0 可能拆分/合并了文件、改了函数名或类名，同一段逻辑所在文件或方法不同。 |
| **API/钩子** | 部分 API、trigger、hook 在 22.0 中改名或参数变化，需按 22.0 文档适配。 |
| **数据库/安装** | 安装脚本、migration、表结构 14→22 已有多次变更，不能直接套用 14.0 的 SQL 或 install 修改。 |

因此：要么 **在 22.0 上按「逻辑」逐项重做**，要么 **尝试 apply 后逐个冲突手工合并**（仍以 22.0 代码为准）。

---

## 三、两种总体策略

### 策略 A：尝试应用 14.0 补丁 + 解决冲突（适合快速试水）

1. 在**干净官方 22.0.4** 上按 `patches/README.md` 中的补丁顺序，依次执行：
   ```bash
   git apply --ignore-whitespace --3way htdocs/slycustom/patches/sly14.0-expedition-shipsgo.patch
   # 若有冲突：编辑冲突文件，解决后 git add；再继续下一块
   ```
2. **优点**：可能有一部分块能自动合入，减少手工量。  
3. **缺点**：冲突可能很多，且自动合并结果不一定正确，需逐块核对逻辑（尤其 14→22 有 API 变化时）。

### 策略 B：按清单逐文件「逻辑移植」（推荐，可控）

1. 以 **CORE-CUSTOMIZATIONS-FULL-LIST.md** 为权威清单，按**模块/目录**分批处理。
2. 对每个需移植的文件：
   - 在 SLY14.0 仓库中执行：  
     `git diff upstream/14.0..SLY14.0 -- 文件路径`  
     得到「相对官方 14.0 的差异」。
   - 在 **22.0.4** 中找到**对应文件**（路径可能相同，也可能在 22.0 中已移动/重命名）。
   - 在 22.0.4 的该文件上，**手工做同等逻辑**：把 14.0 的改动意图在 22.0 的代码结构中实现（必要时查阅 22.0 的类/API 文档）。
3. **优点**：不依赖行号，逻辑清晰，适合 22.0 API 变化时的适配。  
4. **缺点**：工作量大，需逐文件对照。

**建议**：先按 **策略 B** 做「最小必要集合」（见下节），再视需要扩展到全清单；若某一块（如仅 expedition）想先试水，可对该块用策略 A 试 apply，冲突再退回策略 B。

---

## 四、推荐执行顺序（按依赖与影响面）

移植时建议按以下顺序，避免遗漏依赖：

| 顺序 | 内容 | 说明 |
|------|------|------|
| 1 | **数据库 / SQL** | ShipsGo 的 `llx_expedition_extrafields` 列；若有其它 14.0 独有的表/列，需对照 22.0 表结构后决定是否新增或改用 22.0 已有列。 |
| 2 | **核心 lib / class** | `core/lib/*.php`、`core/class/*.php`（多币种符号、PDF、价格、合计模板等），被多处业务引用。 |
| 3 | **核心 triggers / boxes** | Workflow 关闭订单、仪表盘 widget 等。 |
| 4 | **expedition（发货）** | ShipsGo 集成、列表订单列、批量更新状态（依赖 core 与 DB）。 |
| 5 | **compta（发票/付款）** | 多币种、列表合计、自动关闭、来源订单等。 |
| 6 | **commande（客户订单）** | 销售代表、来源采购订单、多币种、排序、草稿改客户等。 |
| 7 | **fourn（采购/供应商）** | 采购代表、来源销售订单、列表合计、禁止 PDF、付款多币种等。 |
| 8 | **comm（报价/商业）** | remx 折扣拆分、报价列表/卡片/API。 |
| 9 | **accountancy + admin + API** | 会计、后台、API 相关。 |
| 10 | **其它模块** | adherents、contact、contrat、product、projet、reception、societe、ticket、user 等（见 CORE 清单 2.11）。 |
| 11 | **语言包** | `htdocs/langs/`：可先移植 en_US/zh_CN 等常用语种。 |
| 12 | **install/脚本/构建** | 按需：若 22.0 已有等效机制则不必照搬 14.0。 |

同一阶段内，建议按 **patches/README.md** 里拆分的补丁对应关系处理（见下节）。

---

## 五、按补丁/区域与 CORE 清单的对应关系

以下把「现有 14.0 补丁」与 **CORE-CUSTOMIZATIONS-FULL-LIST.md** 的章节对应，便于你按块移植并在 22.0 上生成新补丁。

| 14.0 补丁文件 | CORE 清单章节 | 22.0 移植要点 |
|---------------|----------------|----------------|
| **sly14.0-expedition-shipsgo.patch** | §2.9 发货 (expedition) | 在 22.0 的 `expedition/card.php`、`list.php`、`class/expedition.class.php`、`class/api_shipments.class.php` 等上做 ShipsGo 提交、列表订单列、批量更新；22.0 若已有 shipment API 变更需适配。 |
| **sly14.0-compta.patch** | §2.7 财务/发票/付款 (compta) | 多币种合计、已付判定、贷项符号、付款列表/卡片、自动关闭、linkedobject、列表合计等；注意 22.0 的 facture/paiement 类与 API 可能更名或参数不同。 |
| **sly14.0-commande.patch** | §2.6 客户订单 (commande) | 列表销售代表、来源采购订单、多币种、排序、草稿改客户、linkedobject、API；对照 22.0 的 commande 列表与 API。 |
| **sly14.0-fourn.patch** | §2.10 采购/供应商 (fourn) | 采购代表、来源销售订单、列表合计、禁止 PDF、付款多币种、API、linkedobject；22.0 的 fourn 路径与类名需确认。 |
| **sly14.0-comm.patch** | §2.5 商业/报价 (comm) | remx 折扣拆分、报价 card/list/API；对应 22.0 的 comm/propal 等。 |
| **sly14.0-core.patch** | §2.8 核心 (core) | lib、class、boxes、triggers、Workflow、PDF、多币种符号、list_print_total、incoterm 等；体量最大，建议先做 lib/price、lib/pdf、list_print_total 等被多处引用的部分。 |
| **sly14.0-accountancy-admin-api.patch** | §2.1 会计、§2.3 后台、§2.4 API | accountancy/*、admin/*、api/*；22.0 的 API 与模块结构可能有变。 |
| **sly14.0-other-modules.patch** | §2.11 其它业务模块 | accountancy、adherents、contact、contrat、product、projet、reception、societe、ticket、user 等；按业务优先级选做。 |
| **sly14.0-misc.patch** | §4 安装/构建/脚本/其它 | install、main.inc、theme、modulebuilder、public、includes 等；仅移植 22.0 仍需要的部分。 |
| **sly14.0-langs.patch** | §3 语言包 | 可直接尝试 apply 或按 22.0 的 lang 键逐文件合并。 |

---

## 六、单文件「逻辑移植」的具体步骤（策略 B 细化）

对 **CORE-CUSTOMIZATIONS-FULL-LIST.md** 中列出的每一个文件：

1. **确认 22.0.4 中是否存在该路径**  
   - 若不存在：检查是否在 22.0 中改名/合并到其它文件，用 22.0 源码搜索对应功能所在文件。

2. **生成 14.0 差异**（在含 SLY14.0 与 upstream/14.0 的仓库中）：
   ```bash
   git diff upstream/14.0..SLY14.0 -- htdocs/expedition/card.php
   ```
   保存或打印该 diff，作为「要实现的逻辑」清单。

3. **在 22.0.4 的对应文件中实现**  
   - 逐块看 14.0 的 diff：增加的是哪段逻辑（例如「确认时调 ShipsGo API」「列表多一列订单」）。  
   - 在 22.0 的等价位置（可能是不同行号或不同方法名）加入**等效代码**，并注意：  
     - 22.0 的类名、方法名、常量名是否已改；  
     - 22.0 是否已有类似功能（避免重复或冲突）。

4. **测试**  
   - 该文件涉及的功能在 22.0 上走一遍（如发货单确认、列表筛选、付款合计等）。

5. **记录**  
   - 在 `patches/PATCHES-BY-MODULE.md` 或 `patches/APPLY-ON-22.md` 中注明该文件已移植，或为该 22.0 修改生成 `sly22.0-*.patch`。

---

## 七、产出物建议

1. **针对 22.0.4 的新补丁**  
   - 在 22.0.4 上完成移植后，用 `git diff` 生成针对 22.0.4 的补丁，例如：  
     `patches/sly22.0-expedition-shipsgo.patch`、`sly22.0-compta.patch` 等，与 14.0 的命名对应，便于日后 22.0.x 升级时重打。

2. **patches/APPLY-ON-22.md** 与 **patches/PATCHES-BY-MODULE.md**  
   - 维护 22.0.4 上需要应用的补丁列表及顺序（APPLY-ON-22）；  
   - 按功能模块的 14/22 对应与缺口（PATCHES-BY-MODULE）；  
   - 数据库变更（如 expedition_extrafields 的 ShipsGo 列）见 APPLY-ON-22。  

3. **更新 patches/README.md**  
   - 增加「22.0 补丁」小节，指向 `patches/APPLY-ON-22.md`、`patches/PATCHES-BY-MODULE.md` 和 `patches/sly22.0-*.patch`。

---

## 八、生成「按文件 diff」的参考命令（便于策略 B）

在**包含 SLY14.0 与 upstream/14.0** 的仓库根目录（与 htdocs 同级）执行：

```bash
# 导出某一模块所有文件的 diff 到目录（便于逐文件查看）
MODULE=expedition
mkdir -p /tmp/sly-diffs/$MODULE
git diff upstream/14.0..SLY14.0 -- htdocs/$MODULE/ | \
  csplit -s -f /tmp/sly-diffs/$MODULE/ - '/^diff --git/' '{*}'
```

或按 CORE 清单逐文件：

```bash
git diff upstream/14.0..SLY14.0 -- htdocs/expedition/card.php htdocs/expedition/list.php \
  > /tmp/sly-expedition-port-reference.patch
```

在 22.0 上移植时，打开该 reference 作为「逻辑清单」，在 22.0 的对应文件中实现，而不是直接 apply 该 patch。

---

## 九、小结

- **全部** CORE-CUSTOMIZATIONS-FULL-LIST 功能移植到 22.0.4 = 按清单逐文件在 22.0 上做**等效逻辑**（或对 14.0 补丁 try apply 后手工解决冲突）。
- **推荐**：先做 **数据库 → core（lib/class/triggers）→ expedition → compta → commande → fourn → comm**，再 accountancy/admin/API 与其它模块，最后语言包与杂项。
- **产出**：22.0.4 专用 `sly22.0-*.patch` + 更新 **patches/APPLY-ON-22.md** 与 **patches/PATCHES-BY-MODULE.md** + **patches/README.md**，便于日后 22.0.x 升级时重复应用与维护。

若你希望从**最小可用**开始，可先只做 **PATCHES-OFFICIAL-14.md** 中的「ShipsGo 最小补丁」在 22.0 上的等价实现（DB + expedition/card + expedition/list），再按业务优先级逐步扩大至全清单。
