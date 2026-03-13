# 删除残余的 SLY 菜单（如 SLY ALL-in-One 重复项）

若在「工具 (Tools)」下看到重复或错位的 SLY 菜单（例如多个 SLY ALL-in-One、旧层级），可按下面任一方式清理。  
菜单存在表 `llx_menu` 中，由字段 `module = 'slycustom'` 标识。

---

## 方式一：界面停用再启用（推荐）

1. 进入 **设置 → 模块/应用**
2. 找到 **SLY Custom**，点击 **停用**
3. 再点击 **启用**

停用时会执行 `delete_menus()`，删除所有 `module='slycustom'` 的菜单；启用时会按 [modSlyCustom.class.php](../core/modules/modSlyCustom.class.php) 中当前 `$this->menu` 重新插入，只保留正确定义的一级 SLY Export、二级 SLY ALL-in-One、三级 5 个详情。

---

## 方式二：CLI 重建菜单（不关模块）

在服务器上执行（Dolibarr 需已启用 SLY Custom）：

```bash
# 从 htdocs 的上一级（仓库根）或 htdocs 内执行
php htdocs/custom/slycustom/cli_reset_sly_menus.php
```

脚本会先删除所有 SLY Custom 的菜单，再按当前模块定义重新插入，无需在界面停用/启用模块。

---

## 方式三：仅用 SQL 删除（不自动重建）

若只想清掉数据库里的 SLY 菜单、稍后再通过「启用模块」或方式二重建，可执行（请先备份或确认环境）：

```sql
DELETE FROM llx_menu
WHERE module = 'slycustom'
  AND menu_handler = 'all'
  AND entity IN (0, 1);   -- 按实际 entity 调整，单公司多为 0 和 1
```

执行后需在界面**重新启用 SLY Custom**（或先停用再启用），菜单才会按当前代码重新出现。
