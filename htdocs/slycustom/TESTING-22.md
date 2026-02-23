# 在 Dolibarr 22.0 上测试 slycustom

本分支（SLY22.0）基于 [官方 22.0.4](https://github.com/Dolibarr/dolibarr/tree/22.0.4)，仅包含 **slycustom 模块**，未应用针对 14.0 的核心补丁（补丁与 22.0 代码不兼容，需按需移植）。

## 测试步骤

1. **部署**
   - 本仓库分支 `SLY22.0` 即官方 22.0 + `htdocs/slycustom/`。
   - 将代码放到 Web 目录，配置 PHP + 数据库，按官方文档完成 22.0 安装或从旧版升级。

2. **启用模块**
   - 登录 Dolibarr → **首页** → **配置** → **模块/应用**。
   - 在「外部模块」中找到 **SLY Custom**，启用。

3. **可测试内容**
   - **PDF 模板**：在 设置 中为订单/发票/发货单/采购订单 选择 SLY 模板（如 pdf_eratosthene_SLY、pdf_sponge_SLY_consignee 等）。
   - **SLY Exports 菜单**：左侧菜单「商业」下的 SLY 导出入口。
   - ShipsGo 类已包含在模块中，但 22.0 核心无 ShipsGo 集成（无发货单卡片/列表的 API 调用与扩展字段），需按 `PATCHES-OFFICIAL-14.md` 思路在 22.0 上单独实现或放弃。

4. **核心补丁**
   - `patches/` 下补丁为 **14.0 专用**，不能直接 `git apply` 到 22.0。
   - 若要在 22.0 上恢复与 SLY14.0 相近的行为，需根据 `CORE-CUSTOMIZATIONS-FULL-LIST.md` 在 22.0 对应文件中逐项移植或重做补丁。

## 快速验证

```bash
# 克隆后切到本分支
git clone https://github.com/henrynopo/dolibarr.git
cd dolibarr
git checkout SLY22.0

# 或已有仓库
git fetch origin && git checkout SLY22.0
```

然后按官方 [Dolibarr 22 安装说明](https://wiki.dolibarr.org/index.php/Installation) 配置 Web 与数据库，访问 install 完成安装/升级，再在模块页面启用 SLY Custom。
