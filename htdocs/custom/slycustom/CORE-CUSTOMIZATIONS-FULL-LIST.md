# SLY14.0 相对官方 Dolibarr 14.0 的完整核心定制清单

本文档列出 **SLY14.0 与官方 14.0 之间所有被修改的核心文件**（不含仅新增的文件）。若要在官方 14.0 上达到与 SLY14.0 相同的业务功能，需要根据实际使用需求，对下列文件保留或重新应用相应定制。

**说明：**

- **“已知定制用途”** 来自 SLY 提交信息，仅作参考；同一文件可能还包含未在提交说明中写明的改动。
- 未标注用途的文件仍需通过 `git diff upstream/14.0..SLY14.0 -- 文件路径` 逐项对比。
- 语言包、安装/构建/脚本、数据库结构分别在后续章节列出。

---

## 一、已知定制用途概览（来自提交信息）

| 用途类别 | 涉及范围（示例） |
|----------|------------------|
| **多币种** | 发票/订单/付款列表金额与符号、仪表盘 widget、付款自动关闭、API 金额与联系人、linkedobject 模板、`MAIN_CURRENCY_SYMBOL_BEFORE_VALUE`、列表合计 |
| **ShipsGo** | 发货单 card/list、确认时提交 API、批量更新状态、扩展字段、CronJob |
| **列表与筛选** | 订单/发票/采购订单/发货单：来源订单列、销售/采购代表、订单号筛选、合计修正、排序、发货单 PDF 链接 |
| **工作流** | 发货关闭时关闭订单（#18326）、Workflow 触发器 |
| **发票/付款** | 贷项凭证 $ 符号、按多币种判定已付、付款列表与卡片多币种、实时付款合计、银行流水显示供应商付款 |
| **PDF/模板** | 银行账号字体、预付款、State 翻译、PDF 合计表货币符号、装箱单/订单/发票 SLY 模板 |
| **其它** | 报价 remx 折扣拆分、采购订单列表合计与禁止生成 PDF、草稿订单改客户（及回退）、发货单可修改/反确认、Cron 时区、API 联系人与付款日、地址 State 翻译、incoterm 等 |

---

## 二、htdocs 下被修改的核心逻辑文件（按目录）

### 2.1 会计 (accountancy)

```
htdocs/accountancy/admin/accountmodel.php
htdocs/accountancy/bookkeeping/balance.php
htdocs/accountancy/bookkeeping/card.php
htdocs/accountancy/bookkeeping/list.php
htdocs/accountancy/bookkeeping/listbyaccount.php
htdocs/accountancy/bookkeeping/listbysubaccount.php
htdocs/accountancy/class/accountancycategory.class.php
htdocs/accountancy/class/accountancyexport.class.php
htdocs/accountancy/class/accountancyimport.class.php
htdocs/accountancy/class/bookkeeping.class.php
htdocs/accountancy/customer/index.php
htdocs/accountancy/customer/lines.php
htdocs/accountancy/expensereport/index.php
htdocs/accountancy/index.php
htdocs/accountancy/journal/bankjournal.php
htdocs/accountancy/journal/expensereportsjournal.php
htdocs/accountancy/journal/purchasesjournal.php
htdocs/accountancy/journal/sellsjournal.php
htdocs/accountancy/supplier/index.php
htdocs/accountancy/supplier/lines.php
```

### 2.2 会员 (adherents)

```
htdocs/adherents/card.php
htdocs/adherents/class/adherent.class.php
htdocs/adherents/class/api_members.class.php
htdocs/adherents/class/api_memberstypes.class.php
htdocs/adherents/class/api_subscriptions.class.php
htdocs/adherents/subscription.php
```

### 2.3 后台/管理 (admin)

```
htdocs/admin/debugbar.php
htdocs/admin/dict.php
htdocs/admin/dolistore/class/dolistore.class.php
htdocs/admin/mails_templates.php
htdocs/admin/paymentbybanktransfer.php
htdocs/admin/supplier_order.php
htdocs/admin/supplier_payment.php
htdocs/admin/tools/dolibarr_export.php
htdocs/admin/tools/export_files.php
htdocs/admin/workflow.php
```

### 2.4 API

```
htdocs/api/class/api.class.php
htdocs/api/class/api_access.class.php
htdocs/api/class/api_documents.class.php
htdocs/api/class/api_setup.class.php
htdocs/api/index.php
```

### 2.5 商业/报价 (comm)

- **已知**：remx.php 折扣拆分等；报价列表/卡片/API。

```
htdocs/comm/action/card.php
htdocs/comm/action/class/actioncomm.class.php
htdocs/comm/action/index.php
htdocs/comm/index.php
htdocs/comm/mailing/card.php
htdocs/comm/propal/card.php
htdocs/comm/propal/class/api_proposals.class.php
htdocs/comm/propal/class/propal.class.php
htdocs/comm/propal/class/propalestats.class.php
htdocs/comm/propal/list.php
htdocs/comm/remx.php
```

### 2.6 客户订单 (commande)

- **已知**：列表销售代表、来源采购订单、多币种合计、排序、草稿改客户（及回退）、linkedobject 多币种、API 多币种与联系人。

```
htdocs/commande/card.php
htdocs/commande/class/api_orders.class.php
htdocs/commande/class/commande.class.php
htdocs/commande/class/commandestats.class.php
htdocs/commande/list.php
htdocs/commande/stats/index.php
htdocs/commande/tpl/linkedobjectblock.tpl.php
```

### 2.7 财务/发票/付款 (compta)

- **已知**：发票列表来源订单、多币种合计与已付判定、贷项 $ 符号、从发货单创建发票多币种、付款列表/卡片/实时合计多币种、付款自动关闭、linkedobject 多币种、列表合计模板。

```
htdocs/compta/accounting-files.php
htdocs/compta/ajaxpayment.php
htdocs/compta/bank/bankentries_list.php
htdocs/compta/bank/card.php
htdocs/compta/bank/class/account.class.php
htdocs/compta/bank/line.php
htdocs/compta/bank/releve.php
htdocs/compta/bank/treso.php
htdocs/compta/bank/various_payment/card.php
htdocs/compta/bank/various_payment/list.php
htdocs/compta/facture/card-rec.php
htdocs/compta/facture/card.php
htdocs/compta/facture/class/api_invoices.class.php
htdocs/compta/facture/class/facture-rec.class.php
htdocs/compta/facture/class/facture.class.php
htdocs/compta/facture/list.php
htdocs/compta/facture/tpl/linkedobjectblock.tpl.php
htdocs/compta/facture/tpl/linkedobjectblockForRec.tpl.php
htdocs/compta/index.php
htdocs/compta/paiement.php
htdocs/compta/paiement/card.php
htdocs/compta/paiement/cheque/card.php
htdocs/compta/paiement/cheque/class/remisecheque.class.php
htdocs/compta/paiement/class/paiement.class.php
htdocs/compta/paiement/list.php
htdocs/compta/paiement_vat.php
htdocs/compta/paymentbybanktransfer/index.php
htdocs/compta/prelevement/class/bonprelevement.class.php
htdocs/compta/prelevement/create.php
htdocs/compta/prelevement/fiche-rejet.php
htdocs/compta/prelevement/fiche-stat.php
htdocs/compta/prelevement/line.php
htdocs/compta/prelevement/list.php
htdocs/compta/prelevement/orders_list.php
htdocs/compta/prelevement/rejets.php
htdocs/compta/prelevement/stats.php
htdocs/compta/resultat/clientfourn.php
htdocs/compta/resultat/index.php
htdocs/compta/resultat/result.php
htdocs/compta/stats/cabyprodserv.php
htdocs/compta/stats/casoc.php
htdocs/compta/tva/class/tva.class.php
```

### 2.8 核心 (core)

- **已知**：多币种符号 MAIN_CURRENCY_SYMBOL_BEFORE_VALUE、地址 State 翻译、PDF 相关、列表合计模板、Workflow 关闭订单、Cron 时区、incoterm 等。

```
htdocs/core/actions_linkedfiles.inc.php
htdocs/core/actions_massactions.inc.php
htdocs/core/ajax/check_notifications.php
htdocs/core/ajax/selectobject.php
htdocs/core/boxes/box_actions.php
htdocs/core/boxes/box_birthdays.php
htdocs/core/boxes/box_commandes.php
htdocs/core/boxes/box_dolibarr_state_board.php
htdocs/core/boxes/box_factures.php
htdocs/core/boxes/box_factures_fourn.php
htdocs/core/boxes/box_factures_fourn_imp.php
htdocs/core/boxes/box_factures_imp.php
htdocs/core/boxes/box_graph_nb_tickets_type.php
htdocs/core/boxes/box_graph_ticket_by_severity.php
htdocs/core/boxes/box_supplier_orders.php
htdocs/core/boxes/box_supplier_orders_awaiting_reception.php
htdocs/core/class/CMailFile.class.php
htdocs/core/class/commonincoterm.class.php
htdocs/core/class/commoninvoice.class.php
htdocs/core/class/commonobject.class.php
htdocs/core/class/conf.class.php
htdocs/core/class/discount.class.php
htdocs/core/class/extrafields.class.php
htdocs/core/class/html.form.class.php
htdocs/core/class/html.formfile.class.php
htdocs/core/class/html.formticket.class.php
htdocs/core/class/utils.class.php
htdocs/core/db/pgsql.class.php
htdocs/core/js/lib_head.js.php
htdocs/core/lib/files.lib.php
htdocs/core/lib/functions.lib.php
htdocs/core/lib/functions2.lib.php
htdocs/core/lib/pdf.lib.php
htdocs/core/lib/price.lib.php
htdocs/core/lib/product.lib.php
htdocs/core/lib/security.lib.php
htdocs/core/menus/standard/auguria.lib.php
htdocs/core/menus/standard/eldy.lib.php
htdocs/core/modules/DolibarrModules.class.php
htdocs/core/modules/cheque/modules_chequereceipts.php
htdocs/core/modules/commande/doc/pdf_eratosthene.modules.php
htdocs/core/modules/facture/doc/pdf_crabe.modules.php
htdocs/core/modules/facture/doc/pdf_sponge.modules.php
htdocs/core/modules/import/import_csv.modules.php
htdocs/core/modules/import/import_xlsx.modules.php
htdocs/core/modules/modAccounting.class.php
htdocs/core/modules/modCashDesk.class.php
htdocs/core/modules/modIncoterm.class.php
htdocs/core/modules/modPartnership.class.php
htdocs/core/modules/modProduct.class.php
htdocs/core/modules/modProjet.class.php
htdocs/core/modules/modRecruitment.class.php
htdocs/core/modules/modResource.class.php
htdocs/core/modules/modService.class.php
htdocs/core/modules/modUser.class.php
htdocs/core/modules/modWorkflow.class.php
htdocs/core/modules/propale/doc/pdf_cyan.modules.php
htdocs/core/modules/reception/doc/doc_generic_reception_odt.modules.php
htdocs/core/modules/reception/doc/pdf_squille.modules.php
htdocs/core/modules/supplier_order/doc/pdf_cornas.modules.php
htdocs/core/modules/supplier_order/doc/pdf_muscadet.modules.php
htdocs/core/modules/supplier_payment/doc/pdf_standard.modules.php
htdocs/core/modules/supplier_proposal/doc/pdf_aurore.modules.php
htdocs/core/modules/workstation/mod_workstation_standard.php
htdocs/core/modules/workstation/modules_workstation.php
htdocs/core/tpl/extrafields_list_array_fields.tpl.php
htdocs/core/tpl/extrafields_view.tpl.php
htdocs/core/tpl/list_print_total.tpl.php
htdocs/core/tpl/login.tpl.php
htdocs/core/tpl/objectline_create.tpl.php
htdocs/core/tpl/originproductline.tpl.php
htdocs/core/tpl/passwordforgotten.tpl.php
htdocs/core/triggers/interface_20_modWorkflow_WorkflowManager.class.php
htdocs/core/triggers/interface_50_modAgenda_ActionsAuto.class.php
```

### 2.9 发货 (expedition)

- **已知**：ShipsGo 集成、确认提交 API、批量更新状态、来源订单列、发货单 PDF 链接、已确认/已关闭后禁止编辑、反确认（modif）、ShipsGo 状态=3 停止跟踪等。

```
htdocs/expedition/card.php
htdocs/expedition/class/api_shipments.class.php
htdocs/expedition/class/expedition.class.php
htdocs/expedition/list.php
htdocs/expedition/shipment.php
htdocs/expedition/tpl/linkedobjectblock.tpl.php
```

### 2.10 采购/供应商 (fourn)

- **已知**：采购订单列表采购代表、来源销售订单、列表合计、禁止生成 PDF、付款列表/卡片多币种、API 多币种与联系人、linkedobject 多币种。
- **说明**：SLY 还**新增**了 `htdocs/fourn/commande/orderstatus.php`（Search Order & show SO/PO Status），不在“修改”列表中；若需该页，需单独从 SLY14.0 拷贝并在菜单中挂载。

```
htdocs/fourn/class/api_supplier_invoices.class.php
htdocs/fourn/class/api_supplier_orders.class.php
htdocs/fourn/class/fournisseur.commande.class.php
htdocs/fourn/class/fournisseur.facture.class.php
htdocs/fourn/class/fournisseur.product.class.php
htdocs/fourn/class/paiementfourn.class.php
htdocs/fourn/commande/card.php
htdocs/fourn/commande/dispatch.php
htdocs/fourn/commande/list.php
htdocs/fourn/commande/tpl/linkedobjectblock.tpl.php
htdocs/fourn/facture/card.php
htdocs/fourn/facture/list.php
htdocs/fourn/facture/paiement.php
htdocs/fourn/facture/tpl/linkedobjectblock.tpl.php
htdocs/fourn/paiement/card.php
htdocs/fourn/paiement/list.php
```

### 2.11 其它业务模块（按字母）

```
htdocs/asset/admin/assets_extrafields.php
htdocs/barcode/codeinit.php
htdocs/bookmarks/bookmarks.lib.php
htdocs/cashdesk/affContenu.php
htdocs/contact/card.php
htdocs/contact/class/contact.class.php
htdocs/contact/consumption.php
htdocs/contrat/agenda.php
htdocs/contrat/card.php
htdocs/contrat/class/contrat.class.php
htdocs/contrat/tpl/linkedobjectblock.tpl.php
htdocs/cron/class/cronjob.class.php
htdocs/cron/list.php
htdocs/debugbar/class/DataCollector/DolLogsCollector.php
htdocs/delivery/class/delivery.class.php
htdocs/document.php
htdocs/don/class/don.class.php
htdocs/ecm/class/ecmfiles.class.php
htdocs/ecm/index.php
htdocs/expensereport/card.php
htdocs/expensereport/class/expensereport.class.php
htdocs/expensereport/tpl/expensereport_linktofile.tpl.php
htdocs/exports/export.php
htdocs/fichinter/card-rec.php
htdocs/fichinter/card.php
htdocs/fichinter/class/fichinter.class.php
htdocs/fichinter/class/fichinterrec.class.php
htdocs/fichinter/list.php
htdocs/holiday/card.php
htdocs/holiday/class/holiday.class.php
htdocs/holiday/month_report.php
htdocs/imports/import.php
htdocs/includes/odtphp/Segment.php
htdocs/includes/odtphp/odf.php
htdocs/includes/restler/framework/Luracast/Restler/AutoLoader.php
htdocs/install/step5.php
htdocs/install/upgrade.php
htdocs/loan/card.php
htdocs/loan/payment/payment.php
htdocs/loan/schedule.php
htdocs/main.inc.php
htdocs/modulebuilder/index.php
htdocs/modulebuilder/template/class/myobject.class.php
htdocs/modulebuilder/template/core/modules/modMyModule.class.php
htdocs/mrp/class/mo.class.php
htdocs/product/admin/product.php
htdocs/product/card.php
htdocs/product/class/api_products.class.php
htdocs/product/class/product.class.php
htdocs/product/class/productbatch.class.php
htdocs/product/fournisseurs.php
htdocs/product/inventory/inventory.php
htdocs/product/list.php
htdocs/product/note.php
htdocs/product/price.php
htdocs/product/reassort.php
htdocs/product/reassortlot.php
htdocs/product/stats/bom.php
htdocs/product/stats/commande.php
htdocs/product/stats/commande_fournisseur.php
htdocs/product/stats/contrat.php
htdocs/product/stats/facture.php
htdocs/product/stats/facture_fournisseur.php
htdocs/product/stats/mo.php
htdocs/product/stats/propal.php
htdocs/product/stats/supplier_proposal.php
htdocs/product/stock/card.php
htdocs/product/stock/class/entrepot.class.php
htdocs/product/stock/class/mouvementstock.class.php
htdocs/product/stock/replenish.php
htdocs/product/stock/stockatdate.php
htdocs/projet/activity/perday.php
htdocs/projet/activity/permonth.php
htdocs/projet/activity/perweek.php
htdocs/projet/card.php
htdocs/projet/class/project.class.php
htdocs/projet/class/task.class.php
htdocs/projet/element.php
htdocs/projet/tasks/contact.php
htdocs/projet/tasks/task.php
htdocs/public/cron/cron_run_jobs_by_url.php
htdocs/public/members/new.php
htdocs/public/payment/newpayment.php
htdocs/public/test/test_exec.php
htdocs/public/ticket/create_ticket.php
htdocs/public/ticket/list.php
htdocs/reception/card.php
htdocs/reception/class/reception.class.php
htdocs/reception/list.php
htdocs/recruitment/core/modules/recruitment/doc/pdf_standard_recruitmentjobposition.modules.php
htdocs/recruitment/core/modules/recruitment/modules_recruitmentcandidature.php
htdocs/salaries/card.php
htdocs/salaries/class/paymentsalary.class.php
htdocs/salaries/class/salariesstats.class.php
htdocs/salaries/document.php
htdocs/salaries/info.php
htdocs/salaries/payments.php
htdocs/salaries/stats/index.php
htdocs/societe/admin/societe.php
htdocs/societe/card.php
htdocs/societe/class/api_thirdparties.class.php
htdocs/societe/class/companybankaccount.class.php
htdocs/societe/class/societe.class.php
htdocs/societe/consumption.php
htdocs/societe/list.php
htdocs/supplier_proposal/card.php
htdocs/supplier_proposal/class/supplier_proposal.class.php
htdocs/supplier_proposal/list.php
htdocs/takepos/invoice.php
htdocs/takepos/receipt.php
htdocs/theme/eldy/global.inc.php
htdocs/ticket/card.php
htdocs/ticket/class/actions_ticket.class.php
htdocs/ticket/class/ticket.class.php
htdocs/ticket/list.php
htdocs/ticket/messaging.php
htdocs/user/bank.php
htdocs/user/card.php
htdocs/user/class/api_users.class.php
htdocs/user/class/user.class.php
htdocs/user/param_ihm.php
htdocs/variants/ajax/get_attribute_values.php
htdocs/variants/class/ProductAttribute.class.php
htdocs/variants/class/ProductAttributeValue.class.php
htdocs/variants/class/ProductCombination.class.php
htdocs/variants/combinations.php
htdocs/viewimage.php
htdocs/website/index.php
htdocs/workstation/lib/workstation_workstation.lib.php
```

---

## 三、语言包 (htdocs/langs) 被修改文件

共 **95** 个。以下按语言与模块列出（路径均为 `htdocs/langs/` 下）。

### 3.1 英文 (en_US)

```
en_US/admin.lang
en_US/bills.lang
en_US/compta.lang
en_US/dict.lang
en_US/errors.lang
en_US/interventions.lang
en_US/main.lang
en_US/orders.lang
en_US/productbatch.lang
en_US/products.lang
en_US/projects.lang
en_US/propal.lang
en_US/sendings.lang
en_US/stocks.lang
en_US/ticket.lang
en_US/workflow.lang
```

### 3.2 法文 (fr_FR)

```
fr_FR/compta.lang
fr_FR/main.lang
fr_FR/orders.lang
fr_FR/productbatch.lang
fr_FR/products.lang
```

### 3.3 简体中文 (zh_CN)

```
zh_CN/accountancy.lang
zh_CN/admin.lang
zh_CN/agenda.lang
zh_CN/assets.lang
zh_CN/banks.lang
zh_CN/bills.lang
zh_CN/blockedlog.lang
zh_CN/bookmarks.lang
zh_CN/boxes.lang
zh_CN/cashdesk.lang
zh_CN/categories.lang
zh_CN/commercial.lang
zh_CN/companies.lang
zh_CN/compta.lang
zh_CN/contracts.lang
zh_CN/cron.lang
zh_CN/deliveries.lang
zh_CN/dict.lang
zh_CN/donations.lang
zh_CN/ecm.lang
zh_CN/errors.lang
zh_CN/eventorganization.lang
zh_CN/exports.lang
zh_CN/externalsite.lang
zh_CN/ftp.lang
zh_CN/help.lang
zh_CN/holiday.lang
zh_CN/hrm.lang
zh_CN/install.lang
zh_CN/interventions.lang
zh_CN/intracommreport.lang
zh_CN/knowledgemanagement.lang
zh_CN/languages.lang
zh_CN/ldap.lang
zh_CN/link.lang
zh_CN/loan.lang
zh_CN/mailmanspip.lang
zh_CN/mails.lang
zh_CN/main.lang
zh_CN/margins.lang
zh_CN/members.lang
zh_CN/modulebuilder.lang
zh_CN/mrp.lang
zh_CN/multicurrency.lang
zh_CN/oauth.lang
zh_CN/opensurvey.lang
zh_CN/orders.lang
zh_CN/other.lang
zh_CN/partnership.lang
zh_CN/paybox.lang
zh_CN/paypal.lang
zh_CN/printing.lang
zh_CN/productbatch.lang
zh_CN/products.lang
zh_CN/projects.lang
zh_CN/propal.lang
zh_CN/receiptprinter.lang
zh_CN/receptions.lang
zh_CN/recruitment.lang
zh_CN/resource.lang
zh_CN/salaries.lang
zh_CN/sendings.lang
zh_CN/sms.lang
zh_CN/stocks.lang
zh_CN/stripe.lang
zh_CN/supplier_proposal.lang
zh_CN/suppliers.lang
zh_CN/ticket.lang
zh_CN/trips.lang
zh_CN/users.lang
zh_CN/website.lang
zh_CN/withdrawals.lang
zh_CN/workflow.lang
zh_CN/zapier.lang
```

---

## 四、安装 / 数据库 / 构建 / 脚本 / 其它

### 4.1 安装与数据库

```
htdocs/install/default.css
htdocs/install/mysql/data/llx_c_tva.sql
htdocs/install/mysql/migration/11.0.0-12.0.0.sql
htdocs/install/mysql/migration/12.0.0-13.0.0.sql
htdocs/install/mysql/migration/13.0.0-14.0.0.sql
htdocs/install/mysql/tables/llx_establishment.sql
htdocs/install/mysql/tables/llx_product.sql
htdocs/install/mysql/tables/llx_product_fournisseur_price.sql
htdocs/install/mysql/tables/llx_product_price.sql
htdocs/install/mysql/tables/llx_propaldet.sql
```

### 4.2 构建与 CI

```
.travis.yml
build/docker/Dockerfile
build/docker/docker-compose.yml
build/generate_filelist_xml.php
build/makepack-dolibarr.pl
```

### 4.3 脚本与测试

```
scripts/cron/cron_run_jobs.php
scripts/emailings/mailing-send.php
test/phpunit/SecurityTest.php
```

### 4.4 其它

```
ChangeLog
dev/dolibarr_changes.txt
htdocs/blockedlog/README-fr.md
htdocs/blockedlog/README.md
```

---

## 五、如何用本清单在官方 14.0 上恢复定制

1. **按需选择**：根据业务依赖，从上文“已知定制用途”和目录分类中圈定必须保留的文件集合。
2. **逐文件对比**：在 SLY14.0 仓库中执行  
   `git diff upstream/14.0..SLY14.0 -- 文件路径`  
   得到该文件相对官方 14.0 的完整差异。
3. **生成补丁**：  
   `git diff upstream/14.0..SLY14.0 -- 路径1 路径2 ... > my-patches.patch`  
   可在官方 14.0 上尝试 `git apply` 或手工合并（注意冲突）。
4. **升级后重做**：官方 14.x 小版本升级后，核心文件可能变动，需重新对比并合并或重打上述定制。

若只希望“尽量少改核心、多用模块”，可优先保留与多币种、ShipsGo、工作流、列表列/合计、发票付款逻辑直接相关的文件，其余通过 hook/模块或接受功能差异逐步替代。
