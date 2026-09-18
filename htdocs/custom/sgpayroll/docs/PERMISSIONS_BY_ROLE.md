# SG Payroll 权限按角色分配指南

本文档说明 SG Payroll 模块各权限的含义，以及针对**员工、HR Manager、财务、管理员**等角色的推荐分配方式。

---

## 一、权限列表（模块内全部 11 项）

| 权限名称（界面显示） | 含义 | 代码中的标识 |
|---------------------|------|-----------------------------|
| Read employee payroll profiles | 查看员工工资/人事档案 | employee / read |
| Create/Modify employee payroll profiles | 创建与修改员工档案 | employee / write |
| Modify own employee profile (Self-Service) | 自助：改自己的档案、看自己的工资单 | employee / self_write |
| Consult all payslips | 查看所有工资单 | payroll / read |
| Create/approve payslips | 创建、计算、批准工资单 | payroll / approve（部分逻辑用 create） |
| Review/Confirm AIS records | 审查与确认 IRAS AIS 记录 | ais / review |
| Generate statutory exports (CPF/IRAS) | 生成法定导出（CPF、IR8A 等） | export / iras |
| Apply for own leave | 申请自己的假期 | leave / apply |
| Approve employee leave | 批准员工假期 | leave / approve |
| Submit own expense claims | 提交自己的费用报销 | claims / submit |
| Approve employee claims | 批准员工报销 | claims / approve |

> **说明**：Dolibarr 的**管理员**（admin）在代码中通常被当作拥有全部权限，无需在 SG Payroll 里逐项勾选。

---

## 二、按角色推荐分配

### 1. 员工（Employee）— 仅自助

只做本人相关操作：看自己的工资单、改自己的档案、申请假期、提交报销；**不能**看他人档案或审批。

| 权限 | 是否勾选 | 说明 |
|------|----------|------|
| Read employee payroll profiles | ❌ | 会看到所有员工档案，员工不需要 |
| Create/Modify employee payroll profiles | ❌ | 仅 HR 需要 |
| **Modify own employee profile (Self-Service)** | ✅ | **必选**：自助改档案、看自己的 payslip |
| Consult all payslips | ❌ | 会看到所有人工资单，员工不需要 |
| Create/approve payslips | ❌ | 仅 HR/财务 |
| Review/Confirm AIS records | ❌ | 仅 HR/财务 |
| Generate statutory exports (CPF/IRAS) | ❌ | 仅财务 |
| **Apply for own leave** | ✅ | 申请自己的假期 |
| Approve employee leave | ❌ | 仅 HR/主管 |
| **Submit own expense claims** | ✅ | 提交自己的报销 |
| Approve employee claims | ❌ | 仅财务/主管 |

**员工门户**：只要模块启用且已登录即可进入，在门户内可查看自己的 AIS、工资单摘要等，不依赖上述权限。

---

### 2. HR Manager（人事/工资主管）

负责：员工档案、工资单制作与批准、AIS 审查、假期审批；一般不负责 CPF/IRAS 申报文件生成和报销审批（可交给财务）。

| 权限 | 是否勾选 | 说明 |
|------|----------|------|
| **Read employee payroll profiles** | ✅ | 查看所有员工档案 |
| **Create/Modify employee payroll profiles** | ✅ | 新建、修改员工档案 |
| Modify own employee profile (Self-Service) | 可选 | 若 HR 本人也要用自助可勾选 |
| **Consult all payslips** | ✅ | 查看所有工资单列表与详情 |
| **Create/approve payslips** | ✅ | 创建、计算、批准工资单 |
| **Review/Confirm AIS records** | ✅ | AIS 审查与确认（IR8A 前一步） |
| Generate statutory exports (CPF/IRAS) | 可选 | 若 HR 也负责报 CPF/IRAS 则勾选 |
| Apply for own leave | 可选 | 若 HR 本人要申请假期 |
| **Approve employee leave** | ✅ | 批准员工假期 |
| Submit own expense claims | 可选 | 若 HR 本人要提交报销 |
| Approve employee claims | 可选 | 若 HR 也负责批报销则勾选 |

---

### 3. 财务（Finance）— 工资付款、法定申报、报销

负责：按已批准工资单付款、生成 CPF/IRAS 等法定导出、审批报销。需要能**查看**工资单，是否**批准**工资单视公司分工（有的公司由 HR 批准、财务只付款）。

| 权限 | 是否勾选 | 说明 |
|------|----------|------|
| **Read employee payroll profiles** | ✅ | 查看员工档案（付款、申报时需要） |
| Create/Modify employee payroll profiles | 可选 | 若财务也维护档案则勾选 |
| Modify own employee profile (Self-Service) | 可选 | 自助用 |
| **Consult all payslips** | ✅ | **必选**：查看工资单以便付款、核对 |
| **Create/approve payslips** | 视分工 | 若财务负责批准工资单则勾选；若只付款不批准可不勾 |
| Review/Confirm AIS records | 可选 | 若财务负责 AIS 确认则勾选 |
| **Generate statutory exports (CPF/IRAS)** | ✅ | **必选**：CPF、IR8A 等法定导出 |
| Apply for own leave | 可选 | 本人申请假期 |
| Approve employee leave | ❌ | 通常归 HR |
| Submit own expense claims | 可选 | 本人提交报销 |
| **Approve employee claims** | ✅ | **必选**：审批员工报销 |

---

### 4. 管理员（Administrator）

- **Dolibarr 管理员账号**（admin）：代码中 `$user->admin` 为真时等同于拥有全部权限，**不需要**在 SG Payroll 里逐项勾选。
- **非 admin 的“业务管理员”**：若希望其拥有 SG Payroll 全部能力，则把下面除“自助”外的**所有权限**都勾选（或按需勾选与 HR + 财务一致）。

| 权限 | 是否勾选 |
|------|----------|
| Read employee payroll profiles | ✅ |
| Create/Modify employee payroll profiles | ✅ |
| Modify own employee profile (Self-Service) | 可选 |
| Consult all payslips | ✅ |
| Create/approve payslips | ✅ |
| Review/Confirm AIS records | ✅ |
| Generate statutory exports (CPF/IRAS) | ✅ |
| Apply for own leave | 可选 |
| Approve employee leave | ✅ |
| Submit own expense claims | 可选 |
| Approve employee claims | ✅ |

---

## 三、快速对照表

| 权限项 | 员工 | HR Manager | 财务 | 管理员(非admin) |
|--------|------|------------|------|------------------|
| Read employee payroll profiles | ❌ | ✅ | ✅ | ✅ |
| Create/Modify employee payroll profiles | ❌ | ✅ | 可选 | ✅ |
| Modify own employee profile (Self-Service) | ✅ | 可选 | 可选 | 可选 |
| Consult all payslips | ❌ | ✅ | ✅ | ✅ |
| Create/approve payslips | ❌ | ✅ | 视分工 | ✅ |
| Review/Confirm AIS records | ❌ | ✅ | 可选 | ✅ |
| Generate statutory exports (CPF/IRAS) | ❌ | 可选 | ✅ | ✅ |
| Apply for own leave | ✅ | 可选 | 可选 | 可选 |
| Approve employee leave | ❌ | ✅ | ❌ | ✅ |
| Submit own expense claims | ✅ | 可选 | 可选 | 可选 |
| Approve employee claims | ❌ | 可选 | ✅ | ✅ |

---

## 四、与菜单/页面的关系（简要）

- **SG Payroll 主菜单**：有 `payroll/read`、`employee/read` 或 `claims/submit` 之一即可看到主菜单。
- **员工门户**：仅需登录且模块启用，不依赖上述 11 项权限。
- **工资单列表/详情**：需 `payroll/read` 或（本人 + `employee/self_write`）；创建/批准需 `payroll/approve`。
- **AIS 审查页**：本人可查看自己的 AIS；查看所有人及审查/确认需 `ais/review`。
- **CPF/IRAS 导出**：需 `export/iras`（CPF 导出在代码中可能使用 `export/cpf`，若存在则与 iras 同组或单独配置）。
- **报销列表/审批**：提交需 `claims/submit`，审批需 `claims/approve`。
- **假期申请/审批**：申请需 `leave/apply`，审批需 `leave/approve`。

可根据实际分工在“可选”项上微调（例如 HR 也做报销审批、财务也做 AIS 确认等）。
