# Box 多币种支持检查清单

检查时间：按代码库当前状态。  
多币种逻辑：启用 `multicurrency` 模块且单据有多币种时，显示 `multicurrency_total_ht` + `multicurrency_code`，否则显示 `total_ht` + 公司默认币种。

---

## 一、已支持多币种（Core 已统一支持，SLY 重复 box 已删除）

| Box | 文件 |
|-----|------|
| **待收货的采购订单** | `core/boxes/box_supplier_orders_awaiting_reception.php` |
| **最新采购订单** | `core/boxes/box_supplier_orders.php` |
| **最新客户发票** | `core/boxes/box_factures.php` |
| **逾期未付客户发票** | `core/boxes/box_factures_imp.php` |
| **最新供应商发票** | `core/boxes/box_factures_fourn.php` |
| **逾期未付供应商发票** | `core/boxes/box_factures_fourn_imp.php` |
| **最新客户订单** | `core/boxes/box_commandes.php` |
| **最新商业提案** | `core/boxes/box_propales.php` |

以上 8 个 core box 均已：在 SQL 中按 `isModEnabled('multicurrency')` 增加 `multicurrency_total_ht`、`multicurrency_code`，展示时若有 `multicurrency_code` 则用多币种金额+币种。  
slycustom 中对应的 7 个重复 box（订单/发票/待收货）已删除，仅保留 `box_sly_lastactions`、`box_sly_birthdays`。

---

## 二、未支持多币种（仍用 total_ht + $conf->currency）

以下为其他尚未支持多币种的 box（列表类 8 个已在 core 中全部支持多币种）。

### 1. Core 汇总/其他金额类 Box（汇总或非订单/发票）

| Box | 文件 | 说明 |
|-----|------|------|
| **活动/待办（提案/订单/发票按状态汇总）** | `core/boxes/box_activity.php` | SUM(total_ttc) 按状态，统一用 $conf->currency；多币种需按币种汇总或折算，改动较大 |
| **销售漏斗（商机金额）** | `core/boxes/box_funnel_of_prospection.php` | opp_amount，用 $conf->currency；商机表若有币种字段可做多币种 |
| **项目商机** | `core/boxes/box_project_opportunities.php` | opp_amount，price() 未传币种；若项目/商机支持多币种可加 |
| **会员最近订阅** | `core/boxes/box_members_last_subscriptions.php` | 订阅金额，一般单币种 |
| **会员按年订阅统计** | `core/boxes/box_members_subscriptions_by_year.php` | 按年汇总金额，一般单币种 |
| **产品/库存预警** | `core/boxes/box_produits.php`, `box_produits_alerte_stock.php` | 产品售价 price/price_ttc，多为公司币种 |

### 2. 图表类 Box（按金额绘图）

| Box | 说明 |
|-----|------|
| `box_graph_invoices_permonth.php` | 发票金额按月，统计类用 total_ht 汇总 |
| `box_graph_invoices_peryear.php` | 发票按年 |
| `box_graph_invoices_supplier_permonth.php` | 供应商发票按月 |
| `box_graph_orders_permonth.php` | 订单金额按月 |
| `box_graph_orders_supplier_permonth.php` | 采购订单按月 |
| `box_graph_propales_permonth.php` | 提案按月 |

图表为汇总曲线，多币种需在统计类中支持“按币种”或“折算为同一币种”，属统计层改造，未在本次 box 逐行显示检查中实现。

---

## 三、订单首页内联表格（已支持多币种）

| 位置 | 说明 |
|------|------|
| **commande/index.php** | 页面内 “Orders to process”“Orders that are in process” 两个表格已增加 `total_ht` / `multicurrency_total_ht`、`multicurrency_code` 的 SELECT 与金额列，展示逻辑与 core box 一致（有 multicurrency_code 时显示多币种金额+币种）。 |

若在 **societe 首页/卡片** 右侧看到“最新客户订单”等 box，其来自 `core/boxes/box_commandes.php`，core 已支持多币种；若仍显示单币种，请确认未用旧版或自定义覆盖该 box。

---

## 四、建议实施顺序

1. ~~**与 SLY 对齐的 7 个 core 列表 box**~~ 已完成（core 8 个 box 已支持多币种，SLY 重复 box 已删）。
2. ~~**commande/index.php 内联 “Orders to process” 表格**~~ 已支持多币种（含金额列）。
3. **box_activity.php**（可选）：  
   若需多币种，可考虑按币种分组汇总或仅对“默认币种”汇总并注明。
4. **商机/项目/会员/产品/图表**：  
   按业务需要再决定是否支持多币种及实现方式。

---

## 五、修改模式（供实现参考）

对“列表类” box（每行一张单据、显示一个金额）：

1. **SQL**（在已有 `total_ht` 等后追加）：  
   `if (isModEnabled('multicurrency')) { $sql .= ", c.multicurrency_total_ht"; $sql .= ", c.multicurrency_code"; }`  
   （表别名 `c` 按实际替换为 `f` 等。）

2. **展示**：  
   ```php
   $amountText = price($objp->total_ht, 0, $langs, 0, -1, -1, $conf->currency);
   if (isModEnabled('multicurrency') && !empty($objp->multicurrency_code)) {
       $amountText = price($objp->multicurrency_total_ht, 0, $langs, 0, -1, -1, $objp->multicurrency_code);
   }
   // 然后 'text' => $amountText
   ```

3. **发票表** 多币种字段名：`multicurrency_total_ht`, `multicurrency_code`（与采购订单一致）。  
4. **销售订单/提案** 表：需确认是否具备 `multicurrency_total_ht`、`multicurrency_code` 字段后再改。
