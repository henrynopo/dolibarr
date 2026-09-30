# SLY Dropshipping（背靠背订单）与会计日期

## 业务场景

**Dropshipping**：货品由供应商直接发往客户，发运单（shipment）在逻辑上既属于销售订单（SO）也属于采购订单（PO）。SLY 的会计记账日期规则（以 shipment 的 ATA/ETA 作为发票记账日）正是为这种模式设计的。

## 当前流程与问题

1. **订单生成**：常见两种顺序——**先 SO 后 PO**（从 SO 生成 PO）或**先 PO 后 SO**（先建采购订单，再建/链接销售订单）。发运单**只能从 SO 创建**（Dolibarr 标准），标准不会把 shipment 与 PO 建立关联，导致采购发票与发运在数据上脱节。
2. **一单一发运假设**：当前逻辑默认每个 SO/PO 只对应一个 shipment；若一个 SO/PO 对应多个 shipment，按“PO→SO→第一个 shipment”取日期会出错。
3. **票据与跟踪**：销售/采购发票若未直接关联到具体 shipment，会计日期只能通过 SO/PO 链推断，容易错位。

## 方案概览

### 1. 简化订单与发运关联（已实现）

- **发运创建时自动链接 PO**  
  当从 SO 创建发运单时（trigger `SHIPPING_CREATE`），自动查找与该 SO 通过 `element_element` 关联的所有采购订单（PO），并写入 `element_element`：  
  `(fk_source=expedition_id, sourcetype='shipping', fk_target=po_id, targettype='order_supplier')`  
  这样同一 shipment 会同时关联 SO 和 PO，便于：
  - 从 PO 直接找到对应 shipment（用于采购发票会计日期）
  - 从 shipment 看到关联的 SO 与 PO（背靠背视图）

- **推荐操作顺序**  
  - **先 SO 后 PO**：1）创建 SO → 2）从 SO 创建 PO（“创建采购订单”，`origin=commande&originid=SO_ID`）→ 3）从 SO 创建发运单 → 4）保存后 trigger 自动把该 shipment 关联到上述 PO。  
  - **先 PO 后 SO**：1）创建 PO → 2）创建 SO 并与该 PO 建立关联（或在 SO 上关联已有 PO，`element_element` 中 SO↔PO 任一方为 source/target 均可）→ 3）从 SO 创建发运单 → 4）保存后 trigger 会查找**所有与该 SO 关联的 PO**（包括“PO 为 source、SO 为 target”的链接），并自动建立该 shipment 与这些 PO 的链接。  

  无论先建 SO 还是先建 PO，只要在**从 SO 创建发运单之前**已把 SO 与 PO 在系统中关联好，发运单保存后都会自动链接到对应 PO，无需再手工维护 SO–shipment–PO 关系。

### 2. 支持多 shipment、避免“脱节”

- **一个 SO/PO 可对应多个 shipment**  
  通过 `element_element`，一个 SO 可以关联多张发运单，一张发运单也可以关联多个 PO（例如一个 SO 拆成多张 PO 时）。  
- **会计日期以“发票 ↔ shipment”为准**  
  - 若**销售发票**或**采购发票**在 `element_element` 中**直接关联了某张 shipment**，则会计日期只使用该 shipment 的 ATA/ETA（或配置的其它日期字段）。  
  - 若没有直接关联，则回退到：  
    - 采购发票：先查 PO 是否直接关联了 shipment（见上），有则用该 shipment 的日期；否则再走 PO→SO→shipment 链，取第一个找到的 shipment。  
  这样即使一个 PO 对应多个 shipment，只要发票在创建/编辑时关联到具体 shipment，就不会取错日期。

### 3. 票据创建与跟踪建议

- **销售发票**  
  - 从发运单创建发票时，Dolibarr 会设置 `origin=shipping, origin_id=expedition_id`，并写入 `element_element`，即已与 shipment 直接关联，会计日期会正确取该 shipment 的 ATA/ETA。  
- **采购发票**  
  - 从 PO 创建采购发票时，当前不会自动关联 shipment。  
  - 若希望该采购发票使用某张 shipment 的日期，可：  
    1. 在**采购发票卡**的“关联元素”中，手工添加与对应 **发运单** 的链接；或  
    2. 依赖“PO 已与 shipment 关联”（由上面 trigger 建立），则仍按 PO→shipment 取日期；若该 PO 只对应一个 shipment，行为正确；若多个 shipment，建议对每张发票在“关联元素”中指定 shipment。  
- **发运单卡**  
  - 可显示已关联的 SO、PO，以及已关联的销售/采购发票（通过 `element_element`），便于核对背靠背订单和票据。

## 技术要点

- **Expedition ↔ PO 链接**：由 `interface_99_modSlyCustom_SlyCustomTriggers.class.php` 在 `SHIPPING_CREATE` 时写入 `element_element`（expedition 为 source，PO 为 target）。  
- **会计日期优先级**（`SLY_AccountingDate` 与 Odoo Connector）：  
  1. 发票 ↔ shipment 直接链接（`element_element` facture/facture_fourn ↔ expedition）  
  2. 采购发票：PO ↔ shipment 直接链接（同上 trigger 建立）  
  3. 否则：销售发票用 SO→shipment；采购发票用 PO→SO→shipment（兼容旧数据）。

## 参考

- 会计日期规则细节：`docs/SLY-ACCOUNTING-DATE-RULES.md`  
- Odoo Connector 会计日期配置：选择“交货/收货日期”及 shipment 日期字段（ATA/ETA 等）。
